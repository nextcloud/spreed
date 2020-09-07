<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
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

use OCP\BackgroundJob\TimedJob;
use OCA\Talk\MatterbridgeManager;
use Psr\Log\LoggerInterface;

/**
 * Class CheckMatterbridges
 *
 * @package OCA\Talk\BackgroundJob
 */
class CheckMatterbridges extends TimedJob {

	/** @var MatterbridgeManager */
	protected $bridgeManager;

	/** @var LoggerInterface */
	protected $logger;

	public function __construct(MatterbridgeManager $bridgeManager,
								LoggerInterface $logger) {
		// Every 15 minutes
		$this->setInterval(60 * 15);

		$this->bridgeManager = $bridgeManager;
		$this->logger = $logger;
	}

	protected function run($argument): void {
		$this->bridgeManager->checkAllBridges();
		$this->bridgeManager->killZombieBridges();
		$this->logger->info('Checked if Matterbridge instances are running correctly.');
	}
}
