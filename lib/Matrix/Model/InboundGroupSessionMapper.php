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
 * @template-extends QBMapper<InboundGroupSession>
 */
class InboundGroupSessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_matrix_megolm_in', InboundGroupSession::class);
	}

	public function find(string $accountId, string $roomId, string $sessionId): ?InboundGroupSession {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)))
			->andWhere($qb->expr()->eq('room_id', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('session_id', $qb->createNamedParameter($sessionId)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deleteForAccount(string $accountId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId)));
		$qb->executeStatement();
	}
}
