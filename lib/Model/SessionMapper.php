<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCA\Talk\Participant;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Session mapRowToEntity(array $row)
 */
class SessionMapper extends QBMapper {

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_sessions', Session::class);
	}

	/**
	 * @param string $sessionId
	 * @return Session
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findBySessionId(string $sessionId): Session {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)));

		return $this->findEntity($query);
	}

	/**
	 * @param int $attendeeId
	 * @return Session[]
	 */
	public function findByAttendeeId(int $attendeeId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('attendee_id', $query->createNamedParameter($attendeeId)));

		return $this->findEntities($query);
	}

	/**
	 * @param int $attendeeId
	 * @return int Number of deleted entities
	 */
	public function deleteByAttendeeId(int $attendeeId): int {
		$delete = $this->db->getQueryBuilder();
		$delete->delete($this->getTableName())
			->where($delete->expr()->eq('attendee_id', $delete->createNamedParameter($attendeeId, IQueryBuilder::PARAM_INT)));

		return $delete->executeStatement();
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
	 * @param int[] $ids
	 */
	public function resetInCallByIds(array $ids): void {
		$update = $this->db->getQueryBuilder();
		$update->update($this->getTableName())
			->set('in_call', $update->createNamedParameter(Participant::FLAG_DISCONNECTED, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('id', $update->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$update->executeStatement();
	}

	public function createSessionFromRow(array $row): Session {
		return $this->mapRowToEntity([
			'id' => $row['s_id'],
			'session_id' => $row['session_id'],
			'attendee_id' => (int) $row['a_id'],
			'in_call' => (int) $row['in_call'],
			'last_ping' => (int) $row['last_ping'],
		]);
	}
}
