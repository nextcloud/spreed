<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Tests\php\Command\Room;

use OCA\Talk\Command\Room\Create;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class CreateTest extends TestCase {
	use TRoomCommandTest;

	/** @var Create */
	private $command;

	/** @var Manager|MockObject */
	private $manager;

	/** @var RoomMockContainer */
	private $roomMockContainer;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->command = new Create($this->manager);

		$this->roomMockContainer = new RoomMockContainer($this);

		$this->registerUserManagerMock();
		$this->registerGroupManagerMock();

		$this->createTestUserMocks();
		$this->createTestGroupMocks();
	}

	public function testMissingArguments(): void {
		$this->manager->expects($this->never())
			->method('createGroupRoom');

		$this->manager->expects($this->never())
			->method('createPublicRoom');

		$this->expectException(ConsoleRuntimeException::class);
		$this->expectExceptionMessage('Not enough arguments (missing: "name").');

		$tester = new CommandTester($this->command);
		$tester->execute([]);
	}

	/**
	 * @dataProvider validProvider
	 */
	public function testValid(array $input, array $expectedRoomData): void {
		$this->manager
			->method('createGroupRoom')
			->willReturnCallback(function (string $name = ''): Room {
				return $this->roomMockContainer->create(['name' => $name, 'type' => Room::GROUP_CALL]);
			});

		$this->manager
			->method('createPublicRoom')
			->willReturnCallback(function (string $name = ''): Room {
				return $this->roomMockContainer->create(['name' => $name, 'type' => Room::PUBLIC_CALL]);
			});

		$tester = new CommandTester($this->command);
		$tester->execute($input);

		$this->assertEquals("Room successfully created.\n", $tester->getDisplay());

		$this->assertEquals($expectedRoomData, $this->roomMockContainer->getRoomData());
	}

	public function validProvider(): array {
		return [
			[
				[
					'name' => 'PHPUnit Test Room',
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--public' => true,
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'type' => Room::PUBLIC_CALL,
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--readonly' => true,
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'readOnly' => Room::READ_ONLY,
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--public' => true,
					'--password' => 'my-secret-password',
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'type' => Room::PUBLIC_CALL,
					'password' => 'my-secret-password',
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1', 'user2'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1', 'user2'],
					'--moderator' => ['user2'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::MODERATOR],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1', 'user2', 'user3'],
					'--moderator' => ['user2', 'user3'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::MODERATOR],
						['userId' => 'user3', 'participantType' => Participant::MODERATOR],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1', 'user2'],
					'--owner' => 'user2',
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::OWNER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1', 'group2'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
						['userId' => 'user3', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1'],
					'--user' => ['user4'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user4', 'participantType' => Participant::USER],
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1'],
					'--moderator' => ['user1'],
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::MODERATOR],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1'],
					'--owner' => 'user1',
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room',
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::OWNER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
		];
	}

	/**
	 * @dataProvider invalidProvider
	 */
	public function testInvalid(array $input, string $expectedOutput): void {
		$this->manager
			->method('createGroupRoom')
			->willReturnCallback(function (string $name = ''): Room {
				return $this->roomMockContainer->create(['name' => $name, 'type' => Room::GROUP_CALL]);
			});

		$this->manager
			->method('createPublicRoom')
			->willReturnCallback(function (string $name = ''): Room {
				return $this->roomMockContainer->create(['name' => $name, 'type' => Room::PUBLIC_CALL]);
			});

		$this->roomMockContainer->registerCallback(function (object $room) {
			/** @var Room|MockObject $room */
			$room->expects($this->once())
				->method('deleteRoom');
		});

		$tester = new CommandTester($this->command);
		$tester->execute($input);

		$this->assertEquals($expectedOutput, $tester->getDisplay());
	}

	public function invalidProvider(): array {
		return [
			[
				[
					'name' => '',
				],
				"Invalid room name.\n",
			],
			[
				[
					'name' => '  ',
				],
				"Invalid room name.\n",
			],
			[
				[
					'name' => str_repeat('x', 256),
				],
				"Invalid room name.\n",
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--password' => 'my-secret-password',
				],
				"Unable to add password protection to private room.\n",
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1','invalid'],
				],
				"User 'invalid' not found.\n",
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--group' => ['group1','invalid'],
				],
				"Group 'invalid' not found.\n",
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1'],
					'--moderator' => ['user2'],
				],
				"User 'user2' is no participant.\n",
			],
			[
				[
					'name' => 'PHPUnit Test Room',
					'--user' => ['user1'],
					'--owner' => 'user2',
				],
				"User 'user2' is no participant.\n",
			],
		];
	}
}
