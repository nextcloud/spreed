<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Turn;

use OCA\Talk\Command\Turn\ListCommand;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ListCommandTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected InputInterface&MockObject $input;
	protected OutputInterface&MockObject $output;
	protected ListCommand&MockObject $command;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);

		$this->command = $this->getMockBuilder(ListCommand::class)
			->setConstructorArgs([$this->appConfig])
			->onlyMethods(['writeMixedInOutputFormat'])
			->getMock();

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testEmptyAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([]);

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
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([
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
			]);

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
