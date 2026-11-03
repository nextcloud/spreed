<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\LockInactiveRooms;
use OCA\Talk\Config;
use OCA\Talk\Import\ImportRefMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class LockInactiveRoomsTest extends TestCase {
	protected ITimeFactory&MockObject $timeFactory;
	protected RoomService&MockObject $roomService;
	private Config&MockObject $appConfig;
	protected LoggerInterface&MockObject $logger;
	protected ImportRefMapper&MockObject $importRefMapper;
	private LockInactiveRooms $job;

	public function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->appConfig = $this->createMock(Config::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->importRefMapper = $this->createMock(ImportRefMapper::class);
		$this->job = new LockInactiveRooms($this->timeFactory,
			$this->roomService,
			$this->appConfig,
			$this->logger,
			$this->importRefMapper,
		);
	}

	public function testImportedRoomsAreInactiveSinceTheImportLastWrote(): void {
		$now = 1_800_000_000;
		$days = 30;
		$rooms = [
			$this->createConfiguredMock(Room::class, ['getId' => 1, 'getReadOnly' => 0, 'getType' => Room::TYPE_GROUP]),
			$this->createConfiguredMock(Room::class, ['getId' => 2, 'getReadOnly' => 0, 'getType' => Room::TYPE_GROUP]),
			$this->createConfiguredMock(Room::class, ['getId' => 3, 'getReadOnly' => 0, 'getType' => Room::TYPE_ONE_TO_ONE]),
		];

		$this->appConfig->method('getInactiveLockTime')->willReturn($days);
		$this->appConfig->method('enableLobbyOnLockedRooms')->willReturn(false);
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('@' . ($now - $days * 86400)));
		$this->roomService->method('getInactiveRooms')->willReturn($rooms);
		// Room 2 was switched over yesterday, room 3 two months ago, room 1 is not imported
		$this->importRefMapper->expects(self::once())
			->method('getLastWrites')
			->with([1, 2, 3])
			->willReturn([2 => $now - 86400, 3 => $now - 60 * 86400]);

		$locked = [];
		$this->roomService->expects(self::exactly(2))
			->method('setReadOnly')
			->willReturnCallback(function (Room $room, int $state) use (&$locked): bool {
				$locked[] = $room->getId();
				return true;
			});

		$this->job->run('t');
		$this->assertSame([1, 3], $locked);
	}

	public function testNotEnabled(): void {
		$this->appConfig->expects(self::once())
			->method('getInactiveLockTime')
			->willReturn(0);
		$this->appConfig->expects(self::once())
			->method('enableLobbyOnLockedRooms')
			->willReturn(false);
		$this->timeFactory->expects(self::never())
			->method(self::anything());
		$this->roomService->expects(self::never())
			->method(self::anything());
		$this->logger->expects(self::never())
			->method(self::anything());

		$this->job->run('t');
	}

	public function testNoRooms(): void {
		$this->appConfig->expects(self::once())
			->method('getInactiveLockTime')
			->willReturn(123);
		$this->appConfig->expects(self::once())
			->method('enableLobbyOnLockedRooms')
			->willReturn(false);
		$this->timeFactory->expects(self::once())
			->method('getTime');
		$this->timeFactory->expects(self::once())
			->method('getDateTime');
		$this->roomService->expects(self::once())
			->method('getInactiveRooms')
			->willReturn([]);
		$this->roomService->expects(self::never())
			->method('setReadOnly');
		$this->roomService->expects(self::never())
			->method('setLobby');
		$this->logger->expects(self::never())
			->method(self::anything());

		$this->job->run('t');

	}

	public function testLockRooms(): void {
		$rooms = [
			$this->createConfiguredMock(Room::class, [
				'getReadOnly' => 0,
				'getType' => Room::TYPE_PUBLIC,
			]),
			$this->createConfiguredMock(Room::class, [
				'getReadOnly' => 0,
				'getType' => Room::TYPE_GROUP,
			]),
		];

		$this->appConfig->expects(self::once())
			->method('getInactiveLockTime')
			->willReturn(123);
		$this->appConfig->expects(self::once())
			->method('enableLobbyOnLockedRooms')
			->willReturn(false);
		$this->timeFactory->expects(self::once())
			->method('getTime');
		$this->timeFactory->expects(self::once())
			->method('getDateTime');
		$this->roomService->expects(self::once())
			->method('getInactiveRooms')
			->willReturn($rooms);
		$this->roomService->expects(self::exactly(2))
			->method('setReadOnly');
		$this->roomService->expects(self::never())
			->method('setLobby');
		$this->logger->expects(self::exactly(2))
			->method('debug');

		$this->job->run('t');

	}

	public function testLockRoomsAndEnableLobby(): void {
		$rooms = [
			$this->createConfiguredMock(Room::class, [
				'getReadOnly' => 0,
				'getType' => Room::TYPE_PUBLIC,
			]),
			$this->createConfiguredMock(Room::class, [
				'getReadOnly' => 0,
				'getType' => Room::TYPE_GROUP,
			]),
		];

		$this->appConfig->expects(self::once())
			->method('getInactiveLockTime')
			->willReturn(123);
		$this->appConfig->expects(self::once())
			->method('enableLobbyOnLockedRooms')
			->willReturn(true);
		$this->timeFactory->expects(self::once())
			->method('getTime');
		$this->timeFactory->expects(self::any())
			->method('getDateTime');
		$this->roomService->expects(self::once())
			->method('getInactiveRooms')
			->willReturn($rooms);
		$this->roomService->expects(self::exactly(2))
			->method('setReadOnly');
		$this->roomService->expects(self::exactly(2))
			->method('setLobby');
		$this->logger->expects(self::exactly(4))
			->method('debug');

		$this->job->run('t');
	}
}
