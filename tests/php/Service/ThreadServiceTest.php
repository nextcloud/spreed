<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadAttendee;
use OCA\Talk\Model\ThreadAttendeeMapper;
use OCA\Talk\Model\ThreadMapper;
use OCA\Talk\Participant;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class ThreadServiceTest extends TestCase {
	protected ThreadMapper $threadMapper;
	protected ThreadAttendeeMapper $threadAttendeeMapper;
	protected ThreadService $service;
	protected int $roomId;

	public function setUp(): void {
		parent::setUp();

		$connection = Server::get(IDBConnection::class);
		$this->threadMapper = new ThreadMapper($connection);
		$this->threadAttendeeMapper = new ThreadAttendeeMapper($connection);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')
			->willReturn($this->createMock(ICache::class));

		$this->service = new ThreadService(
			$connection,
			$this->threadMapper,
			$this->threadAttendeeMapper,
			$this->createMock(ITimeFactory::class),
			$cacheFactory,
		);
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		$this->threadAttendeeMapper->deleteByRoomId($this->roomId);
		$this->threadMapper->deleteByRoomId($this->roomId);
		parent::tearDown();
	}

	protected function createSubscribedThread(int $threadId, \DateTime $lastActivity, string $actorId, int $lastMessageId = 0): void {
		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setRoomId($this->roomId);
		$thread->setName('Thread ' . $threadId);
		$thread->setLastActivity($lastActivity);
		$thread->setLastMessageId($lastMessageId);
		$this->threadMapper->insert($thread);

		$attendee = new ThreadAttendee();
		$attendee->setRoomId($this->roomId);
		$attendee->setThreadId($threadId);
		$attendee->setAttendeeId($threadId);
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId($actorId);
		$attendee->setNotificationLevel(Participant::NOTIFY_DEFAULT);
		$this->threadAttendeeMapper->insert($attendee);
	}

	public function testGetRecentByActorOrdersSameActivityByNewestMessage(): void {
		$actorId = 'thread-order-' . $this->roomId;
		$base = $this->roomId * 10;
		$sameSecond = new \DateTime('2026-01-01 12:00:00');
		$this->createSubscribedThread($base + 2, $sameSecond, $actorId);
		$this->createSubscribedThread($base + 1, $sameSecond, $actorId, $base + 9);
		$this->createSubscribedThread($base + 3, $sameSecond, $actorId);
		$this->createSubscribedThread($base + 4, new \DateTime('2026-01-01 11:00:00'), $actorId);

		$results = $this->service->getRecentByActor(Attendee::ACTOR_USERS, $actorId, 10, 0);

		$this->assertSame(
			[$base + 1, $base + 3, $base + 2, $base + 4],
			array_map(static fn (array $entry): int => $entry['thread']->getId(), $results[$this->roomId]),
		);
	}
}
