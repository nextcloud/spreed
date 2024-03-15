<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024, Joas Schilling <coding@schilljs.com>
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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ProxyCacheMessage mapRowToEntity(array $row)
 * @method ProxyCacheMessage findEntity(IQueryBuilder $query)
 * @method ProxyCacheMessage[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<ProxyCacheMessage>
 */
class ProxyCacheMessageMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_proxy_messages', ProxyCacheMessage::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $proxyId): ProxyCacheMessage {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($proxyId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByRemote(string $remoteServerUrl, string $remoteToken, int $remoteMessageId): ProxyCacheMessage {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('remote_server_url', $query->createNamedParameter($remoteServerUrl, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('remote_token', $query->createNamedParameter($remoteToken, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('remote_message_id', $query->createNamedParameter($remoteMessageId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	public function deleteExpiredMessages(\DateTimeInterface $dateTime): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->isNotNull('expire_datetime'))
			->andWhere($query->expr()->lte('expire_datetime', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE)));

		return $query->executeStatement();
	}
}
