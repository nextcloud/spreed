<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Room;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ManagerTeamRoomTest extends TestCase {
	protected Manager&MockObject $manager;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()
			->onlyMethods(['getRoomByObject', 'getRoomByToken'])
			->getMock();
	}

	private function makeRoom(bool $isTeamManaged, string $objectType = '', string $objectId = ''): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('isTeamManaged')->willReturn($isTeamManaged);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('getObjectId')->willReturn($objectId);
		return $room;
	}

	public function testTeamRoomOfTheTeam(): void {
		$teamRoom = $this->makeRoom(true, Room::OBJECT_TYPE_TEAM, 'team1');
		$this->manager->method('getRoomByObject')->with(Room::OBJECT_TYPE_TEAM, 'team1')->willReturn($teamRoom);

		$this->assertSame($teamRoom, $this->manager->getTeamRoom('team1'));
	}

	public function testTeamWithoutRoom(): void {
		$this->manager->method('getRoomByObject')->willThrowException(new RoomNotFoundException());

		$this->assertNull($this->manager->getTeamRoom('team1'));
	}

	public function testTeamRoomOfATeamRoomIsItself(): void {
		$teamRoom = $this->makeRoom(true, Room::OBJECT_TYPE_TEAM, 'team1');
		$this->manager->expects($this->never())->method('getRoomByToken');

		$this->assertSame($teamRoom, $this->manager->getTeamRoomOf($teamRoom));
	}

	public function testTeamRoomOfABreakoutRoomIsItsParent(): void {
		$teamRoom = $this->makeRoom(true, Room::OBJECT_TYPE_TEAM, 'team1');
		$this->manager->method('getRoomByToken')->with('parenttoken')->willReturn($teamRoom);

		$this->assertSame($teamRoom, $this->manager->getTeamRoomOf($this->makeRoom(false, BreakoutRoom::PARENT_OBJECT_TYPE, 'parenttoken')));
	}

	public function testBreakoutRoomOfAnotherConversationHasNoTeamRoom(): void {
		$this->manager->method('getRoomByToken')->willReturn($this->makeRoom(false));

		$this->assertNull($this->manager->getTeamRoomOf($this->makeRoom(false, BreakoutRoom::PARENT_OBJECT_TYPE, 'parenttoken')));
	}

	public function testBreakoutRoomOfADeletedConversationHasNoTeamRoom(): void {
		$this->manager->method('getRoomByToken')->willThrowException(new RoomNotFoundException());

		$this->assertNull($this->manager->getTeamRoomOf($this->makeRoom(false, BreakoutRoom::PARENT_OBJECT_TYPE, 'parenttoken')));
	}

	public function testOtherRoomsHaveNoTeamRoom(): void {
		$this->manager->expects($this->never())->method('getRoomByToken');

		$this->assertNull($this->manager->getTeamRoomOf($this->makeRoom(false, 'file', '42')));
	}
}
