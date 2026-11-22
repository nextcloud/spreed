<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Hourly safety net: resync team rooms with Circles.
 * Starts a pass of ResyncTeamRoomsNow, which works through the rooms in
 * batches, unless a pass is still running.
 */
class ResyncTeamRooms extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		protected readonly TeamRoomSyncService $syncService,
	) {
		parent::__construct($time);
		$this->setInterval(3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		$this->syncService->queueFullResync();
	}
}
