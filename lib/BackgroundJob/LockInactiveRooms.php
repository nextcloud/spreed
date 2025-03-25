<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Config;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class LockInactiveRooms extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private RoomService $roomService,
		private Config $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);

		// Every hour
		$this->setInterval(60 * 60 * 24);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function run($argument): void {
		$interval = $this->appConfig->getInactiveLockTime();
		$forceLobby = $this->appConfig->enableLobbyOnLockedRooms();
		if ($interval === 0) {
			return;
		}
		$timestamp = $this->time->getTime() - $interval * 60 * 60 * 24;
		$time = $this->time->getDateTime('@' . $timestamp);
		$rooms = $this->roomService->getInactiveRooms($time);
		array_map(function (Room $room) use ($forceLobby) {
			$this->roomService->setReadOnly($room, Room::READ_ONLY);
			$this->logger->debug("Locking room {$room->getId()} due to inactivity");
			if ($forceLobby) {
				$this->roomService->setLobby($room, Webinary::LOBBY_NON_MODERATORS, $this->time->getDateTime());
				$this->logger->debug("Enabling lobby for room {$room->getId()}");
			}
		}, $rooms);
	}
}
