<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method BotServer mapRowToEntity(array $row)
 * @method BotServer findEntity(IQueryBuilder $query)
 * @method list<BotServer> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<BotServer>
 */
class BotServerMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_bots_server', BotServer::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(int $botId): BotServer {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($botId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByUrlAndSecret(string $url, string $secret): BotServer {
		$urlHash = sha1($url);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('url_hash', $query->createNamedParameter($urlHash)))
			->andWhere($query->expr()->eq('secret', $query->createNamedParameter($secret)));

		return $this->findEntity($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByUrl(string $url): BotServer {
		return $this->findByUrlHash(sha1($url));
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByUrlHash(string $urlHash): BotServer {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('url_hash', $query->createNamedParameter($urlHash)));

		return $this->findEntity($query);
	}

	public function deleteById(int $botId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($botId, IQueryBuilder::PARAM_INT)));

		return $query->executeStatement();
	}

	/**
	 * @return list<BotServer>
	 */
	public function findByIds(array $botIds): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->in('id', $query->createNamedParameter($botIds, IQueryBuilder::PARAM_INT_ARRAY)));

		return $this->findEntities($query);
	}

	/**
	 * @return list<BotServer>
	 */
	public function getAllBots(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName());

		return $this->findEntities($query);
	}
}
