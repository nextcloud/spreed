<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Poll findEntity(IQueryBuilder $query)
 * @method list<Poll> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Poll>
 */
class PollMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_polls', Poll::class);
	}

	/**
	 * @return list<Poll>
	 */
	public function getDraftsByRoomId(int $roomId): array {
		$query = $this->db->getQueryBuilder();

		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('status', $query->createNamedParameter(Poll::STATUS_DRAFT, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');

		return $this->findEntities($query);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPollByRoomIdAndPollId(int $roomId, int $pollId): Poll {
		$query = $this->db->getQueryBuilder();

		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @return list<int>
	 */
	public function getExpiredPollIds(\DateTime $dateTime): array {
		$query = $this->db->getQueryBuilder();

		$query->select('id')
			->from($this->getTableName())
			->where($query->expr()->lte('expire_date', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE)));

		$result = $query->executeQuery();
		$ids = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();

		return $ids;
	}

	/**
	 * @param list<int> $pollIds
	 */
	public function deleteByPollIds(array $pollIds): void {
		$query = $this->db->getQueryBuilder();

		$query->delete($this->getTableName());
		foreach (array_chunk($pollIds, IQueryBuilder::MAX_IN_PARAMETERS) as $ids) {
			$query->where($query->expr()->in('id', $query->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			$query->executeStatement();
		}
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
			->where($query->expr()->eq('id', $query->createNamedParameter($pollId, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}
}
