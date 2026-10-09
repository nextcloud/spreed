<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCA\Talk\Matrix\Client\Model\Member;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<MatrixMember>
 */
class MatrixMemberMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_members', MatrixMember::class);
	}

	/** @return array<string, MatrixMember> indexed by Matrix user id */
	public function getForRoom(string $matrixRoomId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));

		$members = [];
		foreach ($this->findEntities($qb) as $member) {
			$members[$member->getMxid()] = $member;
		}
		return $members;
	}

	/** @return list<MatrixMember> */
	public function getForAccount(string $accountId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)));
		return $this->findEntities($qb);
	}

	/**
	 * Whether a member with a linked account is still in the room
	 */
	public function hasLinkedMembers(string $matrixRoomId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->isNotNull('account_id'))
			->andWhere($qb->expr()->eq('membership', $qb->createNamedParameter(Member::JOIN)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}

	public function deleteForRoom(string $matrixRoomId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		$qb->executeStatement();
	}
}
