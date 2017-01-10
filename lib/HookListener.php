<?php
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

namespace OCA\Spreed;

use OCP\IUser;

class HookListener {

	/** @var Manager */
	protected $manager;

	/**
	 * @param Manager $manager
	 */
	public function __construct(Manager $manager) {
		$this->manager = $manager;
	}

	/**
	 * @param IUser $user
	 */
	public function deleteUser(IUser $user) {
		$rooms = $this->manager->getRoomsForParticipant($user->getUID());

		foreach ($rooms as $room) {
			if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
				$room->deleteRoom();
			} else {
				$particiants = $room->getParticipants();

				// Also delete the room, when the user is the only non-guest user
				if (count($particiants['users']) === 1) {
					$room->deleteRoom();
				} else {
					$room->removeUser($user);
				}
			}
		}
	}
}
