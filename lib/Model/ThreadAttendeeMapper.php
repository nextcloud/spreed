<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method ThreadAttendee mapRowToEntity(array $row)
 * @method ThreadAttendee findEntity(IQueryBuilder $query)
 * @method list<ThreadAttendee> findEntities(IQueryBuilder $query)
 * @template-extends QBMapper<ThreadAttendee>
 */
class ThreadAttendeeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_thread_attendees', ThreadAttendee::class);
	}

	public function deleteByRoomId(int $roomId): int {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->getTableName())
			->where($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT,
			));

		return $query->executeStatement();
	}

	public function createAttendeeFromRow(array $row): ThreadAttendee {
		return $this->mapRowToEntity([
			'id' => (int)$row['a_id'],
			'room_id' => (int)$row['room_id'],
			'thread_id' => (int)$row['thread_id'],
			'attendee_id' => (int)$row['attendee_id'],
			'actor_type' => $row['actor_type'],
			'actor_id' => $row['actor_id'],
			'notification_level' => (int)$row['notification_level'],
			'last_read_message' => (int)$row['last_read_message'],
			'last_mention_message' => (int)$row['last_mention_message'],
			'last_mention_direct' => (int)$row['last_mention_direct'],
			'read_privacy' => (int)$row['read_privacy'],
		]);
	}
}
