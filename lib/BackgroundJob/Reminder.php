<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Service\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class Reminder extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		protected ReminderService $reminderService,
	) {
		parent::__construct($time);
		// Every minute
		$this->setInterval(60);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	protected function run($argument): void {
		$this->reminderService->executeReminders($this->time->getDateTime());
	}
}
