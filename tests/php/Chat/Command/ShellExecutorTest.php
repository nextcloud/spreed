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

use OCA\Talk\Chat\Command\ShellExecutor;
use Test\TestCase;

class ShellExecutorTest extends TestCase {
	public static function dataExecShellRun(): array {
		return [
			['admin', 'token', 'echo {ARGUMENTS}', '$PATH', '$PATH'],
			['admin', 'token', 'echo {ARGUMENTS}', '$(pwd)', '$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '$(pwd)', '$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '$PATH', '$PATH'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '$(pwd)', '$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '$PATH', '$PATH'],

			['admin', 'token', 'echo {ARGUMENTS}', '\\$PATH', '\\$PATH'],
			['admin', 'token', 'echo {ARGUMENTS}', '\\$(pwd)', '\\$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '\\$(pwd)', '\\$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '\\$PATH', '\\$PATH'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '\\$(pwd)', '\\$(pwd)'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '\\$PATH', '\\$PATH'],

			['admin', 'token', 'echo {ARGUMENTS}', '`echo $PATH`', '`echo $PATH`'],
			['admin', 'token', 'echo {ARGUMENTS}', '`pwd`', '`pwd`'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '`pwd`', '`pwd`'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '`echo $PATH`', '`echo $PATH`'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '`pwd`', '`pwd`'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '`echo $PATH`', '`echo $PATH`'],

			['admin', 'token', 'echo {ARGUMENTS}', '\\`echo $PATH\\`', '\\`echo $PATH\\`'],
			['admin', 'token', 'echo {ARGUMENTS}', '\\`pwd \\`', '\\`pwd \\`'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '\\`pwd \\`', '\\`pwd \\`'],
			['admin', 'token', __DIR__ . '/echo-argument.sh {ARGUMENTS}', '\\`echo $PATH\\`', '\\`echo $PATH\\`'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '\\`pwd \\`', '\\`pwd \\`'],
			['admin', 'token', __DIR__ . '/echo-option.sh -a {ARGUMENTS}', '\\`echo $PATH\\`', '\\`echo $PATH\\`'],
		];
	}

	/**
	 * @dataProvider dataExecShellRun
	 * @param string|null $actorId
	 * @param string $roomToken
	 * @param string $cmd
	 * @param string $arguments
	 * @param string $output
	 */
	public function testExecShellRun(?string $actorId, string $roomToken, string $cmd, string $arguments, string $output): void {
		$executor = new ShellExecutor();
		$this->assertSame($output, $executor->execShell($cmd, $arguments, $roomToken, $actorId));
	}

	public static function dataExecShell(): array {
		return [
			['admin', 'token', '', '', '', ''],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} {ARGUMENTS}', 'foo bar "hello bear"', "/var/www/nextcloud/script.sh 'admin' 'token' 'foo bar \"hello bear\"'", 'output1'],
			['admin', 'token', '/var/www/nextcloud/script.sh {USER} {ROOM} --arguments {ARGUMENTS}', 'foo bar "hello bear"', "/var/www/nextcloud/script.sh 'admin' 'token' --arguments 'foo bar \"hello bear\"'", "out\nput\n2"],
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
			->onlyMethods(['wrapExec'])
			->getMock();

		$executor->expects($this->once())
			->method('wrapExec')
			->with($expected)
			->willReturn($output);

		$this->assertSame($output, self::invokePrivate($executor, 'execShell', [$cmd, $arguments, $roomToken, $actorId]));
	}

	public function testLegacyArguments(): void {
		$executor = $this->getMockBuilder(ShellExecutor::class)
			->onlyMethods(['wrapExec'])
			->getMock();

		$executor->expects($this->never())
			->method('wrapExec');

		$this->expectException(\InvalidArgumentException::class);
		self::invokePrivate($executor, 'execShell', ['echo "{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"', 'arguments', '', '']);
	}
}
