<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\NotFoundException;

/**
 * Stops uploads without storage when the moderator providing the Guests folder
 * leaves or is demoted, and revokes upload links that may no longer be used
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
		if ($event instanceof RoomModifiedEvent) {
			$room = $event->getRoom();
			$oldGuestsFolderId = $event->getOldValue();
			if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_GUESTS_FOLDER && is_int($oldGuestsFolderId)) {
				$this->revokeUploadLinks($room, $oldGuestsFolderId);
			}
			// Turning a public conversation into a group conversation removes
			// the guests without an AttendeesRemovedEvent
			if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_TYPE && $event->getOldValue() === Room::TYPE_PUBLIC) {
				$this->revokeUploadLinks($room, $room->getGuestsFolderId());
			}
			return;
		}

		if ($event instanceof RoomDeletedEvent) {
			$this->revokeUploadLinks($event->getRoom(), $event->getRoom()->getGuestsFolderId());
			return;
		}

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
			foreach ($event->getAttendees() as $attendee) {
				$this->uploadsWithoutStorageService->revokeUploadLinks($guestsFolder, $room, $attendee);
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

	private function revokeUploadLinks(Room $room, int $guestsFolderId): void {
		try {
			$guestsFolder = $this->uploadsWithoutStorageService->getGuestsFolderById($guestsFolderId);
		} catch (NotFoundException) {
			return;
		}
		$this->uploadsWithoutStorageService->revokeUploadLinks($guestsFolder, $room);
	}
}
