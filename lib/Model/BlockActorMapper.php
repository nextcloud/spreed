<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Types;
use OCP\IDBConnection;

class BlockActorMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_block_actor', BlockActor::class);
	}

	public function getBlockListByBlocker($blocker) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_id', $query->createNamedParameter($blocker)));

		$return = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			/** @var BlockActor */
			$blockedAgent = $this->mapRowToEntity($row);
			$return[$blockedAgent->getBlockedId()] = $blockedAgent;
		}
		return $return;
	}

	public function delete(Entity $entity): Entity {
		if ($entity->getId()) {
			return parent::delete($entity);
		}
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->tableName)
			->andWhere(
				$qb->expr()->eq('actor_type', $qb->createNamedParameter($entity->getActorType(), Types::STRING)),
				$qb->expr()->eq('actor_id', $qb->createNamedParameter($entity->getActorId(), Types::STRING)),
				$qb->expr()->eq('blocked_type', $qb->createNamedParameter($entity->getBlockedType(), Types::STRING)),
				$qb->expr()->eq('blocked_id', $qb->createNamedParameter($entity->getBlockedId(), Types::STRING))
			);
		$qb->executeStatement();
		return $entity;
	}
}
