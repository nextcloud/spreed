<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method LogEntry mapRowToEntity(array $row)
 * @method LogEntry findEntity(IQueryBuilder $query)
 * @method list<LogEntry> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<LogEntry>
 */
class LogEntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'twd_log_entries', LogEntry::class);
	}

	/**
	 * @return LogEntry[]
	 */
	public function findByConversation(string $server, string $token): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('server', $query->createNamedParameter($server)))
			->andWhere($query->expr()->eq('token', $query->createNamedParameter($token)));
		return $this->findEntities($query);
	}

	public function hasActiveCall(string $server, string $token): bool {
		$query = $this->db->getQueryBuilder();
		$query->select($query->expr()->literal(1))
			->from($this->getTableName())
			->where($query->expr()->eq('server', $query->createNamedParameter($server)))
			->andWhere($query->expr()->eq('token', $query->createNamedParameter($token)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter(LogEntry::TYPE_ATTENDEE)))
			->setMaxResults(1);
		$result = $query->executeQuery();
		$hasAttendee = (bool)$result->fetchOne();
		$result->closeCursor();

		return $hasAttendee;
	}

	public function deleteByConversation(string $server, string $token): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('server', $query->createNamedParameter($server)))
			->andWhere($query->expr()->eq('token', $query->createNamedParameter($token)));
		$query->executeStatement();
	}
}
