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
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;

class ThreadService {

	private ICache $cache;
	private const CACHE_PREFIX = 'thread/';
	public function __construct(
		protected IDBConnection $connection,
		protected ThreadMapper $threadMapper,
		protected ThreadAttendeeMapper $threadAttendeeMapper,
		protected ITimeFactory $timeFactory,
		protected ICacheFactory $cacheFactory,
	) {
		$this->cache = $this->cacheFactory->createDistributed('talk.threads');
	}

	public function createThread(Room $room, int $threadId, string $title): Thread {
		if (mb_strlen($title) > 203) {
			$title = mb_substr($title, 0, 200) . '…';
		}
		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setName($title);
		$thread->setRoomId($room->getId());
		$thread->setLastActivity($this->timeFactory->getDateTime());
		$thread = $this->threadMapper->insert($thread);

		$this->cache->set(self::CACHE_PREFIX . $room->getId() . '/' . $threadId, $thread->toJson(), 60 * 15);

		return $thread;
	}

	/**
	 * @param non-negative-int $roomId
	 * @param non-negative-int $threadId
	 * @throws DoesNotExistException
	 */
	public function findByThreadId(int $roomId, int $threadId): Thread {
		$row = $this->cache->get(self::CACHE_PREFIX . $roomId . '/' . $threadId);
		if (!empty($row)) {
			return Thread::fromJson($row);
		}

		// We already looked for a thread with this id, and we didn't find anything
		if ($row === '') {
			throw new DoesNotExistException('No thread found');
		}

		try {
			$thread = $this->threadMapper->findById($roomId, $threadId);
			$this->cache->set(self::CACHE_PREFIX . $roomId . '/' . $threadId, $thread->toJson(), 60 * 15);
		} catch (DoesNotExistException $e) {
			$this->cache->set(self::CACHE_PREFIX . $roomId . '/' . $threadId, '', 60 * 15);
			throw $e;
		}
		return $thread;

	}

	/**
	 * @param non-negative-int $roomId
	 * @param list<non-negative-int> $threadIds
	 * @return array<int, Thread> Map with thread id as key
	 */
	public function findByThreadIds(int $roomId, array $threadIds): array {
		$threads = $this->threadMapper->findByIds($roomId, $threadIds);
		$result = [];
		foreach ($threads as $thread) {
			$result[$thread->getId()] = $thread;
		}
		return $result;
	}

	/**
	 * @internal Warning: does not check room memberships
	 * @param list<non-negative-int> $threadIds
	 * @return array<int, Thread> Map with room id as key
	 */
	public function preloadThreadsForConversationList(array $threadIds): array {
		if (empty($threadIds)) {
			return [];
		}
		$threads = $this->threadMapper->getForIds($threadIds);
		$result = [];
		foreach ($threads as $thread) {
			$result[$thread->getRoomId()] = $thread;
		}
		return $result;
	}

	/**
	 * @throws \InvalidArgumentException When the title is empty
	 */
	public function renameThread(Thread $thread, string $title): Thread {
		if ($title === '') {
			throw new \InvalidArgumentException('name');
		}
		if (mb_strlen($title) > 203) {
			$title = mb_substr($title, 0, 200) . '…';
		}
		$thread->setName($title);
		$this->threadMapper->update($thread);

		$this->cache->set(self::CACHE_PREFIX . $thread->getRoomId() . '/' . $thread->getId(), $thread->toJson(), 60 * 15);
		return $thread;
	}

	/**
	 * @param int<1, 50> $limit
	 * @return list<Thread>
	 */
	public function getRecentByRoomId(Room $room, int $limit): array {
		$limit = min(50, max(1, $limit));
		return $this->threadMapper->getRecentByRoomId($room->getId(), $limit);
	}

	/**
	 * @param int<1, 100> $limit
	 * @param non-negative-int $offset
	 */
	public function getRecentByActor(string $actorType, string $actorId, int $limit, int $offset): array {
		$limit = min(100, max(1, $limit));

		$query = $this->connection->getQueryBuilder();
		$query->select('a.*', 't.last_message_id', 't.num_replies', 't.last_activity', 't.name')
			->selectAlias('t.id', 't_id')
			->from('talk_thread_attendees', 'a')
			->join('a', 'talk_threads', 't', $query->expr()->andX(
				$query->expr()->eq('a.thread_id', 't.id'),
				$query->expr()->eq('a.room_id', 't.room_id'),
			))
			->where($query->expr()->eq('a.actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($actorId)))
			->andWhere($query->expr()->neq('a.notification_level', $query->createNamedParameter(Participant::NOTIFY_NEVER)))
			// FIXME ORDER BY last_activity and subscription moment of the user for better sorting?
			->orderBy('t.last_activity', 'DESC')
			->setMaxResults($limit);

		if ($offset > 0) {
			$query->setFirstResult($offset);
		}

		$results = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$roomId = (int)$row['room_id'];
			$results[$roomId][] = [
				'thread' => Thread::createFromRow($row),
				'attendee' => ThreadAttendee::createFromRow($row),
			];
		}
		$result->closeCursor();

		return $results;
	}

	/**
	 * @param list<int> $threadIds
	 * @return array<int, ThreadAttendee> Key is the thread id
	 */
	public function findAttendeeByThreadIds(Attendee $attendee, array $threadIds): array {
		$attendees = $this->threadAttendeeMapper->findAttendeeByThreadIds($attendee->getActorType(), $attendee->getActorId(), $attendee->getRoomId(), $threadIds);
		$threadAttendees = [];
		foreach ($attendees as $threadAttendee) {
			$threadAttendees[$threadAttendee->getThreadId()] = $threadAttendee;
		}

		return $threadAttendees;
	}

	/**
	 * @return array<int, ThreadAttendee> Key is the attendee id
	 */
	public function findAttendeesForNotificationByThreadId(int $roomId, int $threadId): array {
		$attendees = $this->threadAttendeeMapper->findAttendeesForNotification($roomId, $threadId);
		$threadAttendees = [];
		foreach ($attendees as $threadAttendee) {
			$threadAttendees[$threadAttendee->getAttendeeId()] = $threadAttendee;
		}

		return $threadAttendees;
	}

	public function setNotificationLevel(Attendee $attendee, int $threadId, int $level): ThreadAttendee {
		try {
			$threadAttendee = $this->threadAttendeeMapper->findAttendeeByThreadId($attendee->getActorType(), $attendee->getActorId(), $attendee->getRoomId(), $threadId);
			$threadAttendee->setNotificationLevel($level);
			$this->threadAttendeeMapper->update($threadAttendee);
		} catch (DoesNotExistException) {
			$threadAttendee = new ThreadAttendee();
			$threadAttendee->setThreadId($threadId);
			$threadAttendee->setRoomId($attendee->getRoomId());

			$threadAttendee->setAttendeeId($attendee->getId());
			$threadAttendee->setActorType($attendee->getActorType());
			$threadAttendee->setActorId($attendee->getActorId());
			$threadAttendee->setNotificationLevel($level);
			$this->threadAttendeeMapper->insert($threadAttendee);
		}

		return $threadAttendee;
	}

	public function ensureIsThreadAttendee(Attendee $attendee, int $threadId): void {
		try {
			$this->threadAttendeeMapper->findAttendeeByThreadId($attendee->getActorType(), $attendee->getActorId(), $attendee->getRoomId(), $threadId);
		} catch (DoesNotExistException) {
			$threadAttendee = new ThreadAttendee();
			$threadAttendee->setThreadId($threadId);
			$threadAttendee->setRoomId($attendee->getRoomId());

			$threadAttendee->setAttendeeId($attendee->getId());
			$threadAttendee->setActorType($attendee->getActorType());
			$threadAttendee->setActorId($attendee->getActorId());
			$threadAttendee->setNotificationLevel(Participant::NOTIFY_DEFAULT);
			$this->threadAttendeeMapper->insert($threadAttendee);
		}
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

	public function updateLastMessageInfoAfterReply(int $threadId, int $lastMessageId, int $roomId): bool {
		$dateTime = $this->timeFactory->getDateTime();

		$query = $this->connection->getQueryBuilder();
		$query->update('talk_threads')
			->set('num_replies', $query->func()->add('num_replies', $query->expr()->literal(1)))
			->set('last_message_id', $query->createNamedParameter($lastMessageId))
			->set('last_activity', $query->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($query->expr()->eq('id', $query->createNamedParameter($threadId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($roomId)));
		$this->cache->remove(self::CACHE_PREFIX . $roomId . '/' . $threadId);
		return (bool)$query->executeStatement();
	}

	public function deleteByRoom(Room $room): void {
		$this->cache->clear(self::CACHE_PREFIX . $room->getId() . '/');
		$this->threadMapper->deleteByRoomId($room->getId());
		$this->threadAttendeeMapper->deleteByRoomId($room->getId());
	}

	public function validateThread(int $roomId, int $potentialThreadId): bool {
		$row = $this->cache->get(self::CACHE_PREFIX . $roomId . '/' . $potentialThreadId);
		if (!empty($row)) {
			return true;
		}

		if ($row === '') {
			return false;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_threads')
			->where($query->expr()->eq(
				'id',
				$query->createNamedParameter($potentialThreadId, IQueryBuilder::PARAM_INT),
				IQueryBuilder::PARAM_INT)
			)
			->andWhere($query->expr()->eq(
				'room_id',
				$query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)
			));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			$this->cache->set(self::CACHE_PREFIX . $roomId . '/' . $potentialThreadId, '', 60 * 15);
		} else {
			$this->cache->set(self::CACHE_PREFIX . $roomId . '/' . $potentialThreadId, $row, 60 * 15);
		}

		return $row !== false;
	}
}
