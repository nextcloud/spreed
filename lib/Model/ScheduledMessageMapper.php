<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ScheduledMessage mapRowToEntity(array $row)
 * @method ScheduledMessage findEntity(IQueryBuilder $query)
 * @method list<ScheduledMessage> findEntities(IQueryBuilder $query)
 * @method ScheduledMessage update(ScheduledMessage $scheduledMessage)
 * @method ScheduledMessage delete(ScheduledMessage $scheduledMessage)
 * @template-extends QBMapper<ScheduledMessage>
 */
class ScheduledMessageMapper extends QBMapper {
	use TTransactional;

	public function __construct(
		IDBConnection $db,
	) {
		parent::__construct($db, 'talk_scheduled_messages', ScheduledMessage::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findById(Room $chat, int $messageId): ScheduledMessage {
		if (!$chat->isFederatedConversation()) {
			throw new InvalidRoomException('Can not call ProxyCacheMessageMapper::findById() with a non-federated chat.');
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($query);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function deleteInvalidMessages(Room $room): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->andWhere($query->expr()->eq('room_token', $query->createNamedParameter($room->getToken(), IQueryBuilder::PARAM_STR)));

		return $query->executeStatement();
	}
}
