<?php

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

namespace OCA\Spreed\Tests\php\Chat\Command;

use OCA\Spreed\Chat\Command\Executor;
use OCA\Spreed\Model\Command;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class ExecutorTest extends \Test\TestCase {

	/** @var EventDispatcherInterface|MockObject */
	protected $dispatcher;

	/** @var Executor */
	protected $executor;

	public function setUp() {
		parent::setUp();

		$this->dispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->executor = new Executor($this->dispatcher);
	}

	public function dataExecApp(): array {
		return [
			['arguments1', ''],
			['arguments2', "output from\nevent"],
		];
	}

	/**
	 * @dataProvider dataExecApp
	 * @param string $arguments
	 * @param string $expected
	 */
	public function testExecApp(string $arguments, string $expected): void {
		$message = $this->createMock(IComment::class);
		$room = $this->createMock(Room::class);
		$command = Command::fromParams([]);

		$event = $this->createMock(GenericEvent::class);
		$event->expects($this->once())
			->method('setArguments')
			->with([
				'room' => $room,
				'message' => $message,
				'arguments' => $arguments,
				'output' => '',
			]);
		$event->expects($this->once())
			->method('getArgument')
			->with('output')
			->willReturn($expected);

		$executor = $this->getMockBuilder(Executor::class)
			->setConstructorArgs([$this->dispatcher])
			->setMethods(['createEvent'])
			->getMock();
		$executor->expects($this->once())
			->method('createEvent')
			->with($command)
			->willReturn($event);

		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with(Executor::class . '::execApp', $event);

		$this->assertSame($expected, self::invokePrivate($executor, 'execApp', [$room, $message, $command, $arguments]));
	}

	public function dataExecShell(): array {
		return [
			['admin', 'token', '', '', '', ''],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} {ARGUMENTS}', 'foo bar "hello bear"', "/var/www/nextcloud/script.sh 'admin' 'token' 'foo' 'bar' \"hello bear\"", 'output1'],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} --arguments="{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"', 'foo bar "hello bear"', "/var/www/nextcloud/script.sh 'admin' 'token' --arguments=\"foo bar \\\"hello bear\\\"\"", "out\nput\n2"],
		];
	}

	/**
	 * @dataProvider dataExecShell
	 * @param string|null $actorId
	 * @param string $roomToken
	 * @param string $script
	 * @param string $arguments
	 * @param string $expected
	 * @param string $output
	 */
	public function testExecShell(?string $actorId, string $roomToken, string $script, string $arguments, string $expected, string $output): void {
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

		$room = $this->createMock(Room::class);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);

		$command = Command::fromParams([
			'script' => $script,
		]);

		$executor = $this->getMockBuilder(Executor::class)
			->setConstructorArgs([$this->dispatcher])
			->setMethods(['wrapExec'])
			->getMock();

		$executor->expects($this->once())
			->method('wrapExec')
			->with($expected)
			->willReturn($output);

		$this->assertSame($output, self::invokePrivate($executor, 'execShell', [$room, $message, $command, $arguments]));

	}

	public function dataEscapeArguments(): array {
		return [
			['foobar',             "'foobar'"],
			['foo bar',            "'foo' 'bar'"],
			['"foo" bar',          "\"foo\" 'bar'"],
			['"foo"bar',           "'\"foo\"bar'"],
			['"foo bar"',          '"foo bar"'],
			['"foo foo"bar bar"',  '"foo foo\\"bar bar"'],
			['"foo foo\"bar bar"', '"foo foo\\\\"bar bar"'],
			['" foo bar "',        '" foo bar "'],
			['" foo bar ',         "'\" foo bar '"],
		];
	}

	/**
	 * @dataProvider dataEscapeArguments
	 * @param string $arguments
	 * @param string $expected
	 */
	public function testEscapeArguments(string $arguments, string $expected): void {
		$this->assertSame($expected, self::invokePrivate($this->executor, 'escapeArguments', [$arguments]));
	}
}
