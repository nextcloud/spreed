<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\CleanupStaleSessions;
use OCA\Talk\Model\SessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CleanupStaleSessionsTest extends TestCase {
	protected ITimeFactory&MockObject $timeFactory;
	protected SessionMapper&MockObject $sessionMapper;
	protected LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function getBackgroundJob(): CleanupStaleSessions {
		return new CleanupStaleSessions(
			$this->timeFactory,
			$this->sessionMapper,
			$this->logger,
		);
	}

	public function testNothingToDelete(): void {
		$now = 1_700_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->sessionMapper->expects($this->once())
			->method('deleteByLastPingBefore')
			->with($now - 24 * 3600)
			->willReturn(0);

		$this->sessionMapper->expects($this->once())
			->method('findSessionIdsWithoutAttendee')
			->with(1000)
			->willReturn([]);

		$this->sessionMapper->expects($this->never())
			->method('deleteByIds');

		$this->logger->expects($this->never())
			->method('info');

		self::invokePrivate($this->getBackgroundJob(), 'run', [null]);
	}

	public function testDeleteOnlyByLastPing(): void {
		$now = 1_700_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->sessionMapper->expects($this->once())
			->method('deleteByLastPingBefore')
			->with($now - 24 * 3600)
			->willReturn(7);

		$this->sessionMapper->expects($this->once())
			->method('findSessionIdsWithoutAttendee')
			->with(1000)
			->willReturn([]);

		$this->sessionMapper->expects($this->never())
			->method('deleteByIds');

		$this->logger->expects($this->once())
			->method('info');

		self::invokePrivate($this->getBackgroundJob(), 'run', [null]);
	}

	public function testDeleteOrphansSingleBatch(): void {
		$now = 1_700_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->sessionMapper->expects($this->once())
			->method('deleteByLastPingBefore')
			->with($now - 24 * 3600)
			->willReturn(0);

		$ids = range(1, 5);
		$this->sessionMapper->expects($this->once())
			->method('findSessionIdsWithoutAttendee')
			->with(1000)
			->willReturn($ids);

		$this->sessionMapper->expects($this->once())
			->method('deleteByIds')
			->with($ids)
			->willReturn(5);

		$this->logger->expects($this->once())
			->method('info');

		self::invokePrivate($this->getBackgroundJob(), 'run', [null]);
	}

	public function testDeleteOrphansMultipleBatches(): void {
		$now = 1_700_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->sessionMapper->expects($this->once())
			->method('deleteByLastPingBefore')
			->with($now - 24 * 3600)
			->willReturn(0);

		$fullBatch = range(1, 1000);
		$lastBatch = range(1001, 1003);

		$this->sessionMapper->expects($this->exactly(2))
			->method('findSessionIdsWithoutAttendee')
			->with(1000)
			->willReturnOnConsecutiveCalls($fullBatch, $lastBatch);

		$matcher = $this->exactly(2);
		$this->sessionMapper->expects($matcher)
			->method('deleteByIds')
			->willReturnCallback(function (array $ids) use ($matcher, $fullBatch, $lastBatch): int {
				if ($matcher->numberOfInvocations() === 1) {
					$this->assertSame($fullBatch, $ids);
					return 1000;
				}
				$this->assertSame($lastBatch, $ids);
				return 3;
			});

		self::invokePrivate($this->getBackgroundJob(), 'run', [null]);
	}
}
