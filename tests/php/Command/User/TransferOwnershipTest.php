<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\User;

use OCA\Talk\Command\User\TransferOwnership;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class TransferOwnershipTest extends TestCase {
	public function testMatrixRoomIsSkipped(): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);

		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomsForActor')
			->willReturn([$room]);

		$participantService = $this->createMock(ParticipantService::class);
		$participantService->expects($this->never())
			->method($this->anything());

		$destinationUser = $this->createMock(IUser::class);
		$destinationUser->method('getUID')
			->willReturn('destination');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')
			->with('destination')
			->willReturn($destinationUser);

		$command = new TransferOwnership($participantService, $manager, $userManager);

		$input = $this->createMock(InputInterface::class);
		$input->method('getArgument')
			->willReturnMap([
				['source-user', 'source'],
				['destination-user', 'destination'],
			]);
		$input->method('getOption')
			->willReturn(true);

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->exactly(2))
			->method('writeln')
			->willReturnCallback(function (string $message): void {
				static $expected = [
					'<comment>Could not transfer membership in 1 Matrix rooms.</comment>',
					'<info>Added or promoted user destination in 0 rooms.</info>',
				];
				$this->assertSame(array_shift($expected), $message);
			});

		$returnCode = self::invokePrivate($command, 'execute', [$input, $output]);
		$this->assertSame(0, $returnCode);
	}
}
