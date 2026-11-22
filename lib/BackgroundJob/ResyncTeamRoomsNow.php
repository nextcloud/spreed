<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;

/**
 * One full resync pass right after Circles was (re-)enabled, so team rooms
 * do not wait for the hourly ResyncTeamRooms. Queues itself again with the
 * cursor until the pass is done.
 *
 * @psalm-type ResyncTeamRoomsNowArgument = array{afterId: int}
 */
class ResyncTeamRoomsNow extends QueuedJob {
	protected const TIME_BUDGET = 120; // seconds

	public function __construct(
		ITimeFactory $time,
		protected readonly TeamRoomSyncService $syncService,
		protected readonly IJobList $jobList,
	) {
		parent::__construct($time);
	}

	/**
	 * @param ResyncTeamRoomsNowArgument $argument
	 */
	#[\Override]
	protected function run($argument): void {
		$cursor = $this->syncService->resyncFrom($argument['afterId'] ?? 0, self::TIME_BUDGET);

		// 0: done, null: teams unavailable again, the hourly job takes over
		if ($cursor !== null && $cursor !== 0) {
			$this->jobList->add(self::class, ['afterId' => $cursor]);
		}
	}
}
