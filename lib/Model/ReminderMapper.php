<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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
 * @method Reminder mapRowToEntity(array $row)
 * @method Reminder findEntity(IQueryBuilder $query)
 * @method Reminder[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Reminder>
 */
class ReminderMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_reminders', Reminder::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findForUserAndMessage(string $userId, string $token, int $messageId): Reminder {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('message_id', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @return Reminder[]
	 */
	public function findRemindersToExecute(\DateTime $dateTime): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->lt('date_time', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));

		return $this->findEntities($query);
	}

	public function deleteExecutedReminders(\DateTime $dateTime): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->lt('date_time', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE), IQueryBuilder::PARAM_DATE));

		$query->executeStatement();
	}
}
