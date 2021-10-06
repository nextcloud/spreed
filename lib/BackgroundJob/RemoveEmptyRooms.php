<?php

declare(strict_types=1);
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

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Files\Config\IUserMountCache;
use Psr\Log\LoggerInterface;

/**
 * Class RemoveEmptyRooms
 *
 * @package OCA\Talk\BackgroundJob
 */
class RemoveEmptyRooms extends TimedJob {

	/** @var Manager */
	protected $manager;

	/** @var ParticipantService */
	protected $participantService;

	/** @var LoggerInterface */
	protected $logger;

	/** @var IUserMountCache */
	protected $userMountCache;

	protected $numDeletedRooms = 0;

	public function __construct(ITimeFactory $timeFactory,
								Manager $manager,
								ParticipantService $participantService,
								LoggerInterface $logger,
								IUserMountCache $userMountCache) {
		parent::__construct($timeFactory);

		// Every 5 minutes
		$this->setInterval(60 * 5);

		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->logger = $logger;
		$this->userMountCache = $userMountCache;
	}

	protected function run($argument): void {
		$this->manager->forAllRooms([$this, 'callback']);

		if ($this->numDeletedRooms) {
			$this->logger->info('Deleted {numDeletedRooms} rooms because they were empty', [
				'numDeletedRooms' => $this->numDeletedRooms,
			]);
		}
	}

	public function callback(Room $room): void {
		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return;
		}

		if ($this->deleteIfIsEmpty($room)) {
			return;
		}

		$this->deleteIfFileIsRemoved($room);
	}

	private function deleteIfIsEmpty(Room $room): bool {
		if ($room->getObjectType() === 'file') {
			return false;
		}

		if ($this->participantService->getNumberOfActors($room) !== 0) {
			return false;
		}

		$this->doDeleteRoom($room);
		return true;
	}

	private function deleteIfFileIsRemoved(Room $room): bool {
		if ($room->getObjectType() !== 'file') {
			return false;
		}

		$mountsForFile = $this->userMountCache->getMountsForFileId($room->getObjectId());
		if (!empty($mountsForFile)) {
			return false;
		}

		$this->doDeleteRoom($room);
		return true;
	}

	private function doDeleteRoom(Room $room): void {
		$room->deleteRoom();
		$this->numDeletedRooms++;
	}
}
