<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<OlmSession>
 */
class OlmSessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_olm_sessions', OlmSession::class);
	}

	/**
	 * @return list<OlmSession> most recently used first
	 */
	public function findForDevice(string $accountId, string $senderKey): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)))
			->andWhere($qb->expr()->eq('sender_key', $qb->createNamedParameter($senderKey)))
			->orderBy('last_used', 'DESC');
		return $this->findEntities($qb);
	}

	public function deleteForAccount(string $accountId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)));
		$qb->executeStatement();
	}
}
