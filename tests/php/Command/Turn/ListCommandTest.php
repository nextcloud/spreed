<?php

declare(strict_types=1);
/**
 * @copyright 2018, Denis Mosolov <denismosolov@gmail.com>
 *
 * @author Denis Mosolov <denismosolov@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Afferoq General Public License as
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
namespace OCA\Talk\Tests\php\Command\Turn;

use OCA\Talk\Command\Turn\ListCommand;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ListCommandTest extends TestCase {
	protected IConfig&MockObject $config;
	protected InputInterface&MockObject $input;
	protected OutputInterface&MockObject $output;
	protected ListCommand&MockObject $command;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);

		$this->command = $this->getMockBuilder(ListCommand::class)
			->setConstructorArgs([$this->config])
			->onlyMethods(['writeMixedInOutputFormat'])
			->getMock();

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testEmptyAppConfig(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([]));

		$this->command->expects($this->once())
			->method('writeMixedInOutputFormat')
			->with(
				$this->equalTo($this->input),
				$this->equalTo($this->output),
				$this->equalTo([])
			);

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAppConfigDataChanges(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([
				[
					'server' => 'turn1.test.com',
					'secret' => 'my-sercret-1',
					'protocols' => 'tcp',
				],
				[
					'server' => 'turn2.test.com',
					'secret' => 'my-sercret-2',
					'protocols' => 'udp,tcp',
				],
			]));

		$this->command->expects($this->once())
			->method('writeMixedInOutputFormat')
			->with(
				$this->equalTo($this->input),
				$this->equalTo($this->output),
				$this->equalTo([
					[
						'server' => 'turn1.test.com',
						'secret' => 'my-sercret-1',
						'protocols' => 'tcp',
					],
					[
						'server' => 'turn2.test.com',
						'secret' => 'my-sercret-2',
						'protocols' => 'udp,tcp',
					],
				])
			);

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
