<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\RemoveEmptyRooms;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IUserMountCache;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RemoveEmptyRoomsTest extends TestCase {

	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var Manager|MockObject */
	protected $manager;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var LoggerInterface|MockObject */
	protected $loggerInterface;
	/** @var IUserMountCache|MockObject */
	protected $userMountCache;

	public function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
	}

	public function getBackgroundJob(): RemoveEmptyRooms {
		return new RemoveEmptyRooms(
			$this->timeFactory,
			$this->manager,
			$this->participantService,
			$this->loggerInterface,
			$this->userMountCache
		);
	}

	public function testDoDeleteRoom(): void {
		$backgroundJob = $this->getBackgroundJob();

		$room = $this->createMock(Room::class);
		$room->method('getType')
			->willReturn(Room::TYPE_GROUP);
		$this->invokePrivate($backgroundJob, 'defineRoomToProcces', [$room]);
		$numDeletedRooms = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals(0, $numDeletedRooms);

		$this->invokePrivate($backgroundJob, 'doDeleteRoom', []);

		$numDeletedRooms = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals(1, $numDeletedRooms);

		$this->assertNull($this->invokePrivate($backgroundJob, 'room'));
	}

	/**
	 * @dataProvider dataDeleteIfFileIsRemoved
	 */
	public function testDeleteIfFileIsRemoved(bool $roomExists, string $objectType, array $fileList, int $numDeletedRoomsExpected): void {
		$backgroundJob = $this->getBackgroundJob();

		$numDeletedRoomsActual = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals(0, $numDeletedRoomsActual);

		if ($roomExists) {
			$room = $this->createMock(Room::class);
			$room->method('getType')
				->willReturn(Room::TYPE_GROUP);
			$room->method('getObjectType')
				->willReturn($objectType);
			$this->invokePrivate($backgroundJob, 'defineRoomToProcces', [$room]);
		}
		$userMountCache = $this->invokePrivate($backgroundJob, 'userMountCache');
		$userMountCache->method('getMountsForFileId')
			->willReturn($fileList);

		$this->invokePrivate($backgroundJob, 'deleteIfFileIsRemoved');

		$numDeletedRoomsActual = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals($numDeletedRoomsExpected, $numDeletedRoomsActual);
	}

	public function dataDeleteIfFileIsRemoved(): array {
		return [
			[false, '', [], 0],
			[true, 'email', [], 0],
			[true, 'file', ['fileExists'], 0],
			[true, 'file', [], 1],
		];
	}

	/**
	 * @dataProvider dataDeleteIfIsEmpty
	 */
	public function testDeleteIfIsEmpty(bool $roomExists, string $objectType, int $actorsCount, int $numDeletedRoomsExpected): void {
		$backgroundJob = $this->getBackgroundJob();

		$numDeletedRoomsActual = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals(0, $numDeletedRoomsActual);

		if ($roomExists) {
			$room = $this->createMock(Room::class);
			$room->method('getType')
				->willReturn(Room::TYPE_GROUP);
			$room->method('getObjectType')
				->willReturn($objectType);
			$this->invokePrivate($backgroundJob, 'defineRoomToProcces', [$room]);
		}
		$participantService = $this->invokePrivate($backgroundJob, 'participantService');
		$participantService->method('getNumberOfActors')
			->willReturn($actorsCount);

		$this->invokePrivate($backgroundJob, 'deleteIfIsEmpty');

		$numDeletedRoomsActual = $this->invokePrivate($backgroundJob, 'numDeletedRooms');
		$this->assertEquals($numDeletedRoomsExpected, $numDeletedRoomsActual);
	}

	public function dataDeleteIfIsEmpty() {
		return [
			[false, '', 1, 0],
			[true, 'file', 1, 0],
			[true, 'email', 1, 0],
			[true, 'email', 0, 1]
		];
	}
	/**
	 * @dataProvider dataDefineRoomToProcces
	 */
	public function testDefineRoomToProcces(int $roomType, bool $roomCanBeDeleted): void {
		$backgroundJob = $this->getBackgroundJob();

		$room = $this->createMock(Room::class);
		$room->method('getType')
			->willReturn($roomType);
		$this->invokePrivate($backgroundJob, 'defineRoomToProcces', [$room]);
		$actual = $this->invokePrivate($backgroundJob, 'room');

		if ($roomCanBeDeleted) {
			$this->assertEquals($room, $actual);
		} else {
			$this->assertNull($actual);
		}
	}

	public function dataDefineRoomToProcces(): array {
		return [
			[Room::TYPE_GROUP, true],
			[Room::TYPE_CHANGELOG, false],
		];
	}
}
