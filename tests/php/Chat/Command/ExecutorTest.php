<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Talk\Tests\php\Chat\Command;

use OCA\Talk\Chat\Command\Executor;
use OCA\Talk\Chat\Command\ShellExecutor;
use OCA\Talk\Events\CommandEvent;
use OCA\Talk\Model\Command;
use OCA\Talk\Room;
use OCA\Talk\Service\CommandService;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ExecutorTest extends TestCase {
	protected IEventDispatcher&MockObject $dispatcher;
	protected ShellExecutor&MockObject $shellExecutor;
	protected CommandService&MockObject $commandService;
	protected LoggerInterface&MockObject $logger;
	protected IL10N&MockObject $l10n;
	protected ?Executor $executor = null;

	public function setUp(): void {
		parent::setUp();

		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->shellExecutor = $this->createMock(ShellExecutor::class);
		$this->commandService = $this->createMock(CommandService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->executor = new Executor(
			$this->dispatcher,
			$this->shellExecutor,
			$this->commandService,
			$this->logger,
			$this->l10n
		);
	}

	public static function dataExecApp(): array {
		return [
			['arguments1', ''],
			['arguments2', "output from\nevent"],
		];
	}

	/**
	 * @dataProvider dataExecApp
	 */
	public function testExecApp(string $arguments, string $expected): void {
		$message = $this->createMock(IComment::class);
		$room = $this->createMock(Room::class);
		$command = Command::fromParams([]);

		$event = $this->createMock(CommandEvent::class);
		$event->expects($this->once())
			->method('getOutput')
			->willReturn($expected);

		$executor = $this->getMockBuilder(Executor::class)
			->setConstructorArgs([
				$this->dispatcher,
				$this->shellExecutor,
				$this->commandService,
				$this->logger,
				$this->l10n,
			])
			->onlyMethods(['createEvent'])
			->getMock();
		$executor->expects($this->once())
			->method('createEvent')
			->with($room, $message, $command, $arguments)
			->willReturn($event);

		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with(Executor::EVENT_APP_EXECUTE, $event);

		$this->assertSame($expected, self::invokePrivate($executor, 'execApp', [$room, $message, $command, $arguments]));
	}

	public static function dataExecShell(): array {
		return [
			['admin', 'token', '', '', ''],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} {ARGUMENTS}', 'foo bar "hello bear"', 'output1'],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} --arguments="{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"', 'foo bar "hello bear"', "out\nput\n2"],
		];
	}

	/**
	 * @dataProvider dataExecShell
	 */
	public function testExecShell(?string $actorId, string $roomToken, string $script, string $arguments, string $output): void {
		/** @var IComment&MockObject $message */
		$message = $this->createMock(IComment::class);
		if ($actorId === null) {
			$message->expects($this->once())
				->method('getActorType')
				->willReturn('guests');
			$message->expects($this->never())
				->method('getActorId');
		} else {
			$message->expects($this->once())
				->method('getActorType')
				->willReturn('users');
			$message->expects($this->once())
				->method('getActorId')
				->willReturn($actorId);
		}

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);

		/** @var Command $command */
		$command = Command::fromParams([
			'script' => $script,
		]);

		$this->shellExecutor->expects($this->once())
			->method('execShell')
			->with(
				$script,
				$arguments,
				$roomToken,
				(string) $actorId
			)
			->willReturn($output);

		$this->assertSame($output, $this->executor->execShell($room, $message, $command, $arguments));
	}
}
