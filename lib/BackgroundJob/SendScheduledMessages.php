<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ScheduledMessage;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ScheduledMessageService;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Class SendScheduledMessages
 *
 * @package OCA\Talk\BackgroundJob
 */
class SendScheduledMessages extends TimedJob {
	private readonly IL10N $l10n;
	public function __construct(
		private ScheduledMessageService $scheduledMessageService,
		private ParticipantService $participantService,
		private Manager $manager,
		private ChatManager $chatManager,
		private MessageParser $messageParser,
		private ThreadService $threadService,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		ITimeFactory $time,
	) {
		// Every minute
		$this->setInterval(60);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID);
		parent::__construct($time);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	protected function run($argument): void {
		$messages = $this->scheduledMessageService->getDue($this->time->getDateTime());
		//		$this->logger->error('Here');
		if (empty($messages)) {
			$this->logger->error('No messages found');
			return;
		}

		$rooms = [];
		/** @var ScheduledMessage $message */
		foreach ($messages as $message) {
			if (!isset($rooms[$message->getRoomId()])) {
				try {
					$rooms[$message->getRoomId()] = $this->manager->getRoomById($message->getRoomId());
				} catch (RoomNotFoundException) {
					// This shouldn't happen
					// What to do, what to do
					$this->logger->error('Room not found: ' . $message->getRoomId());
					continue;
				}
			}

			$room = $rooms[$message->getRoomId()];
			try {
				$participant = $this->participantService->getParticipantByActor($room, $message->getActorType(), $message->getActorId());
			} catch (ParticipantNotFoundException $e) {
				$this->logger->error('Participant not found', ['exception' => $e]);
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				continue;
			}

			if ($room->isFederatedConversation()) {
				$this->logger->error('Federated Convo');
				// skip for now
				continue;
			}

			if (($participant->getPermissions() & Attendee::PERMISSIONS_CHAT) === 0) {
				$this->logger->error('No chat permissions');
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				$hasScheduledMessages = $this->scheduledMessageService->getScheduledMessageCount($room, $participant);
				$this->participantService->setHasScheduledMessages($participant, $hasScheduledMessages !== 0);
				continue;
			}

			if ($room->getReadOnly()) {
				$this->logger->error('Read only room');
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				$hasScheduledMessages = $this->scheduledMessageService->getScheduledMessageCount($room, $participant);
				$this->participantService->setHasScheduledMessages($participant, $hasScheduledMessages !== 0);
				continue;
			}

			$parent = $parentMessage = null;
			if ($message->getParentId() !== 0 && $message->getParentId() !== null) {
				try {
					$parent = $this->chatManager->getParentComment($room, (string)$message->getParentId());
					$parentMessage = $this->messageParser->createMessage($room, $participant, $parent, $this->l10n);
					$this->messageParser->parseMessage($parentMessage);
					if (!$parentMessage->isReplyable()) {
						// Log and continue or delete?
						$this->logger->error('Parent message for scheduled message not replyable');
						continue;
					}
				} catch (NotFoundException $e) {
					// Log and continue or delete?
					$this->logger->error('Parent for scheduled message not found', ['exception' => $e]);
				}
			} elseif ($message->getThreadId() > 0) {
				$this->logger->error('Thread');
				if (!$this->threadService->validateThread($room->getId(), $message->getThreadId())) {
					$this->logger->error('Could not validate thread for scheduled message');
					$message->setThreadId(0);
				}
			}

			$this->participantService->ensureOneToOneRoomIsFilled($room);

			try {
				//				$this->logger->error('Sending');
				$metaData = $message->getDecodedMetaData();
				$threadId = $message->getThreadId();
				$threadTitle = $metaData[ScheduledMessage::METADATA_THREAD_TITLE];
				$comment = $this->chatManager->sendMessage($room,
					$participant,
					$message->getActorType(),
					$message->getActorId(),
					$message->getMessage(),
					$this->time->getDateTime(),
					$parent,
					'',
					$metaData[ScheduledMessage::METADATA_SILENT] ?? false,
					threadId: $threadId);
				if ($threadId === Thread::THREAD_CREATE && $threadTitle !== '') {
					//					$this->logger->error('thread');
					$thread = $this->threadService->createThread($room, (int)$comment->getId(), $threadTitle);
					//					$this->logger->error('Thread created', ['thread' => $thread]);
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
						true
					);
				}
			} catch (MessageTooLongException $e) {
				$this->logger->error('Sending scheduled message failed, message too long', ['exception' => $e]);
				continue;
			} catch (\Exception $e) {
				$this->logger->error('Sending scheduled message failed, general exception', ['exception' => $e]);
				continue;
			}

			$deleted = $this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
			//			$this->logger->error('Deleted: ' . (string)$deleted);
			$hasScheduledMessages = $this->scheduledMessageService->getScheduledMessageCount($room, $participant);
			//			$this->logger->error((string)$hasScheduledMessages);
			$this->participantService->setHasScheduledMessages($participant, $hasScheduledMessages !== 0);
		}
	}
}
