<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Mapping;

use Nextcloud\Matrix\Model\Event;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\MissingSessionException;
use Nextcloud\Matrix\Model\Mxc;
use OCA\Talk\Matrix\Service\CryptoService;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Sync\RoomStateApplier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Maps Matrix timeline events onto Talk chat messages. Idempotent: every
 * event id is recorded in talk_matrix_events before anything is written.
 *
 * Phase 1: m.room.message (text, notice, emote, media as file card,
 * location) and replies. Reactions, edits, redactions and threads are
 * recorded but not applied yet (phase 3).
 */
class EventMapper {
	public const RICH_MEDIA = 'matrix-media';
	public const RICH_UNSUPPORTED = 'matrix-unsupported';

	public function __construct(
		private readonly ChatManager $chatManager,
		private readonly ICommentsManager $commentsManager,
		private readonly EventMapMapper $eventMapMapper,
		private readonly Formatter $formatter,
		private readonly RoomStateApplier $stateApplier,
		private readonly CryptoService $cryptoService,
		private readonly \OCA\Talk\Chat\ReactionManager $reactionManager,
		private readonly \OCA\Talk\Service\ThreadService $threadService,
		private readonly \OCA\Talk\Service\ParticipantService $participantService,
		private readonly ITimeFactory $timeFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, Account> $accountsByMxid known linked accounts (sender lookup)
	 * @return int number of new Talk messages created
	 */
	public function ingest(Room $room, MatrixRoom $matrixRoom, Event $event, array $accountsByMxid, bool $silent = false, ?Account $decryptWith = null): int {
		if ($event->isState() || $event->eventId === '') {
			return 0;
		}
		if ($this->eventMapMapper->findByEventId($event->eventId) !== null) {
			return 0; // already mirrored (sync replay, our own echo, or another linked user's sync)
		}

		$map = new EventMap();
		$map->setMatrixRoomId($matrixRoom->getMatrixRoomId());
		$map->setEventId($event->eventId);
		$map->setEventType($event->type);
		$map->setOriginTs($event->originServerTs);
		$map->setSender($event->sender);
		$map->setRelatesTo($event->getRelatedEventId());
		$map->setRelType($event->getRelationType());
		$map->setTxnId($event->getTransactionId());

		if ($event->type === 'm.room.encrypted') {
			$map->setEncrypted(true);
			$map->setSessionId(isset($event->content['session_id']) ? (string)$event->content['session_id'] : null);
			$decrypted = null;
			if ($decryptWith !== null) {
				try {
					$decrypted = $this->cryptoService->decryptRoomEvent($decryptWith, $matrixRoom->getMatrixRoomId(), $event->sender, $event->content);
				} catch (MissingSessionException $e) {
					$this->cryptoService->requestMissingKey($decryptWith, $matrixRoom->getMatrixRoomId(), $e->sessionId, $event->sender);
				} catch (CryptoException $e) {
					$this->logger->info('Matrix event ' . $event->eventId . ' could not be decrypted: ' . $e->getMessage());
					$map->setDecryptState(EventMap::DECRYPT_FAILED);
				}
			}
			if ($decrypted === null) {
				// No key (yet): placeholder now, replaced in place when the key arrives
				if ($map->getDecryptState() !== EventMap::DECRYPT_FAILED) {
					$map->setDecryptState(EventMap::DECRYPT_MISSING_SESSION);
				}
				$map->setCiphertext(json_encode($event->toArray(), JSON_THROW_ON_ERROR));
				$map->setProcessed(false);
				if (!$this->eventMapMapper->insertIfNew($map)) {
					return 0;
				}
				$comment = $this->postMessage($room, $matrixRoom, $event, $accountsByMxid, $this->l->t('🔒 Encrypted message – waiting for the key'), [], null, true);
				$map->setCommentId($comment !== null ? (int)$comment->getId() : null);
				$this->eventMapMapper->update($map);
				return $comment !== null ? 1 : 0;
			}
			$map->setDecryptState(EventMap::DECRYPT_OK);
			$event = new Event($event->eventId, $decrypted['type'], $event->sender, $event->originServerTs, $decrypted['content'], null, $event->unsigned, $event->roomId);
			$map->setEventType($event->type);
			$map->setRelatesTo($event->getRelatedEventId());
			$map->setRelType($event->getRelationType());
		}

		if ($event->type === 'm.reaction') {
			if (!$this->eventMapMapper->insertIfNew($map)) {
				return 0;
			}
			$this->applyReaction($room, $matrixRoom, $event, $accountsByMxid, $map);
			return 0;
		}
		if ($event->type === 'm.room.redaction') {
			if (!$this->eventMapMapper->insertIfNew($map)) {
				return 0;
			}
			$this->applyRedaction($room, $matrixRoom, $event, $accountsByMxid);
			return 0;
		}

		if ($event->type !== 'm.room.message' && $event->type !== 'm.sticker') {
			// Calls, custom events … bookkeeping only
			$map->setProcessed(true);
			$this->eventMapMapper->insertIfNew($map);
			if (str_starts_with($event->type, 'm.call.') || str_starts_with($event->type, 'org.matrix.msc3401.call')) {
				$this->callNotice($room, $event);
			}
			return 0;
		}

		$relationType = $event->getRelationType();
		if ($relationType === 'm.replace') {
			if (!$this->eventMapMapper->insertIfNew($map)) {
				return 0;
			}
			$this->applyEdit($room, $matrixRoom, $event, $accountsByMxid, $map);
			return 0;
		}

		if (!$this->eventMapMapper->insertIfNew($map)) {
			return 0;
		}

		[$message, $parameters] = $this->contentFor($event, $matrixRoom);
		$replyTo = $this->resolveReplyTo($event);
		$threadId = $relationType === 'm.thread' ? $this->resolveThread($room, $event) : 0;
		$comment = $this->postMessage($room, $matrixRoom, $event, $accountsByMxid, $message, $parameters, $replyTo, $silent, $threadId);
		if ($comment !== null) {
			$map->setCommentId((int)$comment->getId());
			$this->eventMapMapper->update($map);
			return 1;
		}
		return 0;
	}

	/**
	 * A key arrived: decrypt the events that were waiting for this session and
	 * replace their placeholders in place (same comment id, no "edited" marker).
	 *
	 * @return int number of messages decrypted
	 */
	public function retryUndecryptable(Room $room, MatrixRoom $matrixRoom, Account $account, string $sessionId): int {
		$count = 0;
		foreach ($this->eventMapMapper->getUndecryptable($matrixRoom->getMatrixRoomId(), $sessionId) as $map) {
			$raw = json_decode((string)$map->getCiphertext(), true);
			if (!is_array($raw)) {
				continue;
			}
			$event = Event::fromArray($raw, $matrixRoom->getMatrixRoomId());
			try {
				$decrypted = $this->cryptoService->decryptRoomEvent($account, $matrixRoom->getMatrixRoomId(), $event->sender, $event->content);
			} catch (CryptoException) {
				continue;
			}
			$plain = new Event($event->eventId, $decrypted['type'], $event->sender, $event->originServerTs, $decrypted['content'], null, $event->unsigned, $event->roomId);
			$map->setDecryptState(EventMap::DECRYPT_OK);
			$map->setCiphertext(null);
			$map->setProcessed(true);
			$map->setEventType($plain->type);
			$map->setRelatesTo($plain->getRelatedEventId());
			$map->setRelType($plain->getRelationType());
			if ($map->getCommentId() !== null && $plain->type === 'm.room.message' && $plain->getRelationType() !== 'm.replace') {
				try {
					$comment = $this->commentsManager->get((string)$map->getCommentId());
					[$message, $parameters] = $this->contentFor($plain, $matrixRoom);
					if ($parameters !== []) {
						$comment->setMessage(json_encode(['message' => 'object_shared', 'parameters' => ['metaData' => $parameters['object']]], JSON_THROW_ON_ERROR));
						$comment->setVerb(ChatManager::VERB_OBJECT_SHARED);
					} else {
						$comment->setMessage($message, ChatManager::MAX_CHAT_LENGTH);
						$comment->setVerb(ChatManager::VERB_MESSAGE);
					}
					$this->commentsManager->save($comment);
					$count++;
				} catch (NotFoundException) {
				}
			}
			$this->eventMapMapper->update($map);
		}
		return $count;
	}

	/**
	 * Record a message that Talk itself sent to Matrix so its echo is skipped.
	 */
	public function recordOutgoing(MatrixRoom $matrixRoom, string $eventId, string $type, IComment $comment, string $txnId, string $sender, ?string $relatesTo = null, ?string $relType = null): void {
		$map = new EventMap();
		$map->setMatrixRoomId($matrixRoom->getMatrixRoomId());
		$map->setEventId($eventId);
		$map->setEventType($type);
		$map->setCommentId((int)$comment->getId());
		$map->setTxnId($txnId);
		$map->setOriginTs($this->timeFactory->getTime() * 1000);
		$map->setSender($sender);
		$map->setRelatesTo($relatesTo);
		$map->setRelType($relType);
		$this->eventMapMapper->insertIfNew($map);
	}

	/**
	 * @return array{0: string, 1: array<string, array<string, string>>} message + rich object parameters
	 */
	private function contentFor(Event $event, MatrixRoom $matrixRoom): array {
		$msgtype = $event->type === 'm.sticker' ? 'm.image' : ($event->getMsgType() ?? 'm.text');
		switch ($msgtype) {
			case 'm.image':
			case 'm.file':
			case 'm.video':
			case 'm.audio':
				return $this->mediaContent($event, $msgtype);
			case 'm.location':
				$geo = (string)($event->content['geo_uri'] ?? '');
				if (preg_match('/^geo:(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $geo, $m)) {
					return ['{object}', ['object' => [
						'type' => 'geo-location',
						'id' => 'geo:' . $m[1] . ',' . $m[2],
						'name' => $event->getBody() !== '' ? $event->getBody() : $this->l->t('Location'),
						'latitude' => $m[1],
						'longitude' => $m[2],
					]]];
				}
				return [$event->getBody(), []];
			default:
				$converted = $this->formatter->incoming($event, $matrixRoom->getMatrixRoomId());
				return [$converted['message'], []];
		}
	}

	/**
	 * Files/images/etc. become a rich object the web renders inline (phase 3)
	 * and other clients render as the file name.
	 * @return array{0: string, 1: array<string, array<string, string>>}
	 */
	private function mediaContent(Event $event, string $msgtype): array {
		$info = is_array($event->content['info'] ?? null) ? $event->content['info'] : [];
		$name = $event->getBody() !== '' ? $event->getBody() : $this->l->t('Attachment');
		$url = (string)($event->content['url'] ?? $event->content['file']['url'] ?? '');
		$object = [
			'type' => self::RICH_MEDIA,
			'id' => $event->eventId,
			'name' => $name,
			'mimetype' => (string)($info['mimetype'] ?? 'application/octet-stream'),
			'size' => (string)(int)($info['size'] ?? 0),
			'msgtype' => $msgtype,
			'mxc' => Mxc::isValid($url) ? $url : '',
			'encrypted' => isset($event->content['file']) ? '1' : '0',
			'width' => (string)(int)($info['w'] ?? 0),
			'height' => (string)(int)($info['h'] ?? 0),
			'preview-available' => in_array($msgtype, ['m.image', 'm.video'], true) ? 'yes' : 'no',
		];
		if ($object['mxc'] !== '') {
			$object['link'] = $this->urlGenerator->linkToRouteAbsolute('spreed.matrixmedia.media', ['eventId' => $event->eventId]);
			if ($msgtype === 'm.image') {
				$object['thumbnail'] = $object['link'] . '?thumbnail=1';
			}
			if (is_string($info['thumbnail_url'] ?? null) && Mxc::isValid($info['thumbnail_url'])) {
				$object['thumbnail-mxc'] = $info['thumbnail_url'];
			}
			if (isset($info['duration'])) {
				$object['duration'] = (string)(int)$info['duration'];
			}
		}
		return ['{object}', ['object' => $object]];
	}

	private function resolveReplyTo(Event $event): ?IComment {
		$parentEventId = $event->getInReplyTo();
		if ($parentEventId === null) {
			return null;
		}
		$parentMap = $this->eventMapMapper->findByEventId($parentEventId);
		if ($parentMap === null || $parentMap->getCommentId() === null) {
			return null;
		}
		try {
			return $this->commentsManager->get((string)$parentMap->getCommentId());
		} catch (NotFoundException) {
			return null;
		}
	}

	/**
	 * @param array<string, Account> $accountsByMxid
	 * @param array<string, array<string, string>> $parameters
	 */
	private function postMessage(Room $room, MatrixRoom $matrixRoom, Event $event, array $accountsByMxid, string $message, array $parameters, ?IComment $replyTo, bool $silent, int $threadId = 0): ?IComment {
		[$actorType, $actorId] = $this->stateApplier->actorFor($event->sender, $accountsByMxid[$event->sender] ?? null);
		$creationDateTime = $this->timeFactory->getDateTime('@' . intdiv(max(0, $event->originServerTs), 1000));
		$creationDateTime->setTimezone(new \DateTimeZone('UTC'));

		try {
			if ($parameters !== []) {
				$comment = $this->chatManager->addSystemMessage(
					$room,
					null,
					$actorType,
					$actorId,
					// Talk's object_shared system message carries the rich object under "metaData"
					json_encode(['message' => 'object_shared', 'parameters' => ['metaData' => $parameters['object']]], JSON_THROW_ON_ERROR),
					$creationDateTime,
					!$silent,
					substr($event->eventId, 0, 64),
					$replyTo,
					false,
					$silent,
					$threadId,
				);
				// object_shared is a system message with verb 'object_shared' in Talk
				return $comment;
			}
			return $this->chatManager->sendMessage(
				$room,
				null,
				$actorType,
				$actorId,
				$message,
				$creationDateTime,
				$replyTo,
				substr($event->eventId, 0, 64),
				$silent,
				false,
				$threadId,
			);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to mirror Matrix event ' . $event->eventId . ' into room ' . $room->getToken() . ': ' . $e->getMessage(), ['exception' => $e]);
			return null;
		}
	}

	/**
	 * m.thread relation → Talk thread id (the root's comment id), creating the Talk thread on first use.
	 */
	private function resolveThread(Room $room, Event $event): int {
		$rootEventId = $event->getRelatedEventId();
		if ($rootEventId === null) {
			return 0;
		}
		$rootMap = $this->eventMapMapper->findByEventId($rootEventId);
		if ($rootMap === null || $rootMap->getCommentId() === null) {
			return 0;
		}
		$rootId = $rootMap->getCommentId();
		try {
			$this->threadService->findByThreadId($room->getId(), $rootId);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			try {
				$this->threadService->createThread($room, $rootId, '');
			} catch (\Throwable $e) {
				$this->logger->info('Could not create Talk thread for Matrix thread ' . $rootEventId . ': ' . $e->getMessage());
				return 0;
			}
		}
		return $rootId;
	}

	/** @param array<string, Account> $accountsByMxid */
	private function applyReaction(Room $room, MatrixRoom $matrixRoom, Event $event, array $accountsByMxid, EventMap $map): void {
		$relation = $event->getRelation();
		$key = (string)($relation['key'] ?? '');
		$targetEventId = $event->getRelatedEventId();
		if ($key === '' || $targetEventId === null || ($relation['rel_type'] ?? '') !== 'm.annotation') {
			return;
		}
		$target = $this->eventMapMapper->findByEventId($targetEventId);
		if ($target === null || $target->getCommentId() === null) {
			return;
		}
		[$actorType, $actorId] = $this->stateApplier->actorFor($event->sender, $accountsByMxid[$event->sender] ?? null);
		try {
			$comment = $this->reactionManager->addReactionMessage($room, $actorType, $actorId, $this->displayName($room, $actorType, $actorId), $target->getCommentId(), $key);
			$map->setCommentId((int)$comment->getId());
			$this->eventMapMapper->update($map);
		} catch (\OCA\Talk\Exceptions\ReactionAlreadyExistsException) {
		} catch (\Throwable $e) {
			$this->logger->info('Could not mirror Matrix reaction ' . $event->eventId . ': ' . $e->getMessage());
		}
	}

	/** @param array<string, Account> $accountsByMxid */
	private function applyRedaction(Room $room, MatrixRoom $matrixRoom, Event $event, array $accountsByMxid): void {
		$targetEventId = (string)($event->content['redacts'] ?? '');
		if ($targetEventId === '') {
			// room v11+: redacts is a top-level key (exposed in unsigned by some servers)
			$targetEventId = (string)($event->unsigned['redacts'] ?? '');
		}
		if ($targetEventId === '') {
			return;
		}
		$target = $this->eventMapMapper->findByEventId($targetEventId);
		if ($target === null || $target->getCommentId() === null) {
			return;
		}
		[$actorType, $actorId] = $this->stateApplier->actorFor($event->sender, $accountsByMxid[$event->sender] ?? null);
		try {
			$comment = $this->commentsManager->get((string)$target->getCommentId());
		} catch (NotFoundException) {
			return;
		}
		try {
			if ($target->getEventType() === 'm.reaction') {
				$this->reactionManager->deleteReactionMessage($room, $comment->getActorType(), $comment->getActorId(), $this->displayName($room, $comment->getActorType(), $comment->getActorId()), (int)$comment->getParentId(), $comment->getMessage());
				return;
			}
			$participant = $this->participantFor($room, $actorType, $actorId);
			if ($participant === null) {
				$this->logger->info('Redaction of ' . $targetEventId . ' by ' . $event->sender . ' skipped: sender is not a participant');
				return;
			}
			$this->chatManager->deleteMessage($room, $comment, $participant, $this->timeFactory->getDateTime('@' . intdiv(max(0, $event->originServerTs), 1000)));
		} catch (\Throwable $e) {
			$this->logger->info('Could not mirror Matrix redaction ' . $event->eventId . ': ' . $e->getMessage());
		}
	}

	/** @param array<string, Account> $accountsByMxid */
	private function applyEdit(Room $room, MatrixRoom $matrixRoom, Event $event, array $accountsByMxid, EventMap $map): void {
		$targetEventId = $event->getRelatedEventId();
		$newContent = is_array($event->content['m.new_content'] ?? null) ? $event->content['m.new_content'] : null;
		if ($targetEventId === null || $newContent === null) {
			return;
		}
		$target = $this->eventMapMapper->findByEventId($targetEventId);
		if ($target === null || $target->getCommentId() === null || $target->getSender() !== $event->sender) {
			return; // only the original sender may edit
		}
		try {
			$comment = $this->commentsManager->get((string)$target->getCommentId());
		} catch (NotFoundException) {
			return;
		}
		[$actorType, $actorId] = $this->stateApplier->actorFor($event->sender, $accountsByMxid[$event->sender] ?? null);
		$participant = $this->participantFor($room, $actorType, $actorId);
		if ($participant === null) {
			return;
		}
		$replacement = new Event($event->eventId, 'm.room.message', $event->sender, $event->originServerTs, $newContent, null, [], $event->roomId);
		$converted = $this->formatter->incoming($replacement, $matrixRoom->getMatrixRoomId());
		try {
			$this->chatManager->editMessage($room, $comment, $participant, $this->timeFactory->getDateTime('@' . intdiv(max(0, $event->originServerTs), 1000)), $converted['message']);
			$map->setProcessed(true);
			$this->eventMapMapper->update($map);
		} catch (\Throwable $e) {
			$this->logger->info('Could not mirror Matrix edit ' . $event->eventId . ': ' . $e->getMessage());
		}
	}

	private function participantFor(Room $room, string $actorType, string $actorId): ?\OCA\Talk\Participant {
		try {
			return $this->participantService->getParticipantByActor($room, $actorType, $actorId);
		} catch (\OCA\Talk\Exceptions\ParticipantNotFoundException) {
			return null;
		}
	}

	private function displayName(Room $room, string $actorType, string $actorId): string {
		$participant = $this->participantFor($room, $actorType, $actorId);
		return $participant?->getAttendee()->getDisplayName() ?: $actorId;
	}

	private function callNotice(Room $room, Event $event): void {
		if ($event->type !== 'm.call.invite' && $event->type !== 'org.matrix.msc3401.call') {
			return;
		}
		try {
			$this->chatManager->addSystemMessage(
				$room,
				null,
				Attendee::ACTOR_GUESTS,
				RoomStateApplier::SYSTEM_ACTOR,
				json_encode(['message' => 'matrix_call_unsupported', 'parameters' => ['sender' => $event->sender]], JSON_THROW_ON_ERROR),
				$this->timeFactory->getDateTime('@' . intdiv(max(0, $event->originServerTs), 1000)),
				false,
				substr($event->eventId, 0, 64),
				null,
				true,
				true,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not add Matrix call notice: ' . $e->getMessage());
		}
	}

}
