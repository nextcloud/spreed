<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\MatrixSendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Matrix\Sync\BackfillService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\L10N\IFactory;

/**
 * Chat operations on Matrix conversations, called from the public
 * ChatController the same way the federation proxy is.
 */
class ChatController {
	public function __construct(
		private readonly SendService $sendService,
		private readonly SyncService $syncService,
		private readonly BackfillService $backfillService,
		private readonly AccountService $accountService,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly EventMapMapper $eventMapMapper,
		private readonly ChatManager $chatManager,
		private readonly MessageParser $messageParser,
		private readonly IFactory $l10nFactory,
		private readonly \OCA\Talk\Service\ThreadService $threadService,
	) {
	}

	/**
	 * Send to Matrix first, mirror locally afterwards; return the message like the normal path does.
	 *
	 * @return array{comment: IComment, parentMessage: ?\OCA\Talk\Model\Message}|DataResponse
	 */
	public function sendMessage(Room $room, Participant $participant, string $message, string $referenceId, int $replyTo, bool $silent, int $threadId = 0, string $threadTitle = ''): array|DataResponse {
		if (trim($message) === '') {
			return new DataResponse(['error' => 'message'], Http::STATUS_BAD_REQUEST);
		}
		$parent = null;
		$parentMessage = null;
		if ($replyTo !== 0) {
			try {
				$parent = $this->chatManager->getParentComment($room, (string)$replyTo);
			} catch (NotFoundException) {
				return new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);
			}
			$parentMessage = $this->messageParser->createMessage($room, $participant, $parent, $this->l10nFactory->get('spreed'));
			$this->messageParser->parseMessage($parentMessage);
			if (!$parentMessage->isReplyable()) {
				return new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);
			}
		}

		if ($parent !== null && $threadId === 0) {
			// Replying inside a Matrix thread keeps the message in that thread
			$threadId = (int)$parent->getTopmostParentId() ?: 0;
			if ($threadId !== 0 && !$this->threadService->validateThread($room->getId(), $threadId)) {
				$threadId = 0;
			}
		}
		$createThread = $replyTo === 0 && $threadId === 0 && $threadTitle !== '';
		try {
			$comment = $this->sendService->sendMessage($room, $participant, $message, $parent, $referenceId, $silent, $createThread ? \OCA\Talk\Model\Thread::THREAD_CREATE : $threadId, $threadTitle);
			if ($createThread) {
				$thread = $this->threadService->createThread($room, (int)$comment->getId(), $threadTitle);
				$this->threadService->setNotificationLevel($participant->getAttendee(), $thread->getId(), Participant::NOTIFY_DEFAULT);
			}
		} catch (MatrixSendException $e) {
			return new DataResponse(['error' => $e->getError()], $e->getStatus());
		} catch (\OCA\Talk\Exceptions\MessageTooLongException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		}
		return ['comment' => $comment, 'parentMessage' => $parentMessage];
	}

	public function editMessage(Room $room, Participant $participant, IComment $comment, string $message): IComment|DataResponse {
		try {
			return $this->sendService->editMessage($room, $participant, $comment, $message);
		} catch (MatrixSendException $e) {
			return new DataResponse(['error' => $e->getError()], $e->getStatus());
		} catch (\OCA\Talk\Exceptions\MessageTooLongException) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		}
	}

	public function deleteMessage(Room $room, Participant $participant, IComment $comment): IComment|DataResponse {
		try {
			return $this->sendService->deleteMessage($room, $participant, $comment);
		} catch (MatrixSendException $e) {
			return new DataResponse(['error' => $e->getError()], $e->getStatus());
		}
	}

	/** @return int HTTP status (201 created, 200 existed) or a DataResponse on error */
	public function react(Room $room, Participant $participant, int $messageId, string $reaction): int|DataResponse {
		try {
			$this->sendService->addReaction($room, $participant, $messageId, $reaction);
			return Http::STATUS_CREATED;
		} catch (\OCA\Talk\Exceptions\ReactionAlreadyExistsException) {
			return Http::STATUS_OK;
		} catch (MatrixSendException $e) {
			return new DataResponse(null, $e->getStatus());
		} catch (\OCP\Comments\NotFoundException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(null, Http::STATUS_BAD_REQUEST);
		}
	}

	public function deleteReaction(Room $room, Participant $participant, int $messageId, string $reaction): ?DataResponse {
		try {
			$this->sendService->removeReaction($room, $participant, $messageId, $reaction);
			return null;
		} catch (MatrixSendException $e) {
			return new DataResponse(null, $e->getStatus());
		} catch (\OCP\Comments\NotFoundException) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse(null, Http::STATUS_BAD_REQUEST);
		}
	}

	public function afterReadMarker(Room $room, Participant $participant, int $messageId): void {
		if ($messageId > 0) {
			$this->sendService->sendReadReceipt($room, $participant, $messageId);
		}
	}

	/**
	 * Before the normal receive path runs: opportunistic sync, and backfill
	 * when the client asks for history older than what we have.
	 */
	public function beforeReceiveMessages(Room $room, Participant $participant, int $lookIntoFuture, int $lastKnownMessageId): void {
		$attendee = $participant->getAttendee();
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			return;
		}
		$userId = $attendee->getActorId();
		if ($lookIntoFuture === 1) {
			$this->syncService->foregroundSync($room, $userId);
			return;
		}
		// Scrolling back: is the requested boundary our oldest mirrored message?
		try {
			$matrixRoom = $this->roomMapper->getByRoomId($room->getId());
		} catch (DoesNotExistException) {
			return;
		}
		if ($matrixRoom->getBackfillDone() || $matrixRoom->getPrevBatch() === null) {
			return;
		}
		$account = $this->accountService->getForUser($userId);
		if ($account === null || !$account->isActive()) {
			return;
		}
		$oldestId = $this->eventMapMapper->getOldestCommentId($matrixRoom->getMatrixRoomId());
		if ($lastKnownMessageId === 0 || $oldestId === null || $lastKnownMessageId <= $oldestId) {
			$this->backfillService->loadOlder($account, $matrixRoom, 3);
		}
	}
}
