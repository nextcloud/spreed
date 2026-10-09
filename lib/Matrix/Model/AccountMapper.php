<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
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

	/**
	 * @param list<string> $mxids
	 * @return array<string, Account> indexed by Matrix user id
	 */
	public function getByMxids(array $mxids): array {
		$accounts = [];
		foreach (array_chunk($mxids, IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('mxid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			foreach ($this->findEntities($qb) as $account) {
				$accounts[$account->getMxid()] = $account;
			}
		}
		return $accounts;
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

	/**
	 * Active accounts that did not sync since the given time, longest waiting first
	 *
	 * @return list<Account>
	 */
	public function getDueForSync(int $syncedBefore, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Account::STATUS_ACTIVE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('last_sync', $qb->createNamedParameter($syncedBefore, IQueryBuilder::PARAM_INT)))
			->orderBy('last_sync', 'ASC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Take the sync lock of the account unless another process holds an unexpired one
	 */
	public function acquireLock(Account $account, int $now, int $until): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('lock_until', $qb->createNamedParameter($until, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((string)$account->getId())))
			->andWhere($qb->expr()->lt('lock_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)));
		if ($qb->executeStatement() !== 1) {
			return false;
		}

		$account->setLockUntil($until);
		$account->resetUpdatedFields();
		return true;
	}

	public function releaseLock(Account $account): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('lock_until', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((string)$account->getId())));
		$qb->executeStatement();
	}
}
