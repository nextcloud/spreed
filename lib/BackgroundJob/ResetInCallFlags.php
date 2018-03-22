<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Class ResetInCallFlags
 *
 * @package OCA\Spreed\BackgroundJob
 */
class ResetInCallFlags extends TimedJob {

	/** @var Manager */
	protected $manager;

	/** @var int */
	protected $timeout;

	public function __construct(Manager $manager, ITimeFactory $timeFactory) {
		// Every 5 minutes
		$this->setInterval(60 * 5);

		$this->manager = $manager;
		$this->timeout = $timeFactory->getTime() - 5 * 60;
	}


	protected function run($argument) {
		$this->manager->forAllRooms([$this, 'callback']);
	}

	public function callback(Room $room) {
		if (!$room->hasSessionsInCall()) {
			return;
		}

		foreach ($room->getActiveSessions() as $session) {
			try {
				$participant = $room->getParticipantBySession($session);
			} catch (ParticipantNotFoundException $e) {
				// Participant was just deleted, ignore …
				continue;
			}

			if ($participant->getLastPing() < $this->timeout) {
				// TODO reset session too
				if ($participant->isInCall()) {
					$room->changeInCall($session, false);
				}
			}
		}
	}
}
