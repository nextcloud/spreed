<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Model;

use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadMapper;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;

#[Group('DB')]
class ThreadMapperTest extends TestCase {
	protected ThreadMapper $threadMapper;
	protected int $roomId;

	public function setUp(): void {
		parent::setUp();

		$this->threadMapper = new ThreadMapper(Server::get(IDBConnection::class));
		$this->roomId = random_int(1_000_000, 9_000_000);
	}

	public function tearDown(): void {
		$this->threadMapper->deleteByRoomId($this->roomId);
		parent::tearDown();
	}

	protected function createThread(int $threadId, \DateTime $lastActivity, int $lastMessageId = 0): void {
		$thread = new Thread();
		$thread->setId($threadId);
		$thread->setRoomId($this->roomId);
		$thread->setName('Thread ' . $threadId);
		$thread->setLastActivity($lastActivity);
		$thread->setLastMessageId($lastMessageId);
		$this->threadMapper->insert($thread);
	}

	public function testGetRecentByRoomIdOrdersSameActivityByNewestMessage(): void {
		$base = $this->roomId * 10;
		$sameSecond = new \DateTime('2026-01-01 12:00:00');
		$this->createThread($base + 2, $sameSecond);
		$this->createThread($base + 1, $sameSecond, $base + 9);
		$this->createThread($base + 3, $sameSecond);
		$this->createThread($base + 4, new \DateTime('2026-01-01 11:00:00'));

		$threads = $this->threadMapper->getRecentByRoomId($this->roomId, 10);

		$this->assertSame(
			[$base + 1, $base + 3, $base + 2, $base + 4],
			array_map(static fn (Thread $thread): int => $thread->getId(), $threads),
		);
	}
}
