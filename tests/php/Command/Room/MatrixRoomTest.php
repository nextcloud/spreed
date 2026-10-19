<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Room;

use OCA\Talk\Command\Room\Add;
use OCA\Talk\Command\Room\Delete;
use OCA\Talk\Command\Room\Demote;
use OCA\Talk\Command\Room\Promote;
use OCA\Talk\Command\Room\Remove;
use OCA\Talk\Command\Room\Update;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class MatrixRoomTest extends TestCase {
	public static function dataCommands(): array {
		return [
			[Add::class],
			[Delete::class],
			[Demote::class],
			[Promote::class],
			[Remove::class],
			[Update::class],
		];
	}

	#[DataProvider('dataCommands')]
	public function testMatrixRoomIsRejected(string $commandClass): void {
		$room = $this->createMock(Room::class);
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);

		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomByToken')
			->with('t0k3n')
			->willReturn($room);

		$roomService = $this->createMock(RoomService::class);
		$roomService->expects($this->never())
			->method($this->anything());
		$participantService = $this->createMock(ParticipantService::class);
		$participantService->expects($this->never())
			->method($this->anything());

		$command = new $commandClass(
			$manager,
			$roomService,
			$participantService,
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
		);

		$input = $this->createMock(InputInterface::class);
		$input->method('getArgument')
			->willReturnMap([
				['token', 't0k3n'],
				['participant', ['admin']],
			]);
		$input->method('getOption')
			->willReturn(null);

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with('<error>Room is a Matrix conversation.</error>');

		$returnCode = self::invokePrivate($command, 'execute', [$input, $output]);
		$this->assertSame(1, $returnCode);
	}
}
