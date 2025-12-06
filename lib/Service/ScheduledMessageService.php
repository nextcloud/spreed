<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\ScheduledMessageMapper;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\MessageTooLongException;
use OCP\DB\Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkScheduledMessage from ResponseDefinitions
 */
class ScheduledMessageService {
	public function __construct(
		private readonly ScheduledMessageMapper $scheduledMessageMapper,
		protected ThreadService $threadService,
		protected ITimeFactory $timeFactory,
		protected IL10N $l,
		protected LoggerInterface $logger,
		protected CommentsManager $commentsManager,
		protected MessageParser $messageParser,
	) {
	}

	/**
	 * @throws MessageTooLongException When the message is too long (~32k characters)
	 */
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
		$scheduledMessage = new ScheduledMessage();
		$scheduledMessage->setRoomId($chat->getId());
		$scheduledMessage->setActorId($participant->getAttendee()->getActorId());
		$scheduledMessage->setActorType($participant->getAttendee()->getActorType());
		$scheduledMessage->setSendAt($sendAt);
		$scheduledMessage->setMessage($message);
		$scheduledMessage->setMessageType($messageType);
		if ($parent instanceof IComment) {
			$scheduledMessage->setParentId((int)$parent->getId());
		}
		$scheduledMessage->setThreadId($threadId);
		$scheduledMessage->setMetaData($metadata);
		$scheduledMessage->setCreatedAt($this->timeFactory->getDateTime());

		$this->scheduledMessageMapper->insert($scheduledMessage);

		return $scheduledMessage;
	}

	public function deleteMessage(Room $chat, int $id, Participant $participant): int {
		return $this->scheduledMessageMapper->deleteById(
			$chat,
			$id,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MessageTooLongException
	 * @throws \InvalidArgumentException
	 */
	public function editMessage(
		Room $chat,
		int $id,
		Participant $participant,
		string $text,
		bool $isSilent,
		\DateTime $sendAt,
		string $threadTitle = '',
	): ScheduledMessage {
		$message = $this->scheduledMessageMapper->findById(
			$chat,
			$id,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);

		$metaData = $message->getDecodedMetaData();
		if ($metaData[ScheduledMessage::METADATA_THREAD_ID] !== Thread::THREAD_CREATE && $threadTitle !== '') {
			throw new \InvalidArgumentException('thread-title');
		}

		if ($metaData[ScheduledMessage::METADATA_THREAD_ID] === Thread::THREAD_CREATE && $threadTitle !== '') {
			$metaData[ScheduledMessage::METADATA_THREAD_TITLE] = $threadTitle;
		}

		$metaData[ScheduledMessage::METADATA_LAST_EDITED_TIME] = $this->timeFactory->getTime();
		$metaData[ScheduledMessage::METADATA_SILENT] = $isSilent;
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
	 * @return list<TalkScheduledMessage>
	 */
	public function getMessages(Room $chat, Participant $participant, string $format): array {
		$result = $this->scheduledMessageMapper->findByRoomAndActor(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId()
		);

		$commentIds = array_filter(array_map(static function (array $result) {
			return $result['parent_id'];
		}, $result));
		try {
			$comments = $this->commentsManager->getCommentsById($commentIds);
		} catch (Exception) {
			$comments = [];
		}

		$messages = [];
		foreach ($result as $row) {
			$parent = $thread = null;
			$entity = [];
			foreach ($row as $field => $value) {
				if (str_starts_with($field, 'th_')) {
					$thread[substr($field, 3)] = $value;
					continue;
				}
				$entity[$field] = $value;
			}

			$scheduleMessage = ScheduledMessage::fromRow($entity);
			if ($entity['parent_id'] !== null && isset($comments[$entity['parent_id']])) {
				$parent = $this->messageParser->createMessage($chat, $participant, $comments[$entity['parent_id']], $this->l);
				$this->messageParser->parseMessage($parent);
			}
			if (in_array($thread['id'], [null, Thread::THREAD_NONE, Thread::THREAD_CREATE], true)) {
				$thread = null;
			} else {
				$thread = Thread::fromRow($thread);
			}
			$messages[] = $this->parseScheduledMessage($format, $scheduleMessage, $parent, $thread);
		}

		return $messages;
	}

	public function parseScheduledMessage(string $format, ScheduledMessage $message, ?Message $parentMessage, ?Thread $thread = null): array {
		if ($thread === null
			&& $message->getThreadId() !== Thread::THREAD_NONE
			&& $message->getThreadId() !== Thread::THREAD_CREATE
		) {
			try {
				$thread = $this->threadService->findByThreadId(
					$message->getRoomId(),
					$message->getThreadId(),
				);
			} catch (DoesNotExistException $e) {
				$this->logger->warning('Could not find thread ' . (string)$message->getThreadId() . ' for scheduled message', ['exception' => $e]);
				$thread = null;
			}
		}
		return $message->toArray($format, $parentMessage, $thread ?? null);
	}

	public function getScheduledMessageCount(Room $chat, Participant $participant): int {
		return $this->scheduledMessageMapper->getCountByActorAndRoom(
			$chat,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
		);
	}
}
