<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\BackgroundJob;

use OCA\Talk\Config;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Syncs the linked Matrix accounts that are due
 */
class MatrixSync extends TimedJob {
	/** Seconds a run may take */
	public const BUDGET = 25;
	/** Seconds a single account may take */
	public const ACCOUNT_BUDGET = 10;

	public function __construct(
		ITimeFactory $time,
		private readonly Config $talkConfig,
		private readonly SyncService $syncService,
	) {
		parent::__construct($time);
		$this->setInterval(SyncService::SYNC_INTERVAL);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		if (!$this->talkConfig->isMatrixEnabled()) {
			return;
		}

		$end = $this->time->getTime() + self::BUDGET;
		foreach ($this->syncService->getDueAccounts(50) as $account) {
			$remaining = $end - $this->time->getTime();
			if ($remaining <= 0) {
				break;
			}
			$this->syncService->syncAccount($account, min(self::ACCOUNT_BUDGET, $remaining));
		}
	}
}
