<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Stun;

use OCA\Talk\Command\Stun\Delete;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DeleteTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected InputInterface&MockObject $input;
	protected OutputInterface&MockObject $output;
	protected Delete $command;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);

		$this->command = new Delete($this->appConfig);

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testAddDefaultServerIfEmpty(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun1.test.com:443');
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('stun_servers')
			->willReturn([]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('stun_servers'),
				$this->equalTo(['stun.nextcloud.com:443'])
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>You deleted all STUN servers. A default STUN server was added.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDelete(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun1.test.com:443');
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('stun_servers')
			->willReturn(['stun1.test.com:443', 'stun2.test.com:443']);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('stun_servers'),
				$this->equalTo(['stun2.test.com:443'])
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Deleted stun1.test.com:443.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testNothingToDelete(): void {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun3.test.com:443');
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('stun_servers')
			->willReturn(['stun1.test.com:443']);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('stun_servers'),
				$this->equalTo(['stun1.test.com:443'])
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>There is nothing to delete.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
