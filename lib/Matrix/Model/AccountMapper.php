<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Account>
 */
class AccountMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_accounts', Account::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByUserId(string $userId): Account {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $offset Only return accounts of users after this user id
	 * @param string $homeserverId Only return accounts on this homeserver, empty for all
	 * @return list<Account>
	 */
	public function getAll(string $offset, int $limit, string $homeserverId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('user_id', 'ASC')
			->setMaxResults($limit);
		if ($offset !== '') {
			$qb->andWhere($qb->expr()->gt('user_id', $qb->createNamedParameter($offset)));
		}
		if ($homeserverId !== '') {
			$qb->andWhere($qb->expr()->eq('homeserver_id', $qb->createNamedParameter($homeserverId)));
		}
		return $this->findEntities($qb);
	}

	/** @return list<Account> */
	public function getByHomeserver(string $homeserverId, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('homeserver_id', $qb->createNamedParameter($homeserverId)))
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	public function hasAccountsOnHomeserver(string $homeserverId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('homeserver_id', $qb->createNamedParameter($homeserverId)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
	}
}
