<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\MatterbridgeManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Class CheckMatterbridges
 *
 * @package OCA\Talk\BackgroundJob
 */
class CheckMatterbridges extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		protected IConfig $serverConfig,
		protected MatterbridgeManager $bridgeManager,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Every 15 minutes
		$this->setInterval(60 * 15);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);

	}

	#[\Override]
	protected function run($argument): void {
		if ($this->serverConfig->getAppValue('spreed', 'enable_matterbridge', '0') === '1') {
			$this->bridgeManager->checkAllBridges();
			$this->bridgeManager->killZombieBridges();
			$this->logger->info('Checked if Matterbridge instances are running correctly.');
		} else {
			if ($this->bridgeManager->stopAllBridges()) {
				$this->logger->info('Stopped all Matterbridge instances as it is disabled');
			}
		}
	}
}
