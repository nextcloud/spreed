<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Joas Schilling <coding@schilljs.com>
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

	protected function run($argument): void {
		$this->manager->resetAssignedSignalingServers($this->cache);
	}
}
