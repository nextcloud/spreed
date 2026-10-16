<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Events\BotUninstallEvent;
use OCA\Talk\Listener\BotListener;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Service\BotService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BotListenerTest extends TestCase {
	protected BotServerMapper&MockObject $botServerMapper;
	protected BotConversationMapper&MockObject $botConversationMapper;
	protected IEventDispatcher&MockObject $dispatcher;
	protected BotListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->botServerMapper = $this->createMock(BotServerMapper::class);
		$this->botConversationMapper = $this->createMock(BotConversationMapper::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->listener = new BotListener(
			$this->botServerMapper,
			$this->botConversationMapper,
			$this->createMock(BotService::class),
			$this->createMock(LoggerInterface::class),
			$this->dispatcher,
		);
	}

	public function testInstallLogsCriticalActionWithoutSecret(): void {
		$this->botServerMapper->method('findByUrlAndSecret')->willThrowException(new DoesNotExistException('not found'));
		$this->botServerMapper->method('findByUrl')->willThrowException(new DoesNotExistException('not found'));
		$this->botServerMapper->method('insert')
			->willReturnCallback(static function (BotServer $bot): BotServer {
				$bot->setId(3);
				return $bot;
			});

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.bot.installed'
				&& $event->getLogMessage() === 'Bot #{botId} ("{botName}") calling {urlOrigin} was installed'
				&& $event->getParameters() === ['botId' => 3, 'botName' => 'Helper', 'urlOrigin' => 'https://bot.example:8443']));

		$this->listener->handle(new BotInstallEvent('Helper', 'secret-value-that-is-long-enough-to-pass-validation', 'https://bot.example:8443/webhook?token=abc'));
	}

	public function testReinstallIsNotLogged(): void {
		$bot = new BotServer();
		$this->botServerMapper->method('findByUrlAndSecret')->willReturn($bot);

		$this->dispatcher->expects($this->never())->method('dispatchTyped');

		$this->listener->handle(new BotInstallEvent('Helper', 'secret-value-that-is-long-enough-to-pass-validation', 'https://bot.example/webhook'));
	}

	public function testUninstallLogsCriticalAction(): void {
		$bot = new BotServer();
		$bot->setId(3);
		$bot->setName('Helper');
		$bot->setUrl('https://bot.example/webhook');
		$this->botServerMapper->method('findByUrlAndSecret')->willReturn($bot);

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.bot.uninstalled'
				&& $event->getParameters() === ['botId' => 3, 'botName' => 'Helper', 'urlOrigin' => 'https://bot.example']));

		$this->listener->handle(new BotUninstallEvent('secret-value-that-is-long-enough-to-pass-validation', 'https://bot.example/webhook'));
	}
}
