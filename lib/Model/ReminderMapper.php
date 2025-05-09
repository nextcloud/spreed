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
 * @method Reminder mapRowToEntity(array $row)
 * @method Reminder findEntity(IQueryBuilder $query)
 * @method list<Reminder> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Reminder>
 */
class ReminderMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_reminders', Reminder::class);
	}

	public function findForUser(string $userId, int $limit): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('date_time', 'ASC')
			->setMaxResults($limit);

		return $this->findEntities($query);
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
	 * @return list<Reminder>
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
