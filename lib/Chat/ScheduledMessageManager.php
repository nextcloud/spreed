<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Exceptions\MessagingNotAllowedException;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\ScheduledMessageMapper;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\IComment;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class ScheduledMessageManager {
	public const MAX_CHAT_LENGTH = 32000;

	public function __construct(
		private IEventDispatcher $dispatcher,
		private ScheduledMessageMapper $scheduledMessageMapper,
		private INotificationManager $notificationManager,
		private IManager $shareManager,
		private RoomShareProvider $shareProvider,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private PollService $pollService,
		protected ThreadService $threadService,
		private Notifier $notifier,
		protected ITimeFactory $timeFactory,
		protected AttachmentService $attachmentService,
		protected IReferenceManager $referenceManager,
		protected ILimiter $rateLimiter,
		protected IRequest $request,
		protected IJobList $jobList,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
	}

	public function scheduleMessage(
		Room $chat,
		Participant $participant,
		string $message,
		string $messageType,
		?IComment $parent,
		int $threadId,
		\DateTime $sendAt,
		bool $silent,
	): ScheduledMessage {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post scheduled message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		$scheduledMessage = new ScheduledMessage();
		$scheduledMessage->setRoomToken($chat->getToken());
		$scheduledMessage->setActorId($participant->getAttendee()->getActorId());
		$scheduledMessage->setActorType($participant->getAttendee()->getActorType());
		$scheduledMessage->setSendAt($sendAt);
		$scheduledMessage->setMessage($message);
		$scheduledMessage->setMessageType($messageType);
		$scheduledMessage->setThreadId($threadId);

		if ($parent instanceof IComment) {
			$scheduledMessage->setParentId((int)$parent->getId());
			$threadId = (int)$parent->getTopmostParentId() ?: (int)$parent->getId();
			$threadId = $this->threadService->validateThread($chat->getId(), $threadId) ? $threadId : Thread::THREAD_NONE;
			$scheduledMessage->setThreadId($threadId);
		} elseif ($threadId !== Thread::THREAD_NONE && $threadId !== Thread::THREAD_CREATE) {
			$scheduledMessage->setParentId($threadId);
		}

		$metadata = [];
		if ($silent) {
			$metadata[Message::METADATA_SILENT] = true;
		}
		if ($chat->getMentionPermissions() === Room::MENTION_PERMISSIONS_EVERYONE || $participant?->hasModeratorPermissions()) {
			$metadata[Message::METADATA_CAN_MENTION_ALL] = true;
		}
		if ($threadId !== Thread::THREAD_NONE) {
			$metadata[Message::METADATA_THREAD_ID] = $threadId;
		}
		$scheduledMessage->setMetaData($metadata);

		try {
			$this->scheduledMessageMapper->insert($scheduledMessage);
		} catch (Exception $e) {
		}

		return $scheduledMessage;
	}

	/**
	 * @param Room $chat
	 * @param int $id
	 * @param Participant $participant
	 * @return int
	 */
	public function deleteMessage(Room $chat, int $id, Participant $participant): int {
		return $this->scheduledMessageMapper->deleteMessage($chat, $id, $participant->getAttendee()->getActorId());
	}

	/**
	 * @param Room $chat
	 * @param int $id
	 * @param Participant $participant
	 * @param string $text
	 * @param bool $isSilent
	 * @return ScheduledMessage
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function editMessage(Room $chat, int $id, Participant $participant, string $text, bool $isSilent): ScheduledMessage {
		if (trim($text) === '') {
			throw new \InvalidArgumentException('message');
		}

		try {
			$message = $this->scheduledMessageMapper->findById($chat, $id);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Attempt to edit scheduled message failed, message could not be found', ['exception' => $e]);
			throw $e;
		}

		$metaData = $message->getMetaData();
		$metaData[Message::METADATA_LAST_EDITED_TIME] = $this->timeFactory->getTime();
		$metaData[Message::METADATA_SILENT] = $isSilent;
		$message->setMetaData($metaData);
		$message->setMessage($text, self::MAX_CHAT_LENGTH);
		$this->scheduledMessageMapper->update($message);

		$this->referenceManager->invalidateCache($chat->getToken());

		return $message;
	}

	public function deleteMessages(Room $chat, Participant $participant): void {
		$this->scheduledMessageMapper->deleteMessagesForActor($chat, $participant->getAttendee()->getActorId());
	}

	/**
	 * @return list<ScheduledMessage>
	 */
	public function getMessages(Room $chat, Participant $participant): array {
		return $this->scheduledMessageMapper->findByRoomAndActor($chat, $participant->getAttendee()->getActorId());
	}

	public function getMessage(Room $chat, int $id, Participant $participant): ScheduledMessage {
		return $this->scheduledMessageMapper->findById($chat, $id, $participant->getAttendee()->getActorId());
	}

	public function parseScheduledMessage(string $message, ?Message $parentMessage) {
	}
}
