<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Recording;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\RecordingService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\SpeechToText\Events\AbstractTranscriptionEvent;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;

/**
 * @template-implements IEventListener<AbstractTranscriptionEvent>
 */
class Listener implements IEventListener {
	public function __construct(
		protected RecordingService $recordingService,
		protected IRootFolder $rootFolder,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof AbstractTranscriptionEvent)) {
			// Unrelated
			return;
		}

		if ($event instanceof TranscriptionSuccessfulEvent) {
			$this->successfulTranscript($event->getFile(), $event->getTranscript());
		} else if ($event instanceof TranscriptionFailedEvent) {
			$this->failedTranscript($event->getFile());
		}
	}

	protected function successfulTranscript(?File $fileNode, string $transcript): void {
		if (!$fileNode instanceof File) {
			return;
		}

		$owner = $fileNode->getOwner();
		if (!$owner instanceof IUser) {
			return;
		}

		$this->recordingService->storeTranscript($owner->getUID(), $fileNode, $transcript);
	}

	protected function failedTranscript(?File $fileNode): void {
		if (!$fileNode instanceof File) {
			return;
		}

		$owner = $fileNode->getOwner();
		if (!$owner instanceof IUser) {
			return;
		}

		$this->recordingService->notifyAboutFailedTranscript($owner->getUID(), $fileNode);
	}
}
