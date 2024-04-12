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
namespace OCA\Talk\Tests\php\Command\Signaling;

use OCA\Talk\Command\Signaling\Add;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class AddTest extends TestCase {
	protected IConfig&MockObject $config;
	protected InputInterface&MockObject $input;
	protected OutputInterface&MockObject $output;
	protected Add $command;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);

		$this->command = new Add($this->config);

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testServerEmptyString(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'server') {
					return '';
				} elseif ($arg === 'secret') {
					return 'my-test-secret';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->with('verify')
			->willReturn(true);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Server cannot be empty.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testSecretEmptyString(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'server') {
					return 'wss://signaling.test.com';
				} elseif ($arg === 'secret') {
					return '';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->with('verify')
			->willReturn(true);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Secret cannot be empty.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToEmptyList(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'server') {
					return 'wss://signaling.test.com';
				} elseif ($arg === 'secret') {
					return 'my-test-secret';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->with('verify')
			->willReturn(true);
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'signaling_servers')
			->willReturn(json_encode([]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('signaling_servers'),
				$this->equalTo(json_encode([
					'servers' => [
						[
							'server' => 'wss://signaling.test.com',
							'verify' => true
						]
					],
					'secret' => 'my-test-secret'
				]))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added signaling server wss://signaling.test.com.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToNonEmptyList(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'server') {
					return 'wss://signaling2.test.com';
				} elseif ($arg === 'secret') {
					return 'my-test-secret';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->with('verify')
			->willReturn(true);
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'signaling_servers')
			->willReturn(json_encode([
				'servers' => [
					[
						'server' => 'wss://signaling1.test.com',
						'verify' => true
					]
				],
				'secret' => 'my-test-secret'
			]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('signaling_servers'),
				$this->equalTo(json_encode([
					'servers' => [
						[
							'server' => 'wss://signaling1.test.com',
							'verify' => true
						],
						[
							'server' => 'wss://signaling2.test.com',
							'verify' => true
						]
					],
					'secret' => 'my-test-secret'
				]))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added signaling server wss://signaling2.test.com.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
