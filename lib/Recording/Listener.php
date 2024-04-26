<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Recording;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Events\RoomDeletedEvent;
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
		if ($event instanceof RoomDeletedEvent) {
			$this->roomDeleted($event);
			return;
		}

		if (!($event instanceof AbstractTranscriptionEvent)) {
			// Unrelated
			return;
		}

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
}
