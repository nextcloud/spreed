<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
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
		parent::__construct($db, 'talk_room_events', RoomEvent::class);
	}

	/**
	 * @return list<RoomEvent>
	 */
	public function findForRoom(string $roomToken): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_token', $query->createNamedParameter($roomToken, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		return $this->findEntities($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findNextForRoom(string $roomToken, int $start): RoomEvent {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_token', $query->createNamedParameter($roomToken, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT))
			->andWhere($query->expr()->lte('start', $query->createNamedParameter($start, IQueryBuilder::PARAM_INT)));
		// Could this return more than one roomEvent?
		return $this->findEntity($query);
	}

	public function deleteByRoom(string $roomToken): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_token', $query->createNamedParameter($roomToken, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		return $query->executeStatement();
	}
}
