<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\BackgroundJob;

use OCA\Talk\BackgroundJob\ResyncTeamRoom;
use OCA\Talk\BackgroundJob\ResyncTeamRooms;
use OCA\Talk\BackgroundJob\ResyncTeamRoomsNow;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ResyncTeamRoomsTest extends TestCase {
	protected ITimeFactory&MockObject $timeFactory;
	protected TeamRoomSyncService&MockObject $syncService;
	protected IJobList&MockObject $jobList;

	public function setUp(): void {
		parent::setUp();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->syncService = $this->createMock(TeamRoomSyncService::class);
		$this->jobList = $this->createMock(IJobList::class);
	}

	public function testHourlyJobOnlyStartsAPass(): void {
		$this->syncService->expects($this->once())->method('queueFullResync');
		$this->syncService->expects($this->never())->method('resyncFrom');

		$job = new ResyncTeamRooms($this->timeFactory, $this->syncService);
		self::invokePrivate($job, 'run', [null]);
	}

	public static function dataPass(): array {
		return [
			'more rooms left: continue from the cursor' => [0, 42, ['afterId' => 42]],
			'continued pass' => [42, 84, ['afterId' => 84]],
			'pass done' => [42, 0, null],
			'teams unavailable: the hourly job starts again' => [42, null, null],
		];
	}

	#[DataProvider('dataPass')]
	public function testPassContinuesWithItsCursor(int $afterId, ?int $cursor, ?array $next): void {
		$this->syncService->expects($this->once())->method('resyncFrom')->with($afterId, $this->anything())->willReturn($cursor);
		if ($next === null) {
			$this->jobList->expects($this->never())->method('add');
		} else {
			$this->jobList->expects($this->once())->method('add')->with(ResyncTeamRoomsNow::class, $next);
		}

		$job = new ResyncTeamRoomsNow($this->timeFactory, $this->syncService, $this->jobList);
		self::invokePrivate($job, 'run', [['afterId' => $afterId]]);
	}

	public function testResyncOfOneTeamRoom(): void {
		$room = $this->createMock(Room::class);
		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomByObject')->with(Room::OBJECT_TYPE_TEAM, 'team1')->willReturn($room);
		$this->syncService->expects($this->once())->method('resyncRoom')->with($room, true);

		$job = new ResyncTeamRoom($this->timeFactory, $manager, $this->syncService, $this->createMock(LoggerInterface::class));
		self::invokePrivate($job, 'run', [['teamId' => 'team1']]);
	}

	public function testResyncOfADeletedTeamRoomIsSkipped(): void {
		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomByObject')->willThrowException(new RoomNotFoundException());
		$this->syncService->expects($this->never())->method('resyncRoom');

		$job = new ResyncTeamRoom($this->timeFactory, $manager, $this->syncService, $this->createMock(LoggerInterface::class));
		self::invokePrivate($job, 'run', [['teamId' => 'team1']]);
	}

	public function testFailingResyncOfOneTeamRoomIsLogged(): void {
		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomByObject')->willReturn($this->createMock(Room::class));
		$this->syncService->method('resyncRoom')->willThrowException(new \Exception('Circles unavailable'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('info');

		$job = new ResyncTeamRoom($this->timeFactory, $manager, $this->syncService, $logger);
		self::invokePrivate($job, 'run', [['teamId' => 'team1']]);
	}
}
