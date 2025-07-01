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

	/**
	 * @param list<int> $threadIds
	 * @return array<int, ThreadAttendee> Key is the thread id
	 */
	public function findAttendeeByThreadIds(Attendee $attendee, array $threadIds): array {
		$attendees = $this->threadAttendeeMapper->findAttendeeByThreadIds($attendee->getId(), $threadIds);
		$threadAttendees = [];
		foreach ($attendees as $threadAttendee) {
			$threadAttendees[$threadAttendee->getThreadId()] = $threadAttendee;
		}

		return $threadAttendees;
	}

	public function addAttendeeToThread(Attendee $attendee, Thread $thread): ThreadAttendee {
		$threadAttendee = new ThreadAttendee();
		$threadAttendee->setThreadId($thread->getId());
		$threadAttendee->setRoomId($thread->getRoomId());

		$threadAttendee->setAttendeeId($attendee->getId());
		$threadAttendee->setActorType($attendee->getActorType());
		$threadAttendee->setActorId($attendee->getActorId());
		$threadAttendee->setNotificationLevel($attendee->getNotificationLevel());
		$threadAttendee->setReadPrivacy($attendee->getReadPrivacy());

		// We only copy the read marker for now.
		// If we copied the last mention and direct ids as well, all threads
		// created would be marked as unread with a mention,
		// when the conversation had an unread mention.
		$threadAttendee->setLastReadMessage($attendee->getLastReadMessage());

		try {
			$this->threadAttendeeMapper->insert($threadAttendee);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
		}

		return $threadAttendee;
	}

	/**
	 * Used e.g. when a user or group is removed from a conversation
	 * @param list<int> $attendeeIds
	 */
	public function removeThreadAttendeesByAttendeeIds(array $attendeeIds): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('talk_thread_attendees')
			->where($query->expr()->in(
				'attendee_id',
				$query->createNamedParameter($attendeeIds, IQueryBuilder::PARAM_INT_ARRAY)
			));
		$query->executeStatement();
	}

	public function updateLastMessageInfoAfterReply(Thread $thread, int $lastMessageId): void {
		$query = $this->connection->getQueryBuilder();
		$query->update('talk_threads')
			->set('num_replies', $query->func()->add('num_replies', $query->expr()->literal(1)))
			->set('last_message_id', $query->createNamedParameter($lastMessageId))
			->where($query->expr()->eq('id', $query->createNamedParameter($thread->getId())));
		$query->executeStatement();

		$thread->setNumReplies($thread->getNumReplies() + 1);
		$thread->setLastMessageId($lastMessageId);
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

	public function validateThreadIds(array $potentialThreadIds): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_threads')
			->where($query->expr()->in(
				'id',
				$query->createNamedParameter($potentialThreadIds, IQueryBuilder::PARAM_INT_ARRAY),
			));

		$ids = [];

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();

		return $ids;
	}

	public function validateThread(int $potentialThreadId): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_threads')
			->where($query->expr()->eq(
				'id',
				$query->createNamedParameter($potentialThreadId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT)
			);

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row !== false;
	}
}
