<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadAttendee;
use OCA\Talk\Model\ThreadAttendeeMapper;
use OCA\Talk\Model\ThreadMapper;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ThreadService {

	public function __construct(
		protected IDBConnection $connection,
		protected ThreadMapper $threadMapper,
		protected ThreadAttendeeMapper $threadAttendeeMapper,
	) {
	}

	public function createThread(Room $room, int $threadId): Thread {
		$info = $this->getThreadInfoFromDatabase($threadId);

		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setNumReplies($info['num_replies']);
		$thread->setLastMessageId($info['last_message_id']);
		$thread->setRoomId($room->getId());

		try {
			$this->threadMapper->insert($thread);
		} catch (\OCP\DB\Exception $e) {
			// FIXME catch only unique constraint violation on primary key
			if ($e->getReason() !== \OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}

		return $thread;
	}

	/**
	 * @param non-negative-int $threadId
	 * @throws DoesNotExistException
	 */
	public function findByThreadId(int $threadId): Thread {
		return $this->threadMapper->findById($threadId);
	}

	/**
	 * @param int<1, 50> $limit
	 * @param non-negative-int $offsetId
	 * @return list<Thread>
	 */
	public function findByRoom(Room $room, int $limit, int $offsetId = 0): array {
		$limit = min(50, max(1, $limit));
		return $this->threadMapper->findByRoomId($room->getId(), $limit, $offsetId);
	}

	public function addAttendeeToThread(Attendee $attendee, Thread $thread): void {
		$threadAttendee = new ThreadAttendee();
		$threadAttendee->setThreadId($thread->getId());
		$threadAttendee->setRoomId($thread->getRoomId());

		$threadAttendee->setAttendeeId($attendee->getId());
		$threadAttendee->setActorType($attendee->getActorType());
		$threadAttendee->setActorId($attendee->getActorId());
		$threadAttendee->setNotificationLevel($attendee->getNotificationLevel());
		$threadAttendee->setReadPrivacy($attendee->getReadPrivacy());

		try {
			$this->threadAttendeeMapper->insert($threadAttendee);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}
	}

	public function deleteByRoom(Room $room): void {
		$this->threadMapper->deleteByRoomId($room->getId());
		$this->threadAttendeeMapper->deleteByRoomId($room->getId());
	}

	/**
	 * @param int $threadId
	 * @return array{num_replies: int, last_message_id: int}
	 */
	protected function getThreadInfoFromDatabase(int $threadId): array {
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_replies'))
			->selectAlias($query->func()->max('id'), 'last_message_id')
			->from('comments')
			->where($query->expr()->eq('topmost_parent_id', $query->createNamedParameter($threadId)));
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return [
			'num_replies' => $row['num_replies'] ?? 0,
			'last_message_id' => $row['last_message_id'] ?? 0,
		];
	}
}
