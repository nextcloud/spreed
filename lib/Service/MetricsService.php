<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Participant;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class MetricsService {

	public function __construct(
		protected LoggerInterface $logger,
		protected IDBConnection $connection,
	) {
	}

	public function getNumberOfActiveCalls(): int {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->count('*', 'num_calls'))
			->from('talk_rooms')
			->where($query->expr()->isNotNull('active_since'));

		$result = $query->executeQuery();
		$numCalls = (int)$result->fetchOne();
		$result->closeCursor();

		return $numCalls;
	}

	public function getNumberOfSessionsInCalls(): int {
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_sessions'))
			->from('talk_sessions')
			->where($query->expr()->gt('in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)))
			->andWhere($query->expr()->gt('last_ping', $query->createNamedParameter(time() - 60)));

		$result = $query->executeQuery();
		$numSessions = (int)$result->fetchColumn();
		$result->closeCursor();

		return $numSessions;
	}

}
