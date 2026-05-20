<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		ITimeFactory $time,
		private readonly BackendNotifier $backendNotifier,
	) {
		parent::__construct($time);

		// Every time the jobs run
		$this->setInterval(1);
	}

	#[\Override]
	protected function run($argument): void {
		$this->backendNotifier->retrySendingFailedNotifications($this->time->getDateTime());
	}
}
