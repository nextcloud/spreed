<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<EventMap>
 */
class EventMapMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_events', EventMap::class);
	}

	/**
	 * Event ids are unique across rooms, the room is checked so events can
	 * not reference messages of other conversations
	 */
	public function findByEventId(string $matrixRoomId, string $eventId): ?EventMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getById(string $id): EventMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		return $this->findEntity($qb);
	}

	public function findByCommentId(string $matrixRoomId, int $commentId): ?EventMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('comment_id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Whether another event of the room was encrypted with the same Megolm
	 * message index, which means it is a replay of that event
	 */
	public function isMegolmIndexUsed(string $matrixRoomId, string $eventId, string $megolmSession, int $megolmIndex): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->eq('megolm_session', $qb->createNamedParameter($megolmSession)))
			->andWhere($qb->expr()->eq('megolm_index', $qb->createNamedParameter($megolmIndex, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('event_id', $qb->createNamedParameter($eventId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$used = $result->fetchOne() !== false;
		$result->closeCursor();
		return $used;
	}

	/**
	 * Events of a Megolm session that could not be decrypted yet
	 *
	 * @return list<EventMap>
	 */
	public function findWaitingForKey(string $matrixRoomId, string $megolmSession): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->eq('megolm_session', $qb->createNamedParameter($megolmSession)))
			->andWhere($qb->expr()->isNotNull('ciphertext'));
		return $this->findEntities($qb);
	}

	/**
	 * Claim the event, fails when it was already applied, for example by the
	 * sync of another account in the same room
	 *
	 * @return bool Whether the event was new
	 */
	public function insertIfNew(EventMap $eventMap): bool {
		try {
			$this->insert($eventMap);
			return true;
		} catch (Exception $e) {
			if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return false;
			}
			throw $e;
		}
	}

	public function deleteForRoom(string $matrixRoomId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		$qb->executeStatement();
	}
}
