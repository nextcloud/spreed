<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Vote>
 */
class VoteMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_poll_votes', Vote::class);
	}

	/**
	 * @param int $pollId
	 * @return Vote[]
	 */
	public function findByPollId(int $pollId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId)));

		return $this->findEntities($query);
	}

	/**
	 * @param int $pollId
	 * @psalm-param Attendee::ACTOR_* $actorType
	 * @param string $actorId
	 * @return Vote[]
	 */
	public function findByPollIdForActor(int $pollId, string $actorType, string $actorId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $this->findEntities($query);
	}

	public function deleteByRoomId(int $roomId): void {
		$query = $this->db->getQueryBuilder();

		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}

	public function deleteByPollId(int $pollId): void {
		$query = $this->db->getQueryBuilder();

		$query->delete($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 */
	public function deleteVotesByActor(int $pollId, string $actorType, string $actorId): void {
		$query = $this->db->getQueryBuilder();

		$query->delete($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		$query->executeStatement();
	}
}
