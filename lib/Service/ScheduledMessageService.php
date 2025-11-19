<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\MessagingNotAllowedException;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\ScheduledMessageMapper;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\IComment;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Security\RateLimiting\ILimiter;
use Psr\Log\LoggerInterface;

class ScheduledMessageService {
	public const MAX_CHAT_LENGTH = 32000;

	public function __construct(
		private ScheduledMessageMapper $scheduledMessageMapper,
		protected ThreadService $threadService,
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
		array $metadata = [],
	): ScheduledMessage {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post scheduled message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		$scheduledMessage = new ScheduledMessage();
		$scheduledMessage->setRoomId($chat->getId());
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
		}

		$metadata[Message::METADATA_THREAD_ID] = $threadId;
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
		return $this->scheduledMessageMapper->deleteById(
			$chat,
			$id,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);
	}

	/**
	 * @param Room $chat
	 * @param int $id
	 * @param Participant $participant
	 * @param string $text
	 * @param bool $isSilent
	 * @param \DateTime $sendAt
	 * @return ScheduledMessage
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function editMessage(Room $chat, int $id, Participant $participant, string $text, bool $isSilent, \DateTime $sendAt): ScheduledMessage {
		if ($chat->isFederatedConversation()) {
			$e = new MessagingNotAllowedException();
			$this->logger->error('Attempt to post scheduled message into proxy conversation', ['exception' => $e]);
			throw $e;
		}

		if (trim($text) === '') {
			throw new \InvalidArgumentException('message');
		}

		try {
			$message = $this->scheduledMessageMapper->findById(
				$chat,
				$id,
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId()
			);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Attempt to edit scheduled message failed, message could not be found', ['exception' => $e]);
			throw $e;
		}

		$metaData = $message->getMetaData();
		$metaData[Message::METADATA_LAST_EDITED_TIME] = $this->timeFactory->getTime();
		$metaData[Message::METADATA_SILENT] = $isSilent;
		$message->setMetaData($metaData);
		$message->setMessage($text);
		$message->setSendAt($sendAt);
		$this->scheduledMessageMapper->update($message);

		return $message;
	}

	public function deleteByActor(string $actorType, string $actorId): void {
		$this->scheduledMessageMapper->deleteByActor($actorType, $actorId);
	}

	/**
	 * @return array<int, ScheduledMessage>
	 */
	public function getMessages(Room $chat, Participant $participant): array {
		return $this->scheduledMessageMapper->findByRoomAndActor(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);
	}

	public function getMessage(Room $chat, int $id, Participant $participant): ScheduledMessage {
		return $this->scheduledMessageMapper->findById(
			$chat,
			$id,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);
	}

	public function parseScheduledMessage(ScheduledMessage $message, ?Message $parentMessage): array {
		$threadId = $message->getThreadId();
		if ($threadId !== Thread::THREAD_NONE && $threadId !== Thread::THREAD_CREATE) {
			try {
				$thread = $this->threadService->findByThreadId(
					$message->getRoomId(),
					$threadId
				);
			} catch (DoesNotExistException $e) {
				$this->logger->warning("Could not find thread $threadId for scheduled message", ['exception' => $e]);
				$thread = null;
			}
		}
		return $message->toArray($parentMessage, $thread ?? null);
	}

	public function getScheduledMessageCount(Room $chat, Participant $participant): int {
		return $this->scheduledMessageMapper->getCountByActorAndRoom(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
		);
	}
}
