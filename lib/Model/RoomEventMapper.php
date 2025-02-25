<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method RoomEvent mapRowToEntity(array $row)
 * @method RoomEvent findEntity(IQueryBuilder $query)
 * @method list<RoomEvent> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<RoomEvent>
 */
class RoomEventMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_room_events', Consent::class);
	}

	/**
	 * @return list<RoomEvent>
	 */
	public function findForRoom(int $roomId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		return $this->findEntities($query);
	}

	public function deleteByRoom(int $roomId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		return $query->executeStatement();
	}
}
