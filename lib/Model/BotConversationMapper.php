<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method BotConversation mapRowToEntity(array $row)
 * @method BotConversation findEntity(IQueryBuilder $query)
 * @method list<BotConversation> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<BotConversation>
 */
class BotConversationMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_bots_conversation', BotConversation::class);
	}

	/**
	 * @return list<BotConversation>
	 */
	public function findForToken(string $token): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		return $this->findEntities($query);
	}

	public function deleteByBotId(int $botId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('bot_id', $query->createNamedParameter($botId, IQueryBuilder::PARAM_INT)));

		return $query->executeStatement();
	}

	public function deleteByBotIdAndTokens(int $botId, array $tokens): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('bot_id', $query->createNamedParameter($botId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('token', $query->createNamedParameter($tokens, IQueryBuilder::PARAM_STR_ARRAY)));

		return $query->executeStatement();
	}
}
