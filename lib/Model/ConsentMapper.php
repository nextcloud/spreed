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
 * @method Consent mapRowToEntity(array $row)
 * @method Consent findEntity(IQueryBuilder $query)
 * @method list<Consent> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Consent>
 */
class ConsentMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_consent', Consent::class);
	}

	/**
	 * @return list<Consent>
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
	 * @return list<Consent>
	 */
	public function findForActor(string $actorType, string $actorId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $this->findEntities($query);
	}

	public function deleteByActor(string $actorType, string $actorId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('actor_id', $query->createNamedParameter($actorId)));

		return $query->executeStatement();
	}

	/**
	 * @return list<Consent>
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
