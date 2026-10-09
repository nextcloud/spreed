<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\UnknownTokenException;
use OCA\Talk\Matrix\Client\Html\MarkdownToHtml;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

/**
 * Sends changes of Matrix conversations to the Matrix room. The homeserver
 * has to accept them first, only then the conversation is changed, so a
 * failed send never leaves a local message behind.
 */
class SendService {
	private ICache $readMarkerCache;

	public function __construct(
		private readonly AccountService $accountService,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly EventMapMapper $eventMapMapper,
		private readonly ChatManager $chatManager,
		private readonly ReactionManager $reactionManager,
		private readonly ICommentsManager $commentsManager,
		private readonly IUserManager $userManager,
		private readonly ISecureRandom $secureRandom,
		private readonly ITimeFactory $timeFactory,
		ICacheFactory $cacheFactory,
	) {
		$this->readMarkerCache = $cacheFactory->createDistributed(CachePrefix::MATRIX_READ_MARKER);
	}

	/**
	 * @throws SendException
	 */
	public function sendMessage(Room $room, Participant $participant, string $message, ?IComment $replyTo, string $referenceId, bool $silent): IComment {
		[$matrixRoom, $account] = $this->prepare($room, $participant);

		$content = $this->getTextContent($message);
		if ($replyTo !== null) {
			$parent = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), (int)$replyTo->getId());
			if ($parent !== null) {
				$content['m.relates_to'] = ['m.in_reply_to' => ['event_id' => $parent->getEventId()]];
			}
		}

		$eventId = $this->send($account, fn (Client $client, string $transactionId): string => $client->sendEvent($matrixRoom->getMatrixRoomId(), 'm.room.message', $content, $transactionId));
		$eventMap = $this->claimEvent($matrixRoom, $account, $eventId, 'm.room.message');
		if ($eventMap === null) {
			return $this->getSyncedComment($matrixRoom, $eventId);
		}

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
		);
		$eventMap->setCommentId((int)$comment->getId());
		$this->eventMapMapper->update($eventMap);
		return $comment;
	}

	/**
	 * @return IComment The system message about the edit
	 * @throws SendException
	 */
	public function editMessage(Room $room, Participant $participant, IComment $comment, string $message): IComment {
		[$matrixRoom, $account] = $this->prepare($room, $participant);
		$target = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), (int)$comment->getId());
		if ($target === null) {
			throw new SendException('message', Http::STATUS_NOT_FOUND);
		}
		if ($target->getSender() !== $account->getMxid()) {
			throw new SendException('permission', Http::STATUS_FORBIDDEN);
		}

		$newContent = $this->getTextContent($message);
		$content = $newContent;
		$content['body'] = '* ' . $newContent['body'];
		if (isset($newContent['formatted_body'])) {
			$content['formatted_body'] = '* ' . $newContent['formatted_body'];
		}
		$content['m.new_content'] = $newContent;
		$content['m.relates_to'] = ['rel_type' => 'm.replace', 'event_id' => $target->getEventId()];

		$eventId = $this->send($account, fn (Client $client, string $transactionId): string => $client->sendEvent($matrixRoom->getMatrixRoomId(), 'm.room.message', $content, $transactionId));
		$this->claimEvent($matrixRoom, $account, $eventId, 'm.room.message');
		return $this->chatManager->editMessage($room, $comment, $participant, $this->timeFactory->getDateTime(), $message);
	}

	/**
	 * Own messages can always be deleted, messages of others with the redact power level
	 *
	 * @return IComment The system message about the deletion
	 * @throws SendException
	 */
	public function deleteMessage(Room $room, Participant $participant, IComment $comment): IComment {
		[$matrixRoom, $account] = $this->prepare($room, $participant);
		$target = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), (int)$comment->getId());
		if ($target === null) {
			throw new SendException('message', Http::STATUS_NOT_FOUND);
		}
		if ($target->getSender() !== $account->getMxid() && !$matrixRoom->getPowerLevelsModel()->canRedact($account->getMxid())) {
			throw new SendException('permission', Http::STATUS_FORBIDDEN);
		}

		$eventId = $this->send($account, fn (Client $client, string $transactionId): string => $client->redact($matrixRoom->getMatrixRoomId(), $target->getEventId(), $transactionId));
		$this->claimEvent($matrixRoom, $account, $eventId, 'm.room.redaction');
		return $this->chatManager->deleteMessage($room, $comment, $participant, $this->timeFactory->getDateTime());
	}

	/**
	 * @throws SendException
	 * @throws ReactionAlreadyExistsException
	 * @throws NotFoundException
	 */
	public function addReaction(Room $room, Participant $participant, int $messageId, string $reaction): IComment {
		[$matrixRoom, $account] = $this->prepare($room, $participant);
		$target = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), $messageId);
		if ($target === null) {
			throw new NotFoundException();
		}
		try {
			$this->commentsManager->getReactionComment($messageId, Attendee::ACTOR_USERS, $account->getUserId(), $reaction);
			throw new ReactionAlreadyExistsException();
		} catch (NotFoundException) {
		}

		$content = ['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => $target->getEventId(), 'key' => $reaction]];
		$eventId = $this->send($account, fn (Client $client, string $transactionId): string => $client->sendEvent($matrixRoom->getMatrixRoomId(), 'm.reaction', $content, $transactionId));
		$eventMap = $this->claimEvent($matrixRoom, $account, $eventId, 'm.reaction');
		if ($eventMap === null) {
			return $this->getSyncedComment($matrixRoom, $eventId);
		}

		$attendee = $participant->getAttendee();
		$comment = $this->reactionManager->addReactionMessage($room, Attendee::ACTOR_USERS, $account->getUserId(), $attendee->getDisplayName(), $messageId, $reaction);
		$eventMap->setCommentId((int)$comment->getId());
		$this->eventMapMapper->update($eventMap);
		return $comment;
	}

	/**
	 * @throws SendException
	 * @throws NotFoundException
	 */
	public function removeReaction(Room $room, Participant $participant, int $messageId, string $reaction): void {
		[$matrixRoom, $account] = $this->prepare($room, $participant);
		$comment = $this->commentsManager->getReactionComment($messageId, Attendee::ACTOR_USERS, $account->getUserId(), $reaction);
		$target = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), (int)$comment->getId());
		if ($target !== null) {
			$eventId = $this->send($account, fn (Client $client, string $transactionId): string => $client->redact($matrixRoom->getMatrixRoomId(), $target->getEventId(), $transactionId));
			$this->claimEvent($matrixRoom, $account, $eventId, 'm.room.redaction');
		}

		$attendee = $participant->getAttendee();
		$this->reactionManager->deleteReactionMessage($room, Attendee::ACTOR_USERS, $account->getUserId(), $attendee->getDisplayName(), $messageId, $reaction);
	}

	/**
	 * Mark the message as read in the Matrix room as well, failures are ignored
	 */
	public function sendReadMarker(Room $room, Participant $participant, int $messageId): void {
		try {
			[$matrixRoom, $account] = $this->prepare($room, $participant);
		} catch (SendException) {
			return;
		}

		$cacheKey = $account->getId() . '/' . $matrixRoom->getId();
		$sent = $this->readMarkerCache->get($cacheKey);
		if (is_int($sent) && $sent >= $messageId) {
			return;
		}

		$target = $this->eventMapMapper->findByCommentId((string)$matrixRoom->getId(), $messageId);
		if ($target === null) {
			return;
		}
		try {
			$this->accountService->getClient($account, 5)->setReadMarker($matrixRoom->getMatrixRoomId(), $target->getEventId());
			$this->readMarkerCache->set($cacheKey, $messageId, 3600);
		} catch (MatrixException|DoesNotExistException) {
		}
	}

	/**
	 * Talk Markdown as Matrix message, mentions of users with a linked
	 * account become mentions on Matrix and @all mentions the room
	 *
	 * @return array<string, mixed>
	 */
	public function getTextContent(string $message): array {
		$mentions = [];
		$mentionRoom = false;
		$resolve = function (string $token) use (&$mentions, &$mentionRoom): ?array {
			$userId = trim(substr($token, 1), '"');
			if ($userId === 'all') {
				$mentionRoom = true;
				return ['name' => '@room'];
			}
			try {
				$account = $this->accountMapper->getByUserId($userId);
			} catch (DoesNotExistException) {
				return null;
			}
			$mentions[] = $account->getMxid();
			return ['mxid' => $account->getMxid(), 'name' => $this->userManager->getDisplayName($userId) ?? $userId];
		};

		$converter = new MarkdownToHtml();
		$converter->setInlineHook(static function (string $token) use ($resolve): ?string {
			$mention = $resolve($token);
			if ($mention === null || !isset($mention['mxid'])) {
				return $mention['name'] ?? null;
			}
			return '<a href="https://matrix.to/#/' . rawurlencode($mention['mxid']) . '">' . htmlspecialchars($mention['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</a>';
		});
		$html = $converter->convert($message);

		$body = preg_replace_callback('/(?<=^|[\s(>])@(?:"[^"]+"|[\w.@:\-]+)/u', static fn (array $match): string => $resolve($match[0])['name'] ?? $match[0], $message) ?? $message;

		$content = ['msgtype' => 'm.text', 'body' => $body];
		if ($html !== null) {
			$content['format'] = 'org.matrix.custom.html';
			$content['formatted_body'] = $html;
		}
		$content['m.mentions'] = array_filter([
			'user_ids' => array_values(array_unique($mentions)),
			'room' => $mentionRoom,
		]) ?: new \stdClass();
		return $content;
	}

	/**
	 * @return array{0: MatrixRoom, 1: Account}
	 * @throws SendException
	 */
	protected function prepare(Room $room, Participant $participant): array {
		try {
			$matrixRoom = $this->roomMapper->getById($room->getObjectId());
		} catch (DoesNotExistException) {
			throw new SendException('message', Http::STATUS_NOT_FOUND);
		}
		if ($matrixRoom->getEncrypted()) {
			throw new SendException('encrypted', Http::STATUS_BAD_REQUEST);
		}

		$attendee = $participant->getAttendee();
		$account = $attendee->getActorType() === Attendee::ACTOR_USERS ? $this->accountService->getForUser($attendee->getActorId()) : null;
		if ($account === null || $account->getStatus() !== Account::STATUS_ACTIVE) {
			throw new SendException('account', Http::STATUS_FORBIDDEN);
		}
		return [$matrixRoom, $account];
	}

	/**
	 * @param \Closure(Client, string): string $request Gets the client and a new transaction id, returns the event id
	 * @throws SendException
	 */
	protected function send(Account $account, \Closure $request): string {
		try {
			return $request($this->accountService->getClient($account, 20), 'nc' . $this->secureRandom->generate(24, ISecureRandom::CHAR_ALPHANUMERIC));
		} catch (UnknownTokenException $e) {
			$this->accountService->markTokenInvalid($account, $e->getMessage());
			throw new SendException('account', Http::STATUS_FORBIDDEN, $e);
		} catch (ForbiddenException $e) {
			throw new SendException('permission', Http::STATUS_FORBIDDEN, $e);
		} catch (MatrixException|DoesNotExistException $e) {
			throw new SendException('matrix', Http::STATUS_BAD_GATEWAY, $e);
		}
	}

	/**
	 * Record the sent event, so the sync does not mirror it again
	 *
	 * @return EventMap|null Null when the sync was faster and already mirrored the event
	 */
	protected function claimEvent(MatrixRoom $matrixRoom, Account $account, string $eventId, string $type): ?EventMap {
		$eventMap = new EventMap();
		$eventMap->setMatrixRoomId((string)$matrixRoom->getId());
		$eventMap->setEventId($eventId);
		$eventMap->setEventType($type);
		$eventMap->setSender($account->getMxid());
		return $this->eventMapMapper->insertIfNew($eventMap) ? $eventMap : null;
	}

	/**
	 * @throws SendException
	 */
	protected function getSyncedComment(MatrixRoom $matrixRoom, string $eventId): IComment {
		$commentId = $this->eventMapMapper->findByEventId((string)$matrixRoom->getId(), $eventId)?->getCommentId();
		try {
			return $this->commentsManager->get((string)$commentId);
		} catch (NotFoundException $e) {
			throw new SendException('matrix', Http::STATUS_BAD_GATEWAY, $e);
		}
	}
}
