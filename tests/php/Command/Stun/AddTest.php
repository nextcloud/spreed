<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Tests\php\Command\Stun;

use OCA\Talk\Command\Stun\Add;
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

	public function testMalformedServerString(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun.test.com');
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Incorrect value. Must be stunserver:port.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToEmptyList(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun.test.com:443');
		$this->config->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn(json_encode([]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('stun_servers'),
				$this->equalTo(json_encode(['stun.test.com:443']))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added stun.test.com:443.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToNonEmptyList(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun2.test.com:443');
		$this->config->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn(json_encode(['stun1.test.com:443']));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('stun_servers'),
				$this->equalTo(json_encode(['stun1.test.com:443', 'stun2.test.com:443']))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added stun2.test.com:443.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddDuplicateServer(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun.test.com:443');
		$this->config->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn(json_encode(['stun.test.com:443']));
		$this->config->expects($this->never())
			->method('setAppValue');
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Server already exists.</error>'));
	
		$this->assertSame(1, self::invokePrivate($this->command, 'execute', [$this->input, $this->output]));
	}
}
