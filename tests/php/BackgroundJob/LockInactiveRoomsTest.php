<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\LockInactiveRooms;
use OCA\Talk\Config;
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
	private LockInactiveRooms $job;

	public function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->appConfig = $this->createMock(Config::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->job = new LockInactiveRooms($this->timeFactory,
			$this->roomService,
			$this->appConfig,
			$this->logger
		);
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
