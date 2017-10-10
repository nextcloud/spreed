<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Activity;

use OCA\Spreed\Room;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;

class Hooks {

	/** @var IManager */
	protected $activityManager;

	/** @var ITimeFactory */
	protected $timeFactory;

	/**
	 * @param IManager $activityManager
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IManager $activityManager, ITimeFactory $timeFactory) {
		$this->activityManager = $activityManager;
		$this->timeFactory = $timeFactory;
	}

	public function setActive(Room $room, $isGuest) {
		$room->setActiveSince(new \DateTime(), $isGuest);
	}

	public function generateActivity(Room $room) {
		$activeSince = $room->getActiveSince();
		if (!$activeSince instanceof \DateTime || $room->hasActiveSessions()) {
			return false;
		}

		$duration = $this->timeFactory->getTime() - $activeSince->getTimestamp();
		$participants = $room->getParticipants($activeSince->getTimestamp());
		$userIds = array_keys($participants['users']);

		if (empty($userIds) || (count($userIds) === 1 && $room->getActiveGuests() === 0)) {
			// Single user pinged or guests only => no activity
			$room->resetActiveSince();
			return false;
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('spreed')
			->setType('spreed')
			->setAuthor('')
			->setObject('room', $room->getId(), $room->getName())
			->setTimestamp(time())
			->setSubject('call', [
				'room' => $room->getId(),
				'users' => $userIds,
				'guests' => $room->getActiveGuests(),
				'duration' => $duration,
			]);

		foreach ($userIds as $userId) {
			$event->setAffectedUser($userId);
			$this->activityManager->publish($event);
		}

		$room->resetActiveSince();
	}

}
