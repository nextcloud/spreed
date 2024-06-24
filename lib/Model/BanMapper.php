<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Ban mapRowToEntity(array $row)
 * @method Ban findEntity(IQueryBuilder $query)
 * @method Ban[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Ban>
 */
class BanMapper extends QBMapper {
	use TTransactional;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_bans', Ban::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findForActorAndRoom(string $actorId, string $actorType, int $roomId): Ban {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	public function findByRoomId(int $roomId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($query);
	}

	public function findByBanId(int $banId): Ban {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($banId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	public function deleteBanForActorAndRoom(string $actorId, string $actorType, int $roomId): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_id', $query->createNamedParameter($actorId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}

}
