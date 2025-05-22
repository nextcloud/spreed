<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ExpireObjectRooms extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		protected Manager $manager,
		protected RoomService $roomService,
		protected LoggerInterface $logger,
		protected IAppConfig $appConfig,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(60 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		$phoneRetention = $this->appConfig->getAppValueInt('retention_phone_rooms', 7);
		if ($phoneRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_PHONE_TEMPORARY, $phoneRetention);
		}

		$eventRetention = $this->appConfig->getAppValueInt('retention_event_rooms', 28);
		if ($eventRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_EVENT, $eventRetention);
		}

		$instantMeetingRetention = $this->appConfig->getAppValueInt('retention_instant_meetings', 1);
		if ($instantMeetingRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_INSTANT_MEETING, $instantMeetingRetention);
		}
	}

	protected function executeRetention(string $objectType, int $retention): void {
		$now = $this->time->getTime();
		$minimumLastActivity = $now - $retention * 24 * 3600;
		$rooms = $this->manager->getExpiringRoomsForObjectType($objectType, $minimumLastActivity);

		$numDeletedRooms = 0;
		foreach ($rooms as $room) {
			if ($objectType === Room::OBJECT_TYPE_EVENT) {
				[, $endTime] = explode('#', $room->getObjectId());
				if ($endTime >= $minimumLastActivity) {
					// Event time is in the future, so don't even consider deleting
					continue;
				}
			}

			$this->roomService->deleteRoom($room);
			$numDeletedRooms++;
		}

		$this->logger->info('Deleted {numDeletedRooms} {objectType} rooms because they did not have activity since {minimumLastActivity} days', [
			'objectType' => $objectType,
			'numDeletedRooms' => $numDeletedRooms,
			'minimumLastActivity' => $retention,
		]);
	}
}
