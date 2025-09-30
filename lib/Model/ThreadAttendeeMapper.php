<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Participant;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ThreadAttendee mapRowToEntity(array $row)
 * @method ThreadAttendee findEntity(IQueryBuilder $query)
 * @method list<ThreadAttendee> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<ThreadAttendee>
 */
class ThreadAttendeeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_thread_attendees', ThreadAttendee::class);
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

	/**
	 * @param list<int> $threadIds
	 * @return list<ThreadAttendee>
	 */
	public function findAttendeeByThreadIds(string $actorType, string $actorId, int $roomId, array $threadIds): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'actor_type',
				$query->createNamedParameter($actorType),
			))
			->andWhere($query->expr()->eq(
				'actor_id',
				$query->createNamedParameter($actorId),
			))
			->andWhere($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId),
			))
			->andWhere($query->expr()->in(
				'thread_id',
				$query->createNamedParameter($threadIds, IQueryBuilder::PARAM_INT_ARRAY),
				IQueryBuilder::PARAM_INT_ARRAY,
			));

		return $this->findEntities($query);
	}

	/**
	 * @throws DoesNotExistException if the item does not exist
	 */
	public function findAttendeeByThreadId(string $actorType, string $actorId, int $roomId, int $threadId): ThreadAttendee {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'actor_type',
				$query->createNamedParameter($actorType),
			))
			->andWhere($query->expr()->eq(
				'actor_id',
				$query->createNamedParameter($actorId),
			))
			->andWhere($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId),
			))
			->andWhere($query->expr()->eq(
				'thread_id',
				$query->createNamedParameter($threadId),
			));

		return $this->findEntity($query);
	}

	/**
	 * @return list<ThreadAttendee>
	 */
	public function findAttendeesForNotification(int $roomId, int $threadId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
			))
			->andWhere($query->expr()->eq(
				'thread_id',
				$query->createNamedParameter($threadId, IQueryBuilder::PARAM_INT),
			))
			->andWhere($query->expr()->neq(
				'notification_level',
				$query->createNamedParameter(Participant::NOTIFY_DEFAULT, IQueryBuilder::PARAM_INT),
			));

		return $this->findEntities($query);
	}
}
