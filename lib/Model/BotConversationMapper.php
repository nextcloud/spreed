<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method BotConversation mapRowToEntity(array $row)
 * @method BotConversation findEntity(IQueryBuilder $query)
 * @method BotConversation[] findEntities(IQueryBuilder $query)
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
	 * @return BotConversation[]
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
