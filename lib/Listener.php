<?php
declare(strict_types=1);
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

class Listener {

	/** @var Manager */
	protected $manager;

	public function __construct(Manager $manager) {
		$this->manager = $manager;
	}

	public static function register(): void {
		\OC::$server->getUserManager()->listen('\OC\User', 'postDelete', function ($user) {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->deleteUser($user);
		});
	}

	/**
	 * @param IUser $user
	 */
	public function deleteUser(IUser $user): void {
		$rooms = $this->manager->getRoomsForParticipant($user->getUID());

		foreach ($rooms as $room) {
			if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
				$room->deleteRoom();
			} else {
				$room->removeUser($user, Room::PARTICIPANT_REMOVED);
			}
		}
	}
}
