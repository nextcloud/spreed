<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Consent mapRowToEntity(array $row)
 * @method Consent findEntity(IQueryBuilder $query)
 * @method Consent[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Consent>
 */
class ConsentMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_consent', Consent::class);
	}

	/**
	 * @return Consent[]
	 */
	public function findForToken(string $token): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		return $this->findEntities($query);
	}

	public function deleteByToken(string $token): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		return $query->executeStatement();
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 * @return Consent[]
	 */
	public function findForActor(string $actorType, string $actorId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $this->findEntities($query);
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 */
	public function deleteByActor(string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $query->executeStatement();
	}

	/**
	 * @psalm-param Attendee::ACTOR_* $actorType
	 * @return Consent[]
	 */
	public function findForTokenByActor(string $token, string $actorType, string $actorId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $this->findEntities($query);
	}
}
