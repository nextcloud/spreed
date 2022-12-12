<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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
 * @method Attachment mapRowToEntity(array $row)
 * @method Attachment findEntity(IQueryBuilder $query)
 * @method Attachment[] findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<Attachment>
 */
class AttachmentMapper extends QBMapper {
	use TTransactional;

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_attachments', Attachment::class);
	}

	public function createAttachmentFromRow(array $row): Attachment {
		return $this->mapRowToEntity([
			'id' => (int) $row['id'],
			'room_id' => (int) $row['room_id'],
			'message_id' => (int) $row['message_id'],
			'message_time' => (int) $row['message_time'],
			'object_type' => (string) $row['object_type'],
			'actor_type' => (string) $row['actor_type'],
			'actor_id' => (string) $row['actor_id'],
		]);
	}

	/**
	 * @param int $roomId
	 * @param string $objectType
	 * @param int $offset
	 * @param int $limit
	 * @return Attachment[]
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

		$this->atomic(static function () use ($query) {
			$query->executeStatement();
		}, $this->db);
	}
}
