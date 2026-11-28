<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCA\Talk\Model\Attendee;
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
	public function getById(string $id): MatrixRoom {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		return $this->findEntity($qb);
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
	 * Tokens of the conversations that mirror the rooms and that the user takes part in
	 *
	 * @param list<string> $matrixRoomIds
	 * @return array<string, string> Matrix room id => conversation token
	 */
	public function findConversationTokens(array $matrixRoomIds, string $userId): array {
		if ($matrixRoomIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.matrix_room_id', 'r.token')
			->from($this->getTableName(), 'm')
			->innerJoin('m', 'talk_rooms', 'r', $qb->expr()->eq('r.id', 'm.room_id'))
			->innerJoin('r', 'talk_attendees', 'a', $qb->expr()->andX(
				$qb->expr()->eq('a.room_id', 'r.id'),
				$qb->expr()->eq('a.actor_type', $qb->createNamedParameter(Attendee::ACTOR_USERS)),
				$qb->expr()->eq('a.actor_id', $qb->createNamedParameter($userId)),
			))
			->where($qb->expr()->in('m.matrix_room_id', $qb->createNamedParameter($matrixRoomIds, IQueryBuilder::PARAM_STR_ARRAY)));

		$tokens = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetchAssociative()) {
			$tokens[(string)$row['matrix_room_id']] = (string)$row['token'];
		}
		$result->closeCursor();
		return $tokens;
	}
}
