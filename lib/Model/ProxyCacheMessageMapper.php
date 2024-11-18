<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ProxyCacheMessage mapRowToEntity(array $row)
 * @method ProxyCacheMessage findEntity(IQueryBuilder $query)
 * @method list<ProxyCacheMessage> findEntities(IQueryBuilder $query)
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
	public function findById(Room $chat, int $proxyId): ProxyCacheMessage {
		if (!$chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ProxyCacheMessageMapper::findById() with a non-federated chat.');
		}

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
			->where($query->expr()->isNotNull('expiration_datetime'))
			->andWhere($query->expr()->lte('expiration_datetime', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE)));

		return $query->executeStatement();
	}
}
