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

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	public function __construct(EventDispatcherInterface $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function register() {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->preventExtraUsersFromJoining($room, $event->getArgument('userId'));
		};
		$this->dispatcher->addListener(Room::class . '::preJoinRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->preventExtraGuestsFromJoining($room);
		};
		$this->dispatcher->addListener(Room::class . '::preJoinRoomGuest', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->destroyRoomOnParticipantLeave($room);
		};
		$this->dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$this->dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$this->dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);
		$this->dispatcher->addListener(Room::class . '::postCleanGuests', $listener);
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
	public function preventExtraUsersFromJoining(Room $room, string $userId) {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		$participants = $room->getParticipants();
		$users = $participants['users'];
		$guests = $participants['guests'];

		if (array_key_exists($userId, $users) && $users[$userId]['participantType'] === Participant::OWNER) {
			return;
		}

		if (\count($users) > 1 || \count($guests) > 0) {
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
	public function preventExtraGuestsFromJoining(Room $room) {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		$participants = $room->getParticipants();
		$users = $participants['users'];
		$guests = $participants['guests'];

		if (\count($users) > 1 || \count($guests) > 0) {
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
	public function destroyRoomOnParticipantLeave(Room $room) {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		$room->deleteRoom();
	}

}
