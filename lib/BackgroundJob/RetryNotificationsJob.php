<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Federation\BackendNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Retry to send OCM notifications
 */
class RetryNotificationsJob extends TimedJob {
	public function __construct(
		private BackendNotifier $backendNotifier,
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);

		// Every time the jobs run
		$this->setInterval(1);
	}

	protected function run($argument): void {
		$this->backendNotifier->retrySendingFailedNotifications($this->time->getDateTime());
	}
}
