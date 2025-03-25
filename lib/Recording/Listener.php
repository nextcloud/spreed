<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Events\ARoomEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Room;
use OCA\Talk\Service\ConsentService;
use OCA\Talk\Service\RecordingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\TaskProcessing\Events\AbstractTaskProcessingEvent;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected RecordingService $recordingService,
		protected ConsentService $consentService,
		protected IRootFolder $rootFolder,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AbstractTaskProcessingEvent) {
			try {
				$this->handleTranscriptionEvents($event);
			} catch (\Throwable $e) {
				$this->logger->error('An error occurred while processing recording AI follow-up task', ['exception' => $e]);
			}
			return;
		}

		if ($event instanceof ARoomEvent && $event->getRoom()->isFederatedConversation()) {
			return;
		}

		match (get_class($event)) {
			RoomDeletedEvent::class => $this->roomDeleted($event),
			CallEndedEvent::class,
			CallEndedForEveryoneEvent::class => $this->endRecordingOnCallEnd($event),
		};
	}

	public function handleTranscriptionEvents(AbstractTaskProcessingEvent $event): void {
		$task = $event->getTask();
		if ($task->getAppId() !== Application::APP_ID) {
			return;
		}

		// 'call/transcription/' . $room->getToken()
		$customId = $task->getCustomId();
		if (str_starts_with($customId, 'call/transcription/')) {
			$aiType = 'transcript';
			$roomToken = substr($customId, strlen('call/transcription/'));

			$fileId = (int)($task->getInput()['input'] ?? null);
		} elseif (str_starts_with($customId, 'call/summary/')) {
			$aiType = 'summary';
			[$roomToken, $fileId] = explode('/', substr($customId, strlen('call/summary/')));
			$fileId = (int)$fileId;
		} else {
			return;
		}

		if ($fileId === 0) {
			return;
		}

		if ($task->getUserId() === null) {
			return;
		}

		if ($event instanceof TaskSuccessfulEvent) {
			$this->recordingService->storeTranscript($task->getUserId(), $roomToken, $fileId, $task->getOutput()['output'] ?? '', $aiType);
		} elseif ($event instanceof TaskFailedEvent) {
			$this->recordingService->notifyAboutFailedTranscript($task->getUserId(), $roomToken, $fileId, $aiType);
		}
	}

	protected function roomDeleted(RoomDeletedEvent $event): void {
		$this->consentService->deleteByRoom($event->getRoom());
	}

	protected function endRecordingOnCallEnd(ACallEndedEvent $event): void {
		$callRecording = $event->getRoom()->getCallRecording();
		if ($callRecording !== Room::RECORDING_NONE && $callRecording !== Room::RECORDING_FAILED) {
			$this->recordingService->stop($event->getRoom());
		}
	}
}
