<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Attachment mapRowToEntity(array $row)
 * @method Attachment findEntity(IQueryBuilder $query)
 * @method list<Attachment> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Attachment>
 */
class AttachmentMapper extends QBMapper {
	use TTransactional;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_attachments', Attachment::class);
	}

	public function createAttachmentFromRow(array $row): Attachment {
		return $this->mapRowToEntity([
			'id' => (int)$row['id'],
			'room_id' => (int)$row['room_id'],
			'message_id' => (int)$row['message_id'],
			'message_time' => (int)$row['message_time'],
			'object_type' => (string)$row['object_type'],
			'actor_type' => (string)$row['actor_type'],
			'actor_id' => (string)$row['actor_id'],
		]);
	}

	/**
	 * @return list<Attachment>
	 * @throws \OCP\DB\Exception
	 */
	public function getAttachmentsByType(int $roomId, string $objectType, int $offset, int $limit): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->setMaxResults($limit)
			->orderBy('id', 'DESC');

		if ($offset > 0) {
			$query->andWhere($query->expr()->lt('message_id', $query->createNamedParameter($offset)));
		}

		return $this->findEntities($query);
	}

	public function deleteByMessageId(int $messageId): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('message_id', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}

	public function deleteByRoomId(int $roomId): void {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$this->atomic(static function () use ($query): void {
			$query->executeStatement();
		}, $this->db);
	}
}
