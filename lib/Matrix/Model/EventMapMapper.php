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
	 * @throws DoesNotExistException
	 */
	public function getByEventId(string $eventId): EventMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId)));
		return $this->findEntity($qb);
	}

	public function findByEventId(string $eventId): ?EventMap {
		try {
			return $this->getByEventId($eventId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByCommentId(int $commentId): EventMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('comment_id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	public function findByCommentId(int $commentId): ?EventMap {
		try {
			return $this->getByCommentId($commentId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Insert unless the event id is already known.
	 * @return bool true when inserted, false when the event was already mapped
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

	/** Oldest mapped message timestamp in a room (for backfill decisions), null if none. */
	public function getOldestTimestamp(string $matrixRoomId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->min('origin_ts'))
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->isNotNull('comment_id'));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false || $value === null ? null : (int)$value;
	}

	/** Events of a room still waiting for a Megolm session. @return list<EventMap> */
	public function getUndecryptable(string $matrixRoomId, ?string $sessionId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->eq('decrypt_state', $qb->createNamedParameter(EventMap::DECRYPT_MISSING_SESSION, IQueryBuilder::PARAM_INT)))
			->orderBy('origin_ts', 'ASC');
		if ($sessionId !== null) {
			$qb->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		}
		return $this->findEntities($qb);
	}

	/** Smallest Talk comment id mirrored for this room, null if none. */
	public function getOldestCommentId(string $matrixRoomId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->min('comment_id'))
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->isNotNull('comment_id'));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false || $value === null ? null : (int)$value;
	}

	public function countForRoom(string $matrixRoomId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'num'))
			->from($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->isNotNull('comment_id'));
		return (int)$qb->executeQuery()->fetchOne();
	}

	public function deleteForRoom(string $matrixRoomId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('matrix_room_id', $qb->createNamedParameter($matrixRoomId)));
		$qb->executeStatement();
	}

	/** Remove bookkeeping rows that never became a message and are older than the given timestamp (ms). */
	public function pruneUnmapped(int $olderThanTs): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->isNull('comment_id'))
			->andWhere($qb->expr()->eq('processed', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('origin_ts', $qb->createNamedParameter($olderThanTs, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
