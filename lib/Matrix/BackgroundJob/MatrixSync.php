<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\BackgroundJob;

use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Polls /sync for all linked accounts that are due. Runs as often as cron
 * allows (interval 10 s) and spends at most ~25 s per run; per-account locks
 * make concurrent cron workers safe.
 */
class MatrixSync extends TimedJob {
	public const BUDGET_SECONDS = 25;

	public function __construct(
		ITimeFactory $timeFactory,
		private readonly SyncService $syncService,
		private readonly MatrixConfig $config,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(10);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
		$this->setAllowParallelRuns(true);
	}

	#[\Override]
	protected function run($argument): void {
		if (!$this->config->isEnabled()) {
			return;
		}
		$start = microtime(true);
		$accounts = $this->syncService->getDueAccounts($this->config->getMaxParallelSyncs() * 5);
		foreach ($accounts as $account) {
			$remaining = self::BUDGET_SECONDS - (int)(microtime(true) - $start);
			if ($remaining < 3) {
				break;
			}
			$perAccount = max(3, min(10, intdiv($remaining, max(1, count($accounts)))));
			$stats = $this->syncService->syncAccount($account, $perAccount, 0);
			if ($stats !== null && $stats['messages'] > 0) {
				$this->logger->debug('Matrix sync for ' . $account->getMxid() . ': ' . $stats['messages'] . ' new messages in ' . $stats['rooms'] . ' rooms');
			}
		}
	}
}
