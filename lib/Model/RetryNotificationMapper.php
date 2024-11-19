<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method RetryNotification mapRowToEntity(array $row)
 * @method RetryNotification findEntity(IQueryBuilder $query)
 * @method list<RetryNotification> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<RetryNotification>
 */
class RetryNotificationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_retry_ocm', RetryNotification::class);
	}

	/**
	 * @return list<RetryNotification>
	 */
	public function getAllDue(\DateTimeInterface $dueDateTime, ?int $limit = 500): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->lte('next_retry', $query->createNamedParameter($dueDateTime, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));

		if ($limit !== null) {
			$query->setMaxResults($limit)
				->orderBy('next_retry', 'ASC')
				->addOrderBy('id', 'ASC');
		}

		return $this->findEntities($query);
	}

	public function deleteByProviderId($providerId): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('provider_id', $query->createNamedParameter($providerId)));
		$query->executeStatement();
	}
}
