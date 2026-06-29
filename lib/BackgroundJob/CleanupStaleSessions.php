<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Model\SessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

/**
 * Removes stale sessions when:
 * - it has not been pinged in the last 24 hours
 * - its attendee no longer exists in the talk_attendees table
 *
 * @package OCA\Talk\BackgroundJob
 */
class CleanupStaleSessions extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly SessionMapper $sessionMapper,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);

		// Every day in the insensitive time frame
		$this->setInterval(23 * 3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		$lastPingBefore = $this->time->getTime() - 24 * 3600;
		$numDeletedSessions = $this->sessionMapper->deleteByLastPingBefore($lastPingBefore);

		do {
			$ids = $this->sessionMapper->findSessionIdsWithoutAttendee(IQueryBuilder::MAX_IN_PARAMETERS);
			if ($ids === []) {
				break;
			}

			$numDeletedSessions += $this->sessionMapper->deleteByIds($ids);
		} while (count($ids) === IQueryBuilder::MAX_IN_PARAMETERS);

		if ($numDeletedSessions > 0) {
			$this->logger->info('Deleted {numDeletedSessions} stale sessions', [
				'numDeletedSessions' => $numDeletedSessions,
			]);
		}
	}
}
