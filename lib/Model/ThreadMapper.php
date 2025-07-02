<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Thread findEntity(IQueryBuilder $query)
 * @method list<Thread> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Thread>
 */
class ThreadMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_threads', Thread::class);
	}

	/**
	 * @param non-negative-int $threadId
	 * @throws DoesNotExistException
	 */
	public function findById(int $threadId): Thread {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'id',
				$query->createNamedParameter($threadId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));

		return $this->findEntity($query);
	}

	/**
	 * @param list<non-negative-int> $threadIds
	 * @return list<Thread>
	 */
	public function findByIds(array $threadIds): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->in(
				'id',
				$query->createNamedParameter($threadIds, IQueryBuilder::PARAM_INT_ARRAY)
			));

		return $this->findEntities($query);
	}

	/**
	 * @param int<1, 50> $limit
	 * @param non-negative-int $offsetId
	 * @return list<Thread>
	 */
	public function findByRoomId(int $roomId, int $limit, int $offsetId = 0): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			))
			->orderBy('id', 'DESC')
			->setMaxResults($limit);

		if ($offsetId !== 0) {
			$query->andWhere($query->expr()->lt(
				'id',
				$query->createNamedParameter($offsetId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));
		}

		return $this->findEntities($query);
	}

	public function deleteByRoomId(int $roomId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));

		return $query->executeStatement();
	}
}
