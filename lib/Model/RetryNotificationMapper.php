<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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
 * @method RetryNotification mapRowToEntity(array $row)
 * @method RetryNotification findEntity(IQueryBuilder $query)
 * @method RetryNotification[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<RetryNotification>
 */
class RetryNotificationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_retry_ocm', RetryNotification::class);
	}

	/**
	 * @return RetryNotification[]
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
}
