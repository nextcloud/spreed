<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Bot;

use OCA\Talk\Command\Bot\Setup;
use OCA\Talk\Events\BotEnabledEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\BotService;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class SetupTest extends TestCase {
	protected Manager&MockObject $roomManager;
	protected BotServerMapper&MockObject $botServerMapper;
	protected BotConversationMapper&MockObject $botConversationMapper;
	protected BotService&MockObject $botService;
	protected IEventDispatcher&MockObject $dispatcher;
	protected InputInterface&MockObject $input;
	protected OutputInterface&MockObject $output;
	protected Setup $command;

	public function setUp(): void {
		parent::setUp();

		$this->roomManager = $this->createMock(Manager::class);
		$this->botServerMapper = $this->createMock(BotServerMapper::class);
		$this->botConversationMapper = $this->createMock(BotConversationMapper::class);
		$this->botService = $this->createMock(BotService::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		$this->command = new Setup(
			$this->roomManager,
			$this->botServerMapper,
			$this->botConversationMapper,
			$this->botService,
			$this->dispatcher,
		);

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	protected function mockBotServer(): BotServer&MockObject {
		$botServer = $this->createMock(BotServer::class);

		$this->botServerMapper->method('findById')
			->with(23)
			->willReturn($botServer);
		$this->botService->method('isAppForBotEnabled')
			->with($botServer)
			->willReturn(true);

		return $botServer;
	}

	protected function mockInput(): void {
		$this->input->method('getArgument')
			->willReturnMap([
				['bot-id', '23'],
				['token', ['t0k3n']],
			]);
	}

	protected function mockRoom(bool $classified): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('isFederatedConversation')->willReturn(false);
		$room->method('isClassified')->willReturn($classified);

		$this->roomManager->method('getRoomByToken')
			->with('t0k3n')
			->willReturn($room);

		return $room;
	}

	public function testSetupInClassifiedConversationIsRejected(): void {
		$this->mockInput();
		$this->mockBotServer();
		$this->mockRoom(true);

		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Classified conversations can not have bots: t0k3n</error>'));
		$this->botConversationMapper->expects($this->never())
			->method('insert');
		$this->dispatcher->expects($this->never())
			->method('dispatchTyped');

		$returnCode = self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
		$this->assertSame(2, $returnCode);
	}

	public function testSetupInRegularConversationIsAllowed(): void {
		$this->mockInput();
		$botServer = $this->mockBotServer();
		$room = $this->mockRoom(false);

		$this->botConversationMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (BotConversation $bot): bool {
				$this->assertSame(23, $bot->getBotId());
				$this->assertSame('t0k3n', $bot->getToken());
				$this->assertSame(Bot::STATE_ENABLED, $bot->getState());
				return true;
			}));
		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->equalTo(new BotEnabledEvent($room, $botServer)));

		$returnCode = self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
		$this->assertSame(0, $returnCode);
	}
}
