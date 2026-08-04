<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Participant;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Session mapRowToEntity(array $row)
 * @method Session findEntity(IQueryBuilder $query)
 * @method list<Session> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Session>
 */
class SessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_sessions', Session::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findBySessionId(string $sessionId): Session {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)));

		return $this->findEntity($query);
	}

	/**
	 * @return list<Session>
	 */
	public function findByAttendeeId(int $attendeeId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('attendee_id', $query->createNamedParameter($attendeeId)));

		return $this->findEntities($query);
	}

	/**
	 * @return int Number of deleted entities
	 */
	public function deleteByAttendeeId(int $attendeeId): int {
		$delete = $this->db->getQueryBuilder();
		$delete->delete($this->getTableName())
			->where($delete->expr()->eq('attendee_id', $delete->createNamedParameter($attendeeId, IQueryBuilder::PARAM_INT)));

		return $delete->executeStatement();
	}

	/**
	 * Delete all sessions that have not been pinged since {@see $lastPingBefore}.
	 *
	 * Uses the ts_last_ping index on the last_ping column.
	 *
	 * @param int $lastPingBefore Unix timestamp, sessions with an older last_ping are deleted
	 * @return int Number of deleted entities
	 */
	public function deleteByLastPingBefore(int $lastPingBefore): int {
		$delete = $this->db->getQueryBuilder();
		$delete->delete($this->getTableName())
			->where($delete->expr()->lt('last_ping', $delete->createNamedParameter($lastPingBefore, IQueryBuilder::PARAM_INT)));

		return $delete->executeStatement();
	}

	/**
	 * Find sessions whose attendee no longer exists in the talk_attendees table.
	 *
	 * @param int $limit Maximum number of session ids to return
	 * @return list<int> Ids of the orphaned sessions
	 */
	public function findSessionIdsWithoutAttendee(int $limit): array {
		$query = $this->db->getQueryBuilder();
		$query->select('s.id')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('s.attendee_id', 'a.id'))
			->where($query->expr()->isNull('a.id'))
			->setMaxResults($limit);

		$result = $query->executeQuery();
		$ids = [];
		while ($row = $result->fetchAssociative()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();

		return $ids;
	}

	/**
	 * @param int[] $ids
	 * @return int Number of deleted entities
	 */
	public function deleteByIds(array $ids): int {
		$delete = $this->db->getQueryBuilder();
		$delete->delete($this->getTableName())
			->where($delete->expr()->in('id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		return $delete->executeStatement();
	}

	/**
	 * @param string[] $sessionIds
	 */
	public function resetInCallByIds(array $sessionIds): void {
		$update = $this->db->getQueryBuilder();
		$update->update($this->getTableName())
			->set('in_call', $update->createNamedParameter(Participant::FLAG_DISCONNECTED, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('session_id', $update->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$update->executeStatement();
	}

	public function createSessionFromRow(array $row): Session {
		return $this->mapRowToEntity([
			'id' => $row['s_id'],
			'session_id' => $row['session_id'],
			'attendee_id' => (int)$row['a_id'],
			'in_call' => (int)$row['in_call'],
			'last_ping' => (int)$row['last_ping'],
			'state' => (int)$row['s_state'],
		]);
	}
}
