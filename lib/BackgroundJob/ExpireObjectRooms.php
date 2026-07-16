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
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly LoggerInterface $logger,
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(60 * 60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		$phoneRetention = $this->appConfig->getAppValueInt('retention_phone_rooms', 7);
		if ($phoneRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_PHONE_TEMPORARY, $phoneRetention * 24 * 3600);
		}

		$eventRetention = $this->appConfig->getAppValueInt('retention_event_rooms', 28);
		if ($eventRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_EVENT, $eventRetention * 24 * 3600);
		}

		$instantMeetingRetention = $this->appConfig->getAppValueInt('retention_instant_meetings', 1);
		if ($instantMeetingRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_INSTANT_MEETING, $instantMeetingRetention * 24 * 3600);
		}

		// Classified conversations are deleted shortly (default 1 hour) after a
		// call happened, unless a moderator kept them (object_type is then
		// "classified_persist" and no longer matched here).
		$classifiedRetention = $this->appConfig->getAppValueInt('retention_classified_rooms', 3600);
		if ($classifiedRetention !== 0) {
			$this->executeRetention(Room::OBJECT_TYPE_CLASSIFIED, $classifiedRetention);
		}
	}

	protected function executeRetention(string $objectType, int $retentionSeconds): void {
		$now = $this->time->getTime();
		$minimumLastActivity = $now - $retentionSeconds;
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

		$this->logger->info('Deleted {numDeletedRooms} {objectType} rooms because they did not have activity for {retentionSeconds} seconds', [
			'objectType' => $objectType,
			'numDeletedRooms' => $numDeletedRooms,
			'retentionSeconds' => $retentionSeconds,
		]);
	}
}
