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
	public function getById(int $id): Account {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
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
	 * @throws DoesNotExistException
	 */
	public function getByMxid(string $mxid): Account {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('mxid', $qb->createNamedParameter($mxid)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	/**
	 * @param list<string> $mxids
	 * @return array<string, Account> keyed by mxid
	 */
	public function getByMxids(array $mxids): array {
		if ($mxids === []) {
			return [];
		}
		$result = [];
		foreach (array_chunk($mxids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('mxid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			foreach ($this->findEntities($qb) as $account) {
				$result[$account->getMxid()] = $account;
			}
		}
		return $result;
	}

	/** @return list<Account> */
	public function getAll(?int $status = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id', 'ASC');
		if ($status !== null) {
			$qb->where($qb->expr()->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	/** @return list<Account> */
	public function getByHomeserver(int $homeserverId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('homeserver_id', $qb->createNamedParameter($homeserverId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Active accounts whose last sync is older than $olderThan (or never synced), oldest first.
	 * @return list<Account>
	 */
	public function getDueForSync(\DateTime $olderThan, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Account::STATUS_ACTIVE, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('last_sync'),
				$qb->expr()->lt('last_sync', $qb->createNamedParameter($olderThan, IQueryBuilder::PARAM_DATETIME_MUTABLE)),
			))
			->orderBy('last_sync', 'ASC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * Atomically acquire the per-account sync lock. Returns false when another
	 * process holds an unexpired lock.
	 */
	public function acquireLock(Account $account, \DateTime $now, \DateTime $until): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('lock_until', $qb->createNamedParameter($until, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($account->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('lock_until'),
				$qb->expr()->lt('lock_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE)),
			));
		$acquired = $qb->executeStatement() === 1;
		if ($acquired) {
			$account->setLockUntil($until);
		}
		return $acquired;
	}

	public function releaseLock(Account $account): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('lock_until', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($account->getId(), IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
		$account->setLockUntil(null);
	}

	/** @return array{total: int, active: int, error: int, disabled: int, medianSyncAge: ?int} */
	public function getStatistics(\DateTime $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('status', 'last_sync')->from($this->getTableName());
		$result = $qb->executeQuery();
		$stats = ['total' => 0, 'active' => 0, 'error' => 0, 'disabled' => 0, 'medianSyncAge' => null];
		$ages = [];
		while ($row = $result->fetch()) {
			$stats['total']++;
			$status = (int)$row['status'];
			if ($status === Account::STATUS_ACTIVE) {
				$stats['active']++;
				if ($row['last_sync'] !== null) {
					$ages[] = $now->getTimestamp() - (new \DateTime($row['last_sync']))->getTimestamp();
				}
			} elseif ($status === Account::STATUS_TOKEN_INVALID) {
				$stats['error']++;
			} else {
				$stats['disabled']++;
			}
		}
		$result->closeCursor();
		if ($ages !== []) {
			sort($ages);
			$stats['medianSyncAge'] = $ages[intdiv(count($ages), 2)];
		}
		return $stats;
	}
}
