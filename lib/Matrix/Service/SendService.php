<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Exception\ForbiddenException;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\TransportException;
use Nextcloud\Matrix\Model\PowerLevels;
use Nextcloud\Matrix\Util\Identifier;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Matrix\Mapping\EventMapper;
use OCA\Talk\Matrix\Mapping\Formatter;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\CapabilityResolver;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Security\ISecureRandom;

/**
 * Talk → Matrix. Every method sends to the homeserver first and only then
 * touches Talk's storage, so a failed send never leaves a local message.
 */
class SendService {
	public function __construct(
		private readonly AccountService $accountService,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly EventMapMapper $eventMapMapper,
		private readonly EventMapper $eventMapper,
		private readonly Formatter $formatter,
		private readonly CapabilityResolver $capabilities,
		private readonly ChatManager $chatManager,
		private readonly CryptoService $cryptoService,
		private readonly \OCA\Talk\Matrix\Model\MatrixMemberMapper $memberMapper,
		private readonly \OCA\Talk\Matrix\ClientFactory $clientFactory,
		private readonly \OCA\Talk\Chat\ReactionManager $reactionManager,
		private readonly \OCA\Talk\Matrix\MatrixConfig $config,
		private readonly ISecureRandom $random,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @throws MatrixSendException
	 */
	public function getMatrixRoom(Room $room): MatrixRoom {
		try {
			return $this->roomMapper->getByRoomId($room->getId());
		} catch (DoesNotExistException) {
			throw new MatrixSendException('matrix-room', Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @throws MatrixSendException
	 */
	public function requireAccount(Participant $participant): Account {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			throw new MatrixSendException('actor', Http::STATUS_FORBIDDEN);
		}
		$account = $this->accountService->getForUser($attendee->getActorId());
		if ($account === null) {
			throw new MatrixSendException('no-matrix-account', Http::STATUS_FORBIDDEN);
		}
		if (!$account->isActive()) {
			throw new MatrixSendException('matrix-relogin', Http::STATUS_FORBIDDEN);
		}
		return $account;
	}

	/**
	 * Send a chat message; on success mirror it locally and return the comment.
	 *
	 * @throws MatrixSendException
	 */
	public function sendMessage(Room $room, Participant $participant, string $message, ?IComment $replyTo, string $referenceId, bool $silent, int $threadId = 0, string $threadTitle = ''): IComment {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		if ($matrixRoom->getEncrypted() && !$this->clientFactory->getHomeserver($account->getHomeserverId())->getAllowE2ee()) {
			throw new MatrixSendException('e2ee-disabled', Http::STATUS_METHOD_NOT_ALLOWED);
		}

		$replyToEventId = null;
		if ($replyTo !== null) {
			$replyToEventId = $this->eventMapMapper->findByCommentId((int)$replyTo->getId())?->getEventId();
		}

		$threadRootEventId = null;
		if ($threadId > 0) {
			$threadRootEventId = $this->eventMapMapper->findByCommentId($threadId)?->getEventId();
			if ($threadRootEventId === null) {
				throw new MatrixSendException('thread', Http::STATUS_BAD_REQUEST);
			}
		}
		$formatted = $this->formatter->outgoing($message, $matrixRoom->getMatrixRoomId());
		$content = Client::textContent($formatted['body'], $formatted['html'], $replyToEventId, $threadRootEventId, null, $formatted['mentions'], $formatted['mentionRoom']);
		$txnId = $this->txnId();

		$eventId = $this->sendRoomEvent($matrixRoom, $account, 'm.room.message', $content, $txnId);

		$comment = $this->chatManager->sendMessage(
			$room,
			$participant,
			Attendee::ACTOR_USERS,
			$account->getUserId(),
			$message,
			$this->timeFactory->getDateTime('now', new \DateTimeZone('UTC')),
			$replyTo,
			$referenceId,
			$silent,
			threadId: $threadId,
			threadTitle: $threadTitle,
		);
		$this->eventMapper->recordOutgoing($matrixRoom, $eventId, 'm.room.message', $comment, $txnId, $account->getMxid(), $threadRootEventId ?? $replyToEventId, $threadRootEventId !== null ? 'm.thread' : ($replyToEventId !== null ? 'm.in_reply_to' : null));
		return $comment;
	}

	/**
	 * Send a room event, encrypting it when the room is encrypted.
	 * @param array<string, mixed> $content
	 * @throws MatrixSendException
	 */
	public function sendRoomEvent(MatrixRoom $matrixRoom, Account $account, string $type, array $content, string $txnId): string {
		if ($matrixRoom->getEncrypted()) {
			$memberIds = [];
			foreach ($this->memberMapper->getForRoom($matrixRoom->getMatrixRoomId()) as $member) {
				if ($member->getMembership() === 'join' || $member->getMembership() === 'invite') {
					$memberIds[] = $member->getMxid();
				}
			}
			try {
				$encrypted = $this->cryptoService->encryptRoomEvent($account, $matrixRoom->getMatrixRoomId(), $type, $content, $memberIds, $matrixRoom->getRotationPeriodMs(), $matrixRoom->getRotationPeriodMsgs());
			} catch (\Nextcloud\Matrix\Crypto\CryptoException $e) {
				throw new MatrixSendException('encryption-failed', Http::STATUS_BAD_GATEWAY, $e);
			}
			return $this->call(fn (Client $client) => $client->sendEvent($matrixRoom->getMatrixRoomId(), 'm.room.encrypted', $encrypted, $txnId), $account);
		}
		return $this->call(fn (Client $client) => $client->sendEvent($matrixRoom->getMatrixRoomId(), $type, $content, $txnId), $account);
	}

	/**
	 * Edit: m.replace on Matrix, then the local edit.
	 * @throws MatrixSendException
	 */
	public function editMessage(Room $room, Participant $participant, IComment $comment, string $message): IComment {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$map = $this->eventMapMapper->findByCommentId((int)$comment->getId());
		if ($map === null) {
			throw new MatrixSendException('message', Http::STATUS_NOT_FOUND);
		}
		if ($map->getSender() !== $account->getMxid()) {
			throw new MatrixSendException('permission', Http::STATUS_FORBIDDEN);
		}
		$formatted = $this->formatter->outgoing($message, $matrixRoom->getMatrixRoomId());
		$newContent = Client::textContent($formatted['body'], $formatted['html'], null, null, null, $formatted['mentions'], $formatted['mentionRoom']);
		$content = Client::textContent('* ' . $formatted['body'], $formatted['html'] !== null ? '* ' . $formatted['html'] : null);
		$content['m.new_content'] = $newContent;
		$content['m.relates_to'] = ['rel_type' => 'm.replace', 'event_id' => $map->getEventId()];
		$txnId = $this->txnId();
		$eventId = $this->sendRoomEvent($matrixRoom, $account, 'm.room.message', $content, $txnId);
		$systemMessage = $this->chatManager->editMessage($room, $comment, $participant, $this->timeFactory->getDateTime(), $message);
		$this->eventMapper->recordOutgoing($matrixRoom, $eventId, 'm.room.message', $systemMessage, $txnId, $account->getMxid(), $map->getEventId(), 'm.replace');
		return $systemMessage;
	}

	/**
	 * Delete: redact on Matrix (power levels decide about others' messages), then locally.
	 * @throws MatrixSendException
	 */
	public function deleteMessage(Room $room, Participant $participant, IComment $comment): IComment {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$map = $this->eventMapMapper->findByCommentId((int)$comment->getId());
		if ($map === null) {
			throw new MatrixSendException('message', Http::STATUS_NOT_FOUND);
		}
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if ($map->getSender() !== $account->getMxid() && !$powerLevels->canDo($account->getMxid(), 'redact')) {
			throw new MatrixSendException('permission', Http::STATUS_FORBIDDEN);
		}
		$txnId = $this->txnId();
		$redactionId = $this->call(fn (Client $client) => $client->redact($matrixRoom->getMatrixRoomId(), $map->getEventId(), $txnId), $account);
		$systemMessage = $this->chatManager->deleteMessage($room, $comment, $participant, $this->timeFactory->getDateTime());
		$this->eventMapper->recordOutgoing($matrixRoom, $redactionId, 'm.room.redaction', $systemMessage, $txnId, $account->getMxid(), $map->getEventId(), 'redaction');
		return $systemMessage;
	}

	/**
	 * @throws MatrixSendException
	 */
	public function addReaction(Room $room, Participant $participant, int $messageId, string $reaction): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$target = $this->eventMapMapper->findByCommentId($messageId);
		if ($target === null) {
			throw new MatrixSendException('message', Http::STATUS_NOT_FOUND);
		}
		$txnId = $this->txnId();
		$content = ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => $target->getEventId(), 'key' => $reaction]];
		// Reactions are not encrypted in practice (Element sends them in clear even in encrypted rooms)
		$eventId = $this->call(fn (Client $client) => $client->sendEvent($matrixRoom->getMatrixRoomId(), 'm.reaction', $content, $txnId), $account);
		$attendee = $participant->getAttendee();
		try {
			$comment = $this->reactionManager->addReactionMessage($room, $attendee->getActorType(), $attendee->getActorId(), $attendee->getDisplayName(), $messageId, $reaction);
			$this->eventMapper->recordOutgoing($matrixRoom, $eventId, 'm.reaction', $comment, $txnId, $account->getMxid(), $target->getEventId(), 'm.annotation');
		} catch (\OCA\Talk\Exceptions\ReactionAlreadyExistsException $e) {
			throw $e;
		}
	}

	/**
	 * @throws MatrixSendException
	 */
	public function removeReaction(Room $room, Participant $participant, int $messageId, string $reaction): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$attendee = $participant->getAttendee();
		// Find our reaction comment → its Matrix event → redact it
		$reactionComment = null;
		foreach ($this->reactionManager->retrieveReactionMessages($room, $participant, $messageId, $reaction) as $candidate) {
			if ($candidate->getActorType() === $attendee->getActorType() && $candidate->getActorId() === $attendee->getActorId()) {
				$reactionComment = $candidate;
				break;
			}
		}
		if ($reactionComment !== null) {
			$map = $this->eventMapMapper->findByCommentId((int)$reactionComment->getId());
			if ($map !== null) {
				$this->call(fn (Client $client) => $client->redact($matrixRoom->getMatrixRoomId(), $map->getEventId(), $this->txnId()), $account);
			}
		}
		$this->reactionManager->deleteReactionMessage($room, $attendee->getActorType(), $attendee->getActorId(), $attendee->getDisplayName(), $messageId, $reaction);
	}

	/**
	 * Upload a Nextcloud file to the homeserver and post it (encrypted attachment in encrypted rooms).
	 * @throws MatrixSendException
	 */
	public function sendFile(Room $room, Account $account, \OCP\Files\File $file, IComment $talkComment): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$homeserver = $this->clientFactory->getHomeserver($account->getHomeserverId());
		if (!$homeserver->getAllowUpload()) {
			throw new MatrixSendException('upload-disabled', Http::STATUS_METHOD_NOT_ALLOWED);
		}
		$size = $file->getSize();
		if ($size > $this->config->getMaxUpload()) {
			throw new MatrixSendException('too-large', Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		}
		$mimetype = $file->getMimeType();
		$msgtype = match (true) {
			str_starts_with($mimetype, 'image/') => 'm.image',
			str_starts_with($mimetype, 'video/') => 'm.video',
			str_starts_with($mimetype, 'audio/') => 'm.audio',
			default => 'm.file',
		};
		$info = ['mimetype' => $mimetype, 'size' => $size];
		if ($msgtype === 'm.image') {
			$dimensions = @getimagesizefromstring($file->getContent());
			if (is_array($dimensions)) {
				$info['w'] = $dimensions[0];
				$info['h'] = $dimensions[1];
			}
		}
		$content = ['msgtype' => $msgtype, 'body' => $file->getName(), 'filename' => $file->getName(), 'info' => $info];
		$factory = new \GuzzleHttp\Psr7\HttpFactory();
		if ($matrixRoom->getEncrypted()) {
			$encrypted = \Nextcloud\Matrix\Crypto\Attachment::encrypt($file->getContent());
			$mxc = $this->call(fn (Client $client) => $client->uploadMedia($factory->createStream($encrypted['ciphertext']), 'application/octet-stream', null), $account);
			$content['file'] = $encrypted['file'] + ['url' => (string)$mxc];
		} else {
			$mxc = $this->call(fn (Client $client) => $client->uploadMedia($factory->createStream($file->getContent()), $mimetype, $file->getName()), $account);
			$content['url'] = (string)$mxc;
		}
		$txnId = $this->txnId();
		$eventId = $this->sendRoomEvent($matrixRoom, $account, 'm.room.message', $content, $txnId);
		$this->eventMapper->recordOutgoing($matrixRoom, $eventId, 'm.room.message', $talkComment, $txnId, $account->getMxid());
	}

	/**
	 * @throws MatrixSendException
	 */
	public function rename(Room $room, Participant $participant, string $name): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$this->requireStatePermission($matrixRoom, $account, 'm.room.name');
		$this->call(fn (Client $client) => $client->sendStateEvent($matrixRoom->getMatrixRoomId(), 'm.room.name', ['name' => $name]), $account);
	}

	/**
	 * @throws MatrixSendException
	 */
	public function setTopic(Room $room, Participant $participant, string $topic): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$this->requireStatePermission($matrixRoom, $account, 'm.room.topic');
		$this->call(fn (Client $client) => $client->sendStateEvent($matrixRoom->getMatrixRoomId(), 'm.room.topic', ['topic' => $topic]), $account);
	}

	/**
	 * @param string $target Matrix user id, or a Nextcloud user id (resolved to their linked account)
	 * @throws MatrixSendException
	 */
	public function invite(Room $room, Participant $participant, string $target, bool $targetIsNextcloudUser): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		if ($targetIsNextcloudUser) {
			$targetAccount = $this->accountService->getForUser($target);
			if ($targetAccount === null) {
				throw new MatrixSendException('no-matrix-account', Http::STATUS_BAD_REQUEST);
			}
			$mxid = $targetAccount->getMxid();
		} else {
			if (!Identifier::isUserId($target)) {
				throw new MatrixSendException('invalid-mxid', Http::STATUS_BAD_REQUEST);
			}
			$mxid = $target;
		}
		$this->requireActionPermission($matrixRoom, $account, 'invite');
		$this->call(fn (Client $client) => $client->invite($matrixRoom->getMatrixRoomId(), $mxid), $account);
	}

	/**
	 * @throws MatrixSendException
	 */
	public function kick(Room $room, Participant $participant, string $targetMxid, bool $ban = false): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if (!$powerLevels->canActOn($account->getMxid(), $targetMxid, $ban ? 'ban' : 'kick')) {
			throw new MatrixSendException('power-level', Http::STATUS_FORBIDDEN);
		}
		$this->call(fn (Client $client) => $ban ? $client->ban($matrixRoom->getMatrixRoomId(), $targetMxid) : $client->kick($matrixRoom->getMatrixRoomId(), $targetMxid), $account);
	}

	/**
	 * @throws MatrixSendException
	 */
	public function leave(Room $room, Participant $participant): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$this->call(function (Client $client) use ($matrixRoom): void {
			$client->leave($matrixRoom->getMatrixRoomId());
			try {
				$client->forget($matrixRoom->getMatrixRoomId());
			} catch (MatrixException) {
				// forget is best effort
			}
		}, $account);
	}

	/**
	 * @throws MatrixSendException
	 */
	public function setPowerLevel(Room $room, Participant $participant, string $targetMxid, int $level): void {
		$matrixRoom = $this->getMatrixRoom($room);
		$account = $this->requireAccount($participant);
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if (!$powerLevels->canChangeUserLevel($account->getMxid(), $targetMxid, $level)) {
			throw new MatrixSendException('power-level', Http::STATUS_FORBIDDEN);
		}
		$content = $powerLevels->withUserLevel($targetMxid, $level);
		$this->call(fn (Client $client) => $client->sendStateEvent($matrixRoom->getMatrixRoomId(), 'm.room.power_levels', $content), $account);
	}

	/**
	 * Mirror the Talk read marker to Matrix (best effort, throttled by caller).
	 */
	public function sendReadReceipt(Room $room, Participant $participant, int $commentId): void {
		try {
			$matrixRoom = $this->getMatrixRoom($room);
			$account = $this->requireAccount($participant);
		} catch (MatrixSendException) {
			return;
		}
		$eventId = $this->eventMapMapper->findByCommentId($commentId)?->getEventId();
		if ($eventId === null) {
			return;
		}
		$store = $this->cryptoService->store($account);
		$state = json_decode((string)$store->getSecret('receipt:' . $matrixRoom->getMatrixRoomId()), true) ?: ['id' => 0, 'ts' => 0];
		$now = $this->timeFactory->getTime();
		if ((int)$state['id'] >= $commentId || ($now - (int)$state['ts']) < 5) {
			return; // already acknowledged a newer message, or throttled
		}
		try {
			$this->accountService->client($account, 5)->setReadMarker($matrixRoom->getMatrixRoomId(), $eventId);
			$store->setSecret('receipt:' . $matrixRoom->getMatrixRoomId(), json_encode(['id' => $commentId, 'ts' => $now]));
		} catch (MatrixException) {
			// receipts are not worth an error
		}
	}

	/**
	 * @throws MatrixSendException
	 */
	private function requireStatePermission(MatrixRoom $matrixRoom, Account $account, string $eventType): void {
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if (!$powerLevels->canSendEvent($account->getMxid(), $eventType, true)) {
			throw new MatrixSendException('power-level', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * @throws MatrixSendException
	 */
	private function requireActionPermission(MatrixRoom $matrixRoom, Account $account, string $action): void {
		$powerLevels = new PowerLevels($matrixRoom->getPowerLevelsArray(), $matrixRoom->getCreator());
		if (!$powerLevels->canDo($account->getMxid(), $action)) {
			throw new MatrixSendException('power-level', Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Run a homeserver call translating library exceptions into HTTP-ish errors.
	 *
	 * @template T
	 * @param callable(Client): T $fn
	 * @return T
	 * @throws MatrixSendException
	 */
	private function call(callable $fn, Account $account) {
		try {
			return $fn($this->accountService->client($account, 20));
		} catch (\Nextcloud\Matrix\Exception\UnknownTokenException $e) {
			$this->accountService->markTokenInvalid($account, $e->getMessage());
			throw new MatrixSendException('matrix-relogin', Http::STATUS_FORBIDDEN, $e);
		} catch (ForbiddenException $e) {
			throw new MatrixSendException($e->getMessage(), Http::STATUS_FORBIDDEN, $e);
		} catch (TransportException $e) {
			throw new MatrixSendException('matrix-unreachable', Http::STATUS_BAD_GATEWAY, $e);
		} catch (MatrixException $e) {
			$status = $e->getHttpStatus() >= 400 && $e->getHttpStatus() < 500 ? $e->getHttpStatus() : Http::STATUS_BAD_GATEWAY;
			throw new MatrixSendException($e->getMessage() !== '' ? $e->getMessage() : 'matrix', $status, $e);
		}
	}

	private function txnId(): string {
		return 'nc-' . $this->random->generate(24, ISecureRandom::CHAR_ALPHANUMERIC);
	}
}
