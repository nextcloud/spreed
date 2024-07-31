<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Room;
use OCA\Talk\Service\ConsentService;
use OCA\Talk\Service\RecordingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\SpeechToText\Events\AbstractTranscriptionEvent;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected RecordingService $recordingService,
		protected ConsentService $consentService,
		protected IRootFolder $rootFolder,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof AbstractTranscriptionEvent) {
			$this->handleTranscriptionEvents($event);
			return;
		}

		match (get_class($event)) {
			RoomDeletedEvent::class => $this->roomDeleted($event),
			CallEndedEvent::class,
			CallEndedForEveryoneEvent::class => $this->endRecordingOnCallEnd($event),
		};
	}

	public function handleTranscriptionEvents(AbstractTranscriptionEvent $event): void {
		if ($event->getAppId() !== Application::APP_ID) {
			return;
		}

		if ($event instanceof TranscriptionSuccessfulEvent) {
			$this->successfulTranscript($event->getUserId(), $event->getFile(), $event->getTranscript());
		} elseif ($event instanceof TranscriptionFailedEvent) {
			$this->failedTranscript($event->getUserId(), $event->getFile());
		}
	}

	protected function successfulTranscript(?string $owner, ?File $fileNode, string $transcript): void {
		if (!$fileNode instanceof File) {
			return;
		}

		if ($owner === null) {
			return;
		}

		$this->recordingService->storeTranscript($owner, $fileNode, $transcript);
	}

	protected function failedTranscript(?string $owner, ?File $fileNode): void {
		if (!$fileNode instanceof File) {
			return;
		}

		if ($owner === null) {
			return;
		}

		$this->recordingService->notifyAboutFailedTranscript($owner, $fileNode);
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
