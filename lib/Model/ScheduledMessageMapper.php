<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
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
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_scheduled_msg', ScheduledMessage::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(Room $chat, int $id, string $actorType, string $actorId): ScheduledMessage {
		if (!$chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ProxyCacheMessageMapper::findById() with a non-federated chat.');
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)));

		return $this->findEntity($query);
	}

	/**
	 * @return array<int, ScheduledMessage>
	 */
	public function findByRoomAndActor(Room $chat, string $actorType, string $actorId): array {
		if (!$chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ProxyCacheMessageMapper::findById() with a non-federated chat.');
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($query);
	}

	public function getCountByActorAndRoom(Room $chat, string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->select(['room_id', $query->createFunction('COUNT(DISTINCT(room_id)) AS count'), 'room_id'])
			->from($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr('room_id'), $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR));

		$result = $query->executeQuery();
		$count = $result->rowCount();
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
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)));
		return $query->executeStatement();
	}

	public function deleteById(Room $chat, int $id, string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($chat->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}

	public function deleteByActor(string $actorType, string $actorId) {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}

	public function getMessagesDue(\DateTime $dateTime): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->lt('send_at', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE)));

		return $this->findEntities($query);
	}
}
