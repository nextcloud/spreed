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
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\ILogger;

/**
 * Class RemoveEmptyRooms
 *
 * @package OCA\Spreed\BackgroundJob
 */
class RemoveEmptyRooms extends TimedJob {

	/** @var Manager */
	protected $manager;

	/** @var ILogger */
	protected $logger;

	protected $numDeletedRooms = 0;

	public function __construct(Manager $manager, ILogger $logger) {
		// Every 5 minutes
		$this->setInterval(60 * 5);

		$this->manager = $manager;
		$this->logger = $logger;
	}

	protected function run($argument) {
		$this->manager->forAllRooms([$this, 'callback']);

		if ($this->numDeletedRooms) {
			$this->logger->info('Deleted {numDeletedRooms} rooms because they were empty', [
				'numDeletedRooms' => $this->numDeletedRooms,
				'app' => 'spreed',
			]);
		}
	}

	public function callback(Room $room) {
		if ($room->getType() === Room::ONE_TO_ONE_CALL && $room->getNumberOfParticipants(false) <= 1) {
			$room->deleteRoom();
			$this->numDeletedRooms++;
		} else if ($room->getNumberOfParticipants(false) === 0 && $room->getObjectType() !== 'file') {
			$room->deleteRoom();
			$this->numDeletedRooms++;
		}
	}
}
