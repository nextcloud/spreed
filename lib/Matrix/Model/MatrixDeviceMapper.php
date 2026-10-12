<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<MatrixDevice>
 */
class MatrixDeviceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_devices', MatrixDevice::class);
	}

	/**
	 * @return array<string, MatrixDevice> indexed by device id
	 */
	public function findForUser(string $accountId, string $mxid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($mxid)));
		$devices = [];
		foreach ($this->findEntities($qb) as $device) {
			$devices[$device->getDeviceId()] = $device;
		}
		return $devices;
	}

	public function markOutdated(string $accountId, string $mxid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('outdated', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)))
			->andWhere($qb->expr()->eq('mxid', $qb->createNamedParameter($mxid)));
		$qb->executeStatement();
	}

	public function deleteForAccount(string $accountId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)));
		$qb->executeStatement();
	}
}
