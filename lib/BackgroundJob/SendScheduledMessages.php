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
		if (empty($messages)) {
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
					continue;
				}
			}

			$room = $rooms[$message->getRoomId()];
			try {
				$participant = $this->participantService->getParticipantByActor($room, $message->getActorType(), $message->getActorId());
			} catch (ParticipantNotFoundException) {
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				continue;
			}

			if ($room->isFederatedConversation()) {
				// skip for now
				continue;
			}

			if (($participant->getPermissions() & Attendee::PERMISSIONS_CHAT) === 0) {
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				continue;
			}

			if ($room->getReadOnly()) {
				$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
				continue;
			}

			$parent = $parentMessage = null;
			if ($message->getParentId() !== 0) {
				try {
					$parent = $this->chatManager->getParentComment($room, (string)$message->getParentId());
				} catch (NotFoundException $e) {
					// Log and continue or delete?
					continue;
				}

				$parentMessage = $this->messageParser->createMessage($room, $participant, $parent, $this->l10n);
				$this->messageParser->parseMessage($parentMessage);
				if (!$parentMessage->isReplyable()) {
					// Log and continue or delete?
					continue;
				}
			} elseif ($message->getThreadId() > 0) {
				if (!$this->threadService->validateThread($room->getId(), $message->getThreadId())) {
					$message->setThreadId(0);
				}
			}

			$this->participantService->ensureOneToOneRoomIsFilled($room);

			try {
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
						true
					);
				}
			} catch (MessageTooLongException) {
				continue;
			} catch (\Exception $e) {
				$this->logger->warning($e->getMessage());
				continue;
			}

			$this->scheduledMessageService->deleteMessage($room, (int)$message->getId(), $message->getActorType(), $message->getActorId());
		}
	}
}
