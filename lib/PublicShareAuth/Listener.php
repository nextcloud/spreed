<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\PublicShareAuth;

use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\BeforeAttendeesAddedEvent;
use OCA\Talk\Events\BeforeGuestJoinedRoomEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Events\GuestsCleanedUpEvent;
use OCA\Talk\Events\SessionLeftRoomEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Custom behaviour for rooms to request the password for a share.
 *
 * The rooms to request the password for a share are temporary, short-lived
 * rooms intended to give the sharer the chance to verify the identity of the
 * sharee before granting them access to the share. They are always created by a
 * guest or user (the sharee) who then waits for the sharer (who will be the
 * owner of the room) to join and provide their the password.
 *
 * These rooms are associated to a "share:password" object, and their custom
 * behaviour is provided by calling the methods of this class as a response to
 * different room events.
 *
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected ParticipantService $participantService,
		protected RoomService $roomService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (get_class($event)) {
			BeforeUserJoinedRoomEvent::class => $this->preventExtraUsersFromJoining($event->getRoom(), $event->getUser()->getUID()),
			BeforeGuestJoinedRoomEvent::class => $this->preventExtraGuestsFromJoining($event->getRoom()),
			BeforeAttendeesAddedEvent::class => $this->preventExtraUsersFromBeingAdded($event->getRoom(), $event->getAttendees()),
			AttendeeRemovedEvent::class,
			SessionLeftRoomEvent::class,
			GuestsCleanedUpEvent::class => $this->destroyRoomOnParticipantLeave($event->getRoom()),
		};
	}

	/**
	 * Prevents other users from joining if there is already another participant
	 * in the room besides the owner.
	 *
	 * This method should be called before a user joins a room.
	 *
	 * @param Room $room
	 * @param string $userId
	 * @throws RoomNotFoundException
	 */
	protected function preventExtraUsersFromJoining(Room $room, string $userId): void {
		if ($room->getObjectType() !== Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			return;
		}

		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
			if ($participant->getAttendee()->getParticipantType() === Participant::OWNER) {
				return;
			}
		} catch (ParticipantNotFoundException) {
		}

		if ($this->participantService->getNumberOfActors($room) > 1) {
			throw new RoomNotFoundException('Only the owner and another participant are allowed in rooms to request the password for a share');
		}
	}

	/**
	 * Prevents other guests from joining if there is already another
	 * participant in the room besides the owner.
	 *
	 * This method should be called before a guest joins a room.
	 *
	 * @param Room $room
	 * @throws RoomNotFoundException
	 */
	protected function preventExtraGuestsFromJoining(Room $room): void {
		if ($room->getObjectType() !== Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			return;
		}

		if ($this->participantService->getNumberOfActors($room) > 1) {
			throw new RoomNotFoundException('Only the owner and another participant are allowed in rooms to request the password for a share');
		}
	}

	/**
	 * Prevents other users from being added to the room (as they will not be
	 * able to join).
	 *
	 * This method should be called before a user is added to a room.
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 * @throws RoomNotFoundException
	 */
	protected function preventExtraUsersFromBeingAdded(Room $room, array $attendees): void {
		if ($room->getObjectType() !== Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			return;
		}

		if (empty($attendees)) {
			return;
		}

		// Events with more than one participant can be directly aborted, as
		// when the owner is added during room creation or a user self-joins the
		// event will always have just one participant.
		if (count($attendees) > 1) {
			throw new RoomNotFoundException('Only the owner and another participant are allowed in rooms to request the password for a share');
		}

		$attendee = $attendees[0];
		if ($attendee->getParticipantType() !== Participant::OWNER && $attendee->getParticipantType() !== Participant::USER_SELF_JOINED) {
			throw new RoomNotFoundException('Only the owner and another participant are allowed in rooms to request the password for a share');
		}
	}

	/**
	 * Destroys the PublicShareAuth room as soon as one of the participant
	 * leaves the room.
	 *
	 * This method should be called after a user or guest leaves a room for any
	 * reason (no matter if the user or guest removed herself, was removed or
	 * timed out).
	 *
	 * @param Room $room
	 */
	protected function destroyRoomOnParticipantLeave(Room $room): void {
		if ($room->getObjectType() !== Room::OBJECT_TYPE_VIDEO_VERIFICATION) {
			return;
		}

		$this->roomService->deleteRoom($room);
	}
}
