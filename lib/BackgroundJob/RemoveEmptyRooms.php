<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\Config\IUserMountCache;
use Psr\Log\LoggerInterface;

/**
 * Class RemoveEmptyRooms
 *
 * @package OCA\Talk\BackgroundJob
 */
class RemoveEmptyRooms extends TimedJob {

	protected int $numDeletedRooms = 0;

	public function __construct(
		ITimeFactory $timeFactory,
		protected Manager $manager,
		protected RoomService $roomService,
		protected ParticipantService $participantService,
		protected FederationManager $federationManager,
		protected LoggerInterface $logger,
		protected IUserMountCache $userMountCache,
	) {
		parent::__construct($timeFactory);

		// Every 5 minutes
		$this->setInterval(60 * 5);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);

	}

	#[\Override]
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

		if ($room->isFederatedConversation()
			&& $this->federationManager->getNumberOfInvitations($room) !== 0) {
			return false;
		}

		$this->doDeleteRoom($room);
		return true;
	}

	private function deleteIfFileIsRemoved(Room $room): bool {
		if ($room->getObjectType() !== 'file') {
			return false;
		}

		$mountsForFile = $this->userMountCache->getMountsForFileId((int)$room->getObjectId());
		if (!empty($mountsForFile)) {
			return false;
		}

		$this->doDeleteRoom($room);
		return true;
	}

	private function doDeleteRoom(Room $room): void {
		$this->roomService->deleteRoom($room);
		$this->numDeletedRooms++;
	}
}
