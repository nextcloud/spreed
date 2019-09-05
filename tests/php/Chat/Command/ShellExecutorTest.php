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

namespace OCA\Talk\Tests\php\Chat\Command;

use OCA\Talk\Chat\Command\Executor;
use OCA\Talk\Chat\Command\ShellExecutor;
use OCA\Talk\Model\Command;
use OCA\Talk\Room;
use OCA\Talk\Service\CommandService;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;

class ShellExecutorTest extends TestCase {

	/** @var EventDispatcherInterface|MockObject */
	protected $dispatcher;

	/** @var ShellExecutor|MockObject */
	protected $shellExecutor;

	/** @var CommandService|MockObject */
	protected $commandService;

	/** @var ILogger|MockObject */
	protected $logger;

	/** @var IL10N|MockObject */
	protected $l10n;

	/** @var Executor */
	protected $executor;

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
	 * @param string $cmd
	 * @param string $arguments
	 * @param string $expected
	 * @param string $output
	 */
	public function testExecShell(?string $actorId, string $roomToken, string $cmd, string $arguments, string $expected, string $output): void {
		$executor = $this->getMockBuilder(ShellExecutor::class)
			->setMethods(['wrapExec'])
			->getMock();

		$executor->expects($this->once())
			->method('wrapExec')
			->with($expected)
			->willReturn($output);

		$this->assertSame($output, self::invokePrivate($executor, 'execShell', [$cmd, $arguments, $roomToken, $actorId]));

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
		$this->assertSame($expected, self::invokePrivate(new ShellExecutor(), 'escapeArguments', [$arguments]));
	}
}
