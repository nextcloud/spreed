<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Vote findEntity(IQueryBuilder $query)
 * @method list<Vote> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Vote>
 */
class VoteMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_poll_votes', Vote::class);
	}

	/**
	 * @return list<Vote>
	 */
	public function findByPollId(int $pollId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId)));

		return $this->findEntities($query);
	}

	/**
	 * @return list<Vote>
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

	public function deleteVotesByActor(int $pollId, string $actorType, string $actorId): void {
		$query = $this->db->getQueryBuilder();

		$query->delete($this->getTableName())
			->where($query->expr()->eq('poll_id', $query->createNamedParameter($pollId)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		$query->executeStatement();
	}
}
