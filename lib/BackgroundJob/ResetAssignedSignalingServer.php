<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\CachePrefix;
use OCA\Talk\Manager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\ICache;
use OCP\ICacheFactory;

class ResetAssignedSignalingServer extends TimedJob {
	protected ICache $cache;

	/**
	 * @param ITimeFactory $time
	 * @param Manager $manager
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(
		ITimeFactory $time,
		protected Manager $manager,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct($time);

		// Every 5 minutes
		$this->setInterval(60 * 5);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);

		$this->cache = $cacheFactory->createDistributed(CachePrefix::SIGNALING_ASSIGNED_SERVER);
	}

	#[\Override]
	protected function run($argument): void {
		$this->manager->resetAssignedSignalingServers($this->cache);
	}
}
