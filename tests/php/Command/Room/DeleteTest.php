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

use OCA\Talk\Command\Room\Delete;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class DeleteTest extends TestCase {
	use TRoomCommandTest;

	/** @var Delete */
	private $command;

	/** @var Manager|MockObject */
	private $manager;

	/** @var RoomService|MockObject */
	private $roomService;

	/** @var RoomMockContainer */
	private $roomMockContainer;

	public function setUp(): void {
		parent::setUp();

		$this->registerUserManagerMock();
		$this->registerGroupManagerMock();

		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->command = new Delete(
			$this->manager,
			$this->roomService,
			$this->userManager,
			$this->groupManager
		);

		$this->roomMockContainer = new RoomMockContainer($this);
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
	public function testValid(array $input, array $initialRoomData): void {
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->willReturnCallback(function (string $token) use ($initialRoomData): Room {
				if ($token !== $initialRoomData['token']) {
					throw new RoomNotFoundException();
				}

				return $this->roomMockContainer->create($initialRoomData);
			});

		$this->roomMockContainer->registerCallback(function (object $room) {
			/** @var Room|MockObject $room */
			$room->expects($this->once())
				->method('deleteRoom');
		});

		$tester = new CommandTester($this->command);
		$tester->execute($input);

		$this->assertEquals("Room successfully deleted.\n", $tester->getDisplay());
	}

	public function validProvider(): array {
		return [
			[
				[
					'token' => '__test-room',
				],
				RoomMockContainer::prepareRoomData([]),
			],
		];
	}

	/**
	 * @dataProvider invalidProvider
	 */
	public function testInvalid(array $input, string $expectedOutput, array $initialRoomData): void {
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

		$this->assertEquals($expectedOutput, $tester->getDisplay());
	}

	public function invalidProvider(): array {
		return [
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
		];
	}
}
