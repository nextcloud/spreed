<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ScheduledMessageService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Class SendScheduledMessages
 *
 * @package OCA\Talk\BackgroundJob
 */
class SendScheduledMessages extends TimedJob {
	public function __construct(
		private readonly ScheduledMessageService $scheduledMessageService,
		private readonly ParticipantService $participantService,
		private readonly Manager $manager,
		private readonly ChatManager $chatManager,
		private readonly MessageParser $messageParser,
		private readonly ThreadService $threadService,
		private readonly IL10n $l10n,
		private readonly LoggerInterface $logger,
		ITimeFactory $time,
	) {
		// Every minute
		$this->setInterval(60);
		parent::__construct($time);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	protected function run($argument): void {
		$time = $this->time->getDateTime('-1 second');
		$messages = $this->scheduledMessageService->getDue($time);
		if (empty($messages)) {
			$this->logger->debug('No messages found');
			return;
		}

		/** @var list<Room> $rooms */
		$rooms = [];
		foreach ($messages as $message) {
			if (!isset($rooms[$message->getRoomId()])) {
				try {
					$rooms[$message->getRoomId()] = $this->manager->getRoomById($message->getRoomId());
				} catch (RoomNotFoundException) {
					$this->logger->warning('Room not found: ' . $message->getRoomId());
					continue;
				}
			}

			$room = $rooms[$message->getRoomId()];
			try {
				$participant = $this->participantService->getParticipantByActor($room, $message->getActorType(), $message->getActorId());
			} catch (ParticipantNotFoundException $e) {
				$this->scheduledMessageService->deleteMessage(
					$room,
					(string)$message->getId(),
					$message->getActorType(),
					$message->getActorId()
				);
				continue;
			}

			if ($room->isFederatedConversation() || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$this->logger->warning('Cannot send scheduled message to conversation of type ' . $room->getType() . ' with id ' . $room->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId() . ', removing scheduled message ' . $message->getId());
				$this->scheduledMessageService->deleteMessage(
					$room,
					(string)$message->getId(),
					$participant->getAttendee()->getActorType(),
					$participant->getAttendee()->getActorId()
				);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			}

			if ($room->getReadOnly() === Room::READ_ONLY) {
				$this->logger->warning('Cannot send scheduled message ' . $message->getId() . ' to read only room ' . $room->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				$this->scheduledMessageService->markAsFailed($message);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			}

			if ($room->getLobbyState() !== Webinary::LOBBY_NONE && ($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE) === 0) {
				$this->logger->warning('User ' . $message->getActorId() . ' has no chat permissions for room ' . $message->getRoomId() . ', could not send scheduled message ' . $message->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				$this->scheduledMessageService->markAsFailed($message);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			}

			if (($participant->getPermissions() & Attendee::PERMISSIONS_CHAT) === 0) {
				$this->logger->warning('User ' . $message->getActorId() . ' has no chat permissions for room ' . $message->getRoomId() . ', could not send scheduled message ' . $message->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				$this->scheduledMessageService->markAsFailed($message);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			}

			$parent = $parentMessage = null;
			if ($message->getParentId() !== 0 && $message->getParentId() !== null) {
				try {
					$parent = $this->chatManager->getParentComment($room, (string)$message->getParentId());
					$parentMessage = $this->messageParser->createMessage($room, $participant, $parent, $this->l10n);
					$this->messageParser->parseMessage($parentMessage);
					if (!$parentMessage->isReplyable()) {
						$parentMessageId = $message->getParentId() ?? 0;
						$this->logger->warning('Parent ' . $parentMessageId . ' in room ' . $message->getRoomId() . ' for scheduled message ' . $message->getId() . ' not replyable for ' . $message->getActorType() . ' ' . $message->getActorId());
						$this->scheduledMessageService->markAsFailed($message);
						$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
						continue;
					}
				} catch (NotFoundException $e) {
					$parentMessageId = $message->getParentId() ?? 0;
					$this->logger->warning('Parent ' . $parentMessageId . ' in room ' . $message->getRoomId() . ' for scheduled message ' . $message->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId() . ' not found', ['exception' => $e]);
					$this->scheduledMessageService->markAsFailed($message);
					$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
					continue;
				}
			} elseif ($message->getThreadId() !== 0 && $message->getThreadId() !== -1) {
				if (!$this->threadService->validateThread($room->getId(), $message->getThreadId())) {
					$message->setThreadId(0);
					$this->logger->warning('Could not validate thread ' . $message->getThreadId() . ' in room ' . $message->getRoomId() . ' for scheduled message ' . $message->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
					$this->scheduledMessageService->markAsFailed($message);
					$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
					continue;
				}
			}

			$this->participantService->ensureOneToOneRoomIsFilled($room);
			try {
				$this->logger->debug('Sending scheduled message ' . $message->getId() . ' to room ' . $message->getRoomId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				$metaData = $message->getDecodedMetaData();
				$threadId = $message->getThreadId();
				$threadTitle = $metaData[ScheduledMessage::METADATA_THREAD_TITLE] ?? null;
				$comment = $this->chatManager->sendMessage(
					$room,
					$participant,
					$message->getActorType(),
					$message->getActorId(),
					$message->getMessage(),
					$this->time->getDateTime(),
					$parent,
					'',
					$metaData[ScheduledMessage::METADATA_SILENT] ?? false,
					threadId: $threadId,
					threadTitle: $threadTitle,
					fromScheduledMessage: true,
				);
				$this->logger->debug('Sent scheduled message ' . $message->getId() . ' to room ' . $message->getRoomId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				if ($threadId === Thread::THREAD_CREATE && $threadTitle !== '') {
					$thread = $this->threadService->createThread($room, (int)$comment->getId(), $threadTitle);
					// Add to subscribed threads list
					$this->threadService->setNotificationLevel($participant->getAttendee(), $thread->getId(), Participant::NOTIFY_DEFAULT);
					$this->chatManager->addSystemMessage(
						$room,
						$participant,
						$participant->getAttendee()->getActorType(),
						$participant->getAttendee()->getActorId(),
						json_encode(['message' => 'thread_created', 'parameters' => ['thread' => (int)$comment->getId(), 'title' => $thread->getName()]]),
						$this->time->getDateTime(),
						false,
						null,
						$comment,
						true,
						true,
					);
					$this->logger->debug('Created thread ' . $thread->getId() . ' in room ' . $message->getRoomId() . ' for scheduled message ' . $message->getId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
				}
			} catch (MessageTooLongException $e) {
				$this->logger->error('Sending scheduled message ' . $message->getId() . ' to room ' . $message->getRoomId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId() . ' failed, message too long', ['exception' => $e]);
				$this->scheduledMessageService->markAsFailed($message);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			} catch (\Exception $e) {
				$this->logger->error('Sending scheduled message ' . $message->getId() . ' to room ' . $message->getRoomId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId() . ' failed, general exception', ['exception' => $e]);
				$this->scheduledMessageService->markAsFailed($message);
				$this->participantService->setHasScheduledMessages($participant, Participant::ERROR_SCHEDULED_MESSAGE);
				continue;
			}

			$this->scheduledMessageService->deleteMessage(
				$room,
				(string)$message->getId(),
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId()
			);

			$this->logger->debug('Deleted scheduled message ' . $message->getId() . ' in room ' . $message->getRoomId() . ' for ' . $message->getActorType() . ' ' . $message->getActorId());
			$hasScheduledMessages = $this->scheduledMessageService->getScheduledMessageCount($room, $participant);
			$this->participantService->setHasScheduledMessages($participant, $hasScheduledMessages);
		}
	}
}
