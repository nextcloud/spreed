<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<MatrixRoom>
 */
class MatrixRoomMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_rooms', MatrixRoom::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByMatrixRoomId(string $matrixRoomId): MatrixRoom {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByRoomId(int $roomId): MatrixRoom {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<MatrixRoom> */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	public function countUndecryptable(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'num'))
			->from('talk_matrix_events')
			->where($qb->expr()->eq('decrypt_state', $qb->createNamedParameter(EventMap::DECRYPT_MISSING_SESSION, IQueryBuilder::PARAM_INT)));
		return (int)$qb->executeQuery()->fetchOne();
	}
}
