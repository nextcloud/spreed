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
	 * @param non-negative-int $roomId
	 * @param non-negative-int $threadId
	 * @throws DoesNotExistException
	 */
	public function findById(int $roomId, int $threadId): Thread {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'id',
				$query->createNamedParameter($threadId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			))
			->andWhere($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));

		return $this->findEntity($query);
	}

	/**
	 * @param non-negative-int $roomId
	 * @param list<non-negative-int> $threadIds
	 * @return list<Thread>
	 */
	public function findByIds(int $roomId, array $threadIds): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->in(
				'id',
				$query->createNamedParameter($threadIds, IQueryBuilder::PARAM_INT_ARRAY),
				IQueryBuilder::PARAM_INT,
			))
			->andWhere($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));

		return $this->findEntities($query);
	}

	/**
	 * @param list<non-negative-int> $threadIds
	 * @return list<Thread>
	 */
	public function getForIds(array $threadIds): array {
		$threads = [];
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName());
		foreach (array_chunk($threadIds, IQueryBuilder::MAX_IN_PARAMETERS) as $ids) {
			$query->where($query->expr()->in(
				'id',
				$query->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY),
				IQueryBuilder::PARAM_INT,
			));
			$threads[] = $this->findEntities($query);
		}

		return array_merge(...$threads);
	}

	/**
	 * @param int<1, 50> $limit
	 * @param ?\DateTimeInterface $notBefore Only threads whose first message is from this time on
	 * @return list<Thread>
	 */
	public function getRecentByRoomId(int $roomId, int $limit, ?\DateTimeInterface $notBefore = null): array {
		$query = $this->db->getQueryBuilder();
		$query->select('t.*')
			->from($this->getTableName(), 't')
			->where($query->expr()->eq(
				't.room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			))
			->orderBy('t.last_activity', 'DESC')
			// last_activity has second precision, so tie on the newest message (last_message_id is 0 until the first reply)
			->addOrderBy($query->func()->greatest('t.last_message_id', 't.id'), 'DESC')
			->setMaxResults($limit);

		if ($notBefore !== null) {
			$query->join('t', 'comments', 'c', $query->expr()->eq('c.id', 't.id'))
				->andWhere($query->expr()->gte(
					'c.creation_timestamp',
					$query->createNamedParameter(\DateTime::createFromInterface($notBefore), IQueryBuilder::PARAM_DATETIME_MUTABLE),
					IQueryBuilder::PARAM_DATETIME_MUTABLE,
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
