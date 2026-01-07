<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ScheduledMessage mapRowToEntity(array $row)
 * @method ScheduledMessage findEntity(IQueryBuilder $query)
 * @method list<ScheduledMessage> findEntities(IQueryBuilder $query)
 * @method ScheduledMessage update(ScheduledMessage $scheduledMessage)
 * @method ScheduledMessage delete(ScheduledMessage $scheduledMessage)
 * @template-extends QBMapper<ScheduledMessage>
 */
class ScheduledMessageMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_scheduled_msg', ScheduledMessage::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(Room $chat, string $id, string $actorType, string $actorId): ScheduledMessage {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)));

		return $this->findEntity($query);
	}

	public function findByRoomAndActor(Room $chat, string $actorType, string $actorId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('s.*');
		$helper = new SelectHelper();
		$helper->selectThreadsTable($query, aliasAll: true);
		$query->from($this->getTableName(), 's')
			->where($query->expr()->eq('s.room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('s.actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('s.actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->leftJoin('s', 'talk_threads', 'th', $query->expr()->eq('s.thread_id', 'th.id'))
			->orderBy('s.send_at', 'ASC');

		$cursor = $query->executeQuery();
		$result = $cursor->fetchAll();
		$cursor->closeCursor();

		return $result;
	}

	public function getCountByActorAndRoom(Room $chat, string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)));

		$result = $query->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function deleteMessagesByRoomAndActor(Room $chat, string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}

	public function deleteMessagesByRoom(Room $chat): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_INT)));
		return $query->executeStatement();
	}

	public function deleteById(Room $chat, string $id, string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('id', $query->createNamedParameter($id)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}

	public function deleteByActor(string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}

	public function getMessagesDue(\DateTimeInterface $dateTime): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->lte('send_at', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE)));

		return $this->findEntities($query);
	}
}
