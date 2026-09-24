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
 * @template-extends QBMapper<MatrixMember>
 */
class MatrixMemberMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_members', MatrixMember::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function get(string $matrixRoomId, string $mxid): MatrixMember {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($mxid)));
		return $this->findEntity($qb);
	}

	/** @return array<string, MatrixMember> keyed by mxid */
	public function getForRoom(string $matrixRoomId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		$result = [];
		foreach ($this->findEntities($qb) as $member) {
			$result[$member->getMxid()] = $member;
		}
		return $result;
	}

	/** @return list<MatrixMember> */
	public function getForAccount(int $accountId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function deleteForRoom(string $matrixRoomId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		$qb->executeStatement();
	}

	public function clearAccount(int $accountId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('account_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
