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
class Room {

	/**
	 * Destroys the PublicShareAuth room as soon as one of the participant
	 * leaves the room.
	 *
	 * This method should be called after a user or guest leaves a room for any
	 * reason (no matter if the user or guest removed herself, was removed or
	 * timed out).
	 *
	 * @param \OCA\Spreed\Room $room
	 */
	public function destroyRoomOnParticipantLeave(\OCA\Spreed\Room $room) {
		if ($room->getObjectType() !== 'share:password') {
			return;
		}

		$room->deleteRoom();
	}

}
