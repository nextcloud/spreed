<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Signaling\Messages;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Class ExpireSignalingMessage
 *
 * @package OCA\Talk\BackgroundJob
 */
class ExpireSignalingMessage extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		protected Messages $messages,
	) {
		parent::__construct($timeFactory);

		// Every 5 minutes
		$this->setInterval(60 * 5);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);

	}

	#[\Override]
	protected function run($argument): void {
		// Older than 5 minutes
		$this->messages->expireOlderThan(5 * 60);
	}
}
