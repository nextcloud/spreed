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
