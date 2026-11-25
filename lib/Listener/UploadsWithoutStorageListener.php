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
			$oldOwner = $event->getOldValue();
			if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_GUESTS_FOLDER && is_string($oldOwner) && $oldOwner !== '') {
				$this->uploadsWithoutStorageService->revokeUploadLinks($room, $oldOwner);
			}
			// Turning a public conversation into a group conversation removes
			// the guests without an AttendeesRemovedEvent
			if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_TYPE && $event->getOldValue() === Room::TYPE_PUBLIC) {
				$this->revokeUploadLinks($room);
			}
			return;
		}

		if ($event instanceof RoomDeletedEvent) {
			$this->revokeUploadLinks($event->getRoom());
			return;
		}

		if ($event instanceof AttendeesRemovedEvent) {
			$room = $event->getRoom();
			$ownerId = $room->getGuestsFolderOwner();
			if ($ownerId === '') {
				return;
			}

			foreach ($event->getAttendees() as $attendee) {
				if ($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $ownerId) {
					$this->roomService->setGuestsFolder($room, '', 0);
					return;
				}
			}
			foreach ($event->getAttendees() as $attendee) {
				$this->uploadsWithoutStorageService->revokeUploadLinks($room, $ownerId, $attendee);
			}
			return;
		}

		if ($event instanceof ParticipantModifiedEvent
			&& $event->getProperty() === AParticipantModifiedEvent::PROPERTY_TYPE
			&& !$event->getParticipant()->hasModeratorPermissions(false)) {
			$room = $event->getRoom();
			$attendee = $event->getParticipant()->getAttendee();
			if ($attendee->getActorType() === Attendee::ACTOR_USERS && $attendee->getActorId() === $room->getGuestsFolderOwner()) {
				$this->roomService->setGuestsFolder($room, '', 0);
			}
		}
	}

	private function revokeUploadLinks(Room $room): void {
		if ($room->getGuestsFolderOwner() !== '') {
			$this->uploadsWithoutStorageService->revokeUploadLinks($room, $room->getGuestsFolderOwner());
		}
	}
}
