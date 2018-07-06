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
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;

class Hooks {

	/** @var IManager */
	protected $activityManager;

	/** @var IUserSession */
	protected $userSession;

	/** @var ILogger */
	protected $logger;

	/** @var ITimeFactory */
	protected $timeFactory;

	/**
	 * @param IManager $activityManager
	 * @param IUserSession $userSession
	 * @param ILogger $logger
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IManager $activityManager, IUserSession $userSession, ILogger $logger, ITimeFactory $timeFactory) {
		$this->activityManager = $activityManager;
		$this->userSession = $userSession;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Mark the user as (in)active for a call
	 *
	 * @param Room $room
	 */
	public function setActive(Room $room) {
		$room->setActiveSince(new \DateTime(), !$this->userSession->isLoggedIn());
	}

	/**
	 * Call activity: "You attended a call with {user1} and {user2}"
	 *
	 * @param Room $room
	 * @return bool True if activity was generated, false otherwise
	 */
	public function generateCallActivity(Room $room) {
		$activeSince = $room->getActiveSince();
		if (!$activeSince instanceof \DateTime || $room->hasSessionsInCall()) {
			return false;
		}

		$duration = $this->timeFactory->getTime() - $activeSince->getTimestamp();
		$participants = $room->getParticipants($activeSince->getTimestamp());
		$userIds = array_map('strval', array_keys($participants['users']));

		if (empty($userIds) || (count($userIds) === 1 && $room->getActiveGuests() === 0)) {
			// Single user pinged or guests only => no activity
			$room->resetActiveSince();
			return false;
		}

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor('')
				->setObject('room', $room->getId(), $room->getName())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('call', [
					'room' => $room->getId(),
					'users' => $userIds,
					'guests' => $room->getActiveGuests(),
					'duration' => $duration,
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return false;
		}

		foreach ($userIds as $userId) {
			try {
				$event->setAffectedUser($userId);
				$this->activityManager->publish($event);
			} catch (\BadMethodCallException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			}
		}

		$room->resetActiveSince();
		return true;
	}
}

