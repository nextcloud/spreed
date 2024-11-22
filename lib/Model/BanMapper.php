<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Ban mapRowToEntity(array $row)
 * @method Ban findEntity(IQueryBuilder $query)
 * @method list<Ban> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Ban>
 */
class BanMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_bans', Ban::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findForBannedActorAndRoom(string $bannedActorType, string $bannedActorId, int $roomId): Ban {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('banned_actor_type', $query->createNamedParameter($bannedActorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('banned_actor_id', $query->createNamedParameter($bannedActorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @return list<Ban>
	 */
	public function findByRoomId(int $roomId, ?string $bannedActorType = null): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');

		if ($bannedActorType !== null) {
			$query->andWhere($query->expr()->eq('banned_actor_type', $query->createNamedParameter($bannedActorType, IQueryBuilder::PARAM_STR)));
		}

		return $this->findEntities($query);
	}

	public function findByUserId(string $userId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('banned_actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('banned_actor_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('id', 'ASC');

		return $this->findEntities($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByBanIdAndRoom(int $banId, int $roomId): Ban {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($banId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	public function updateDisplayNameForActor(string $actorType, string $actorId, string $displayName): void {
		$update = $this->db->getQueryBuilder();
		$update->update($this->getTableName())
			->set('moderator_displayname', $update->createNamedParameter($displayName))
			->where($update->expr()->eq('moderator_actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('moderator_actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();

		$update = $this->db->getQueryBuilder();
		$update->update($this->getTableName())
			->set('banned_displayname', $update->createNamedParameter($displayName))
			->where($update->expr()->eq('banned_actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('banned_actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();
	}
}
