<?php
declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\PublicShareAuth;

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Custom behaviour for rooms to request the password for a share.
 *
 * The rooms to request the password for a share are temporary, short-lived
 * rooms intended to give the sharer the chance to verify the identity of the
 * sharee before granting her access to the share. They are always created by a
 * guest or user (the sharee) who then waits for the sharer (who will be the
 * owner of the room) to join and provide her the password.
 *
 * These rooms are associated to a "share:password" object, and their custom
 * behaviour is provided by calling the methods of this class as a response to
 * different room events.
 */
class Listener {

	public static function register(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			self::preventExtraUsersFromJoining($room, $event->getArgument('userId'));
		};
		$dispatcher->addListener(Room::class . '::preJoinRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			self::preventExtraGuestsFromJoining($room);
		};
		$dispatcher->addListener(Room::class . '::preJoinRoomGuest', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			self::destroyRoomOnParticipantLeave($room);
		};
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);
		$dispatcher->addListener(Room::class . '::postCleanGuests', $listener);
	}

	/**
	 * Prevents other users from joining if there is already another participant
	 * in the room besides the owner.
	 *
	 * This method should be called before a user joins a room.
	 *
	 * @param Room $room
	 * @param string $userId
	 * @throws \OverflowException
	 */
	public static function preventExtraUsersFromJoining(Room $room, string $userId): void {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		try {
			$participant = $room->getParticipant($userId);
			if ($participant->getParticipantType() === Participant::OWNER) {
				return;
			}
		} catch (ParticipantNotFoundException $e) {
		}

		if ($room->getActiveGuests() > 0 || \count($room->getParticipantUserIds()) > 1) {
			throw new \OverflowException('Only the owner and another participant are allowed in rooms to request the password for a share');
		}
	}

	/**
	 * Prevents other guests from joining if there is already another
	 * participant in the room besides the owner.
	 *
	 * This method should be called before a guest joins a room.
	 *
	 * @param Room $room
	 * @throws \OverflowException
	 */
	public static function preventExtraGuestsFromJoining(Room $room): void {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		if ($room->getActiveGuests() > 0 || \count($room->getParticipantUserIds()) > 1) {
			throw new \OverflowException('Only the owner and another participant are allowed in rooms to request the password for a share');
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
	public static function destroyRoomOnParticipantLeave(Room $room): void {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		$room->deleteRoom();
	}

}
