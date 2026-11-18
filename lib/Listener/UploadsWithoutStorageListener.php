<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\NotFoundException;

/**
 * Stops uploads without storage when the moderator providing the Guests folder
 * leaves or is demoted
 *
 * @template-implements IEventListener<Event>
 */
class UploadsWithoutStorageListener implements IEventListener {
	public function __construct(
		private readonly RoomService $roomService,
		private readonly UploadsWithoutStorageService $uploadsWithoutStorageService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AttendeesRemovedEvent) {
			$room = $event->getRoom();
			try {
				$guestsFolder = $this->uploadsWithoutStorageService->getGuestsFolder($room);
				$ownerId = $this->uploadsWithoutStorageService->getOwnerId($guestsFolder);
			} catch (NotFoundException) {
				return;
			}

			foreach ($event->getAttendees() as $attendee) {
				if ($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $ownerId) {
					$this->roomService->setGuestsFolderId($room, 0);
					return;
				}
			}
			return;
		}

		if ($event instanceof ParticipantModifiedEvent
			&& $event->getProperty() === AParticipantModifiedEvent::PROPERTY_TYPE
			&& !$event->getParticipant()->hasModeratorPermissions(false)) {
			$room = $event->getRoom();
			$attendee = $event->getParticipant()->getAttendee();
			try {
				$ownerId = $this->uploadsWithoutStorageService->getOwnerId($this->uploadsWithoutStorageService->getGuestsFolder($room));
			} catch (NotFoundException) {
				return;
			}
			if ($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $ownerId) {
				$this->roomService->setGuestsFolderId($room, 0);
			}
		}
	}
}
