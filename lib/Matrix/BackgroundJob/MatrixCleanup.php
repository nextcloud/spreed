<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\BackgroundJob;

use OCA\Talk\Matrix\Model\EventMapMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Prunes event bookkeeping rows that never became a message (reactions,
 * redactions, custom events …) after 90 days.
 */
class MatrixCleanup extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly EventMapMapper $eventMapMapper,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		$this->eventMapMapper->pruneUnmapped(($this->time->getTime() - 90 * 86400) * 1000);
	}
}
