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

use OCA\Talk\Command\Room\Update;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class UpdateTest extends TestCase {
	use TRoomCommandTest;

	/** @var Update */
	private $command;

	/** @var Manager|MockObject */
	private $manager;

	/** @var RoomMockContainer */
	private $roomMockContainer;

	public function setUp(): void {
		parent::setUp();

		$this->registerUserManagerMock();
		$this->registerGroupManagerMock();

		$this->manager = $this->createMock(Manager::class);
		$this->command = new Update($this->manager, $this->userManager, $this->groupManager);

		$this->roomMockContainer = new RoomMockContainer($this);

		$this->registerUserManagerMock();
		$this->registerGroupManagerMock();

		$this->createTestUserMocks();
		$this->createTestGroupMocks();
	}

	public function testMissingArguments(): void {
		$this->manager->expects($this->never())
			->method('getRoomByToken');

		$this->expectException(ConsoleRuntimeException::class);
		$this->expectExceptionMessage('Not enough arguments (missing: "token").');

		$tester = new CommandTester($this->command);
		$tester->execute([]);
	}

	/**
	 * @dataProvider validProvider
	 */
	public function testValid(array $input, array $expectedRoomData, array $initialRoomData): void {
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->willReturnCallback(function (string $token) use ($initialRoomData): Room {
				if ($token !== $initialRoomData['token']) {
					throw new RoomNotFoundException();
				}

				return $this->roomMockContainer->create($initialRoomData);
			});

		$tester = new CommandTester($this->command);
		$tester->execute($input);

		$this->assertEquals("Room successfully updated.\n", $tester->getDisplay());

		$this->assertEquals($expectedRoomData, $this->roomMockContainer->getRoomData());
	}

	public function validProvider(): array {
		return [
			[
				[
					'token' => '__test-room',
				],
				RoomMockContainer::prepareRoomData([]),
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--name' => 'PHPUnit Test Room 2'
				],
				RoomMockContainer::prepareRoomData([
					'name' => 'PHPUnit Test Room 2',
				]),
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--public' => '1'
				],
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
				]),
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--public' => '0'
				],
				RoomMockContainer::prepareRoomData([]),
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
				]),
			],
			[
				[
					'token' => '__test-room',
					'--readonly' => '1'
				],
				RoomMockContainer::prepareRoomData([
					'readOnly' => Room::READ_ONLY,
				]),
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--readonly' => '0'
				],
				RoomMockContainer::prepareRoomData([]),
				RoomMockContainer::prepareRoomData([
					'readOnly' => Room::READ_ONLY,
				]),
			],
			[
				[
					'token' => '__test-room',
					'--readonly' => '1'
				],
				RoomMockContainer::prepareRoomData([
					'readOnly' => Room::READ_ONLY,
				]),
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--password' => 'my-secret-password'
				],
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
					'password' => 'my-secret-password',
				]),
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
				]),
			],
			[
				[
					'token' => '__test-room',
					'--password' => ''
				],
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
				]),
				RoomMockContainer::prepareRoomData([
					'type' => Room::PUBLIC_CALL,
					'password' => 'my-secret-password',
				]),
			],
			[
				[
					'token' => '__test-room',
					'--owner' => 'user1'
				],
				RoomMockContainer::prepareRoomData([
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::OWNER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
				RoomMockContainer::prepareRoomData([
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'token' => '__test-room',
					'--owner' => 'user2'
				],
				RoomMockContainer::prepareRoomData([
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::OWNER],
					],
				]),
				RoomMockContainer::prepareRoomData([
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::OWNER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
			],
			[
				[
					'token' => '__test-room',
					'--owner' => ''
				],
				RoomMockContainer::prepareRoomData([
					'participants' => [
						['userId' => 'user1', 'participantType' => Participant::USER],
						['userId' => 'user2', 'participantType' => Participant::USER],
					],
				]),
				RoomMockContainer::prepareRoomData([
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
	public function testInvalid(array $input, string $expectedOutput, array $initialRoomData = null): void {
		if ($initialRoomData !== null) {
			$this->manager->expects($this->once())
				->method('getRoomByToken')
				->willReturnCallback(function (string $token) use ($initialRoomData): Room {
					if ($token !== $initialRoomData['token']) {
						throw new RoomNotFoundException();
					}

					return $this->roomMockContainer->create($initialRoomData);
				});
		} else {
			$this->manager->expects($this->never())
				->method('getRoomByToken');
		}

		$tester = new CommandTester($this->command);
		$tester->execute($input);

		$this->assertEquals($expectedOutput, $tester->getDisplay());
	}

	public function invalidProvider(): array {
		return [
			[
				[
					'token' => '__test-room',
					'--public' => '',
				],
				"Invalid value for option \"--public\" given.\n",
			],
			[
				[
					'token' => '__test-room',
					'--readonly' => '',
				],
				"Invalid value for option \"--readonly\" given.\n",
			],
			[
				[
					'token' => '__test-invalid',
				],
				"Room not found.\n",
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
				],
				"Room is no group call.\n",
				RoomMockContainer::prepareRoomData([
					'type' => Room::ONE_TO_ONE_CALL,
				]),
			],
			[
				[
					'token' => '__test-room',
					'--name' => '',
				],
				"Invalid room name.\n",
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--name' => '  ',
				],
				"Invalid room name.\n",
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--name' => str_repeat('x', 256),
				],
				"Invalid room name.\n",
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--password' => 'my-secret-password',
				],
				"Unable to add password protection to private room.\n",
				RoomMockContainer::prepareRoomData([]),
			],
			[
				[
					'token' => '__test-room',
					'--owner' => 'invalid',
				],
				"User 'invalid' is no participant.\n",
				RoomMockContainer::prepareRoomData([]),
			],
		];
	}
}
