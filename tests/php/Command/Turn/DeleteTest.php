<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Turn;

use OCA\Talk\Command\Turn\Delete;
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

	public function testDeleteIfEmpty(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.example.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('turn_servers'),
				$this->equalTo([]),
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>There is nothing to delete.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDelete(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn2.example.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([
				[
					'schemes' => 'turn,turns',
					'server' => 'turn1.example.com',
					'secret' => 'my-test-secret-1',
					'protocols' => 'udp,tcp',
				],
			]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('turn_servers'),
				$this->equalTo([
					[
						'schemes' => 'turn,turns',
						'server' => 'turn1.example.com',
						'secret' => 'my-test-secret-1',
						'protocols' => 'udp,tcp',
					]
				]),
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>There is nothing to delete.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testNothingToDelete(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn4.example.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([
				[
					'schemes' => 'turn,turns',
					'server' => 'turn1.example.com',
					'secret' => 'my-test-secret-1',
					'protocols' => 'udp,tcp',
				],
				[
					'schemes' => 'turn,turns',
					'server' => 'turn2.example.com',
					'secret' => 'my-test-secret-2',
					'protocols' => 'udp,tcp',
				],
				[
					'schemes' => 'turn,turns',
					'server' => 'turn3.example.com',
					'secret' => 'my-test-secret-3',
					'protocols' => 'udp,tcp',
				],
			]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('turn_servers'),
				$this->equalTo([
					[
						'schemes' => 'turn,turns',
						'server' => 'turn1.example.com',
						'secret' => 'my-test-secret-1',
						'protocols' => 'udp,tcp',
					],
					[
						'schemes' => 'turn,turns',
						'server' => 'turn2.example.com',
						'secret' => 'my-test-secret-2',
						'protocols' => 'udp,tcp',
					],
					[
						'schemes' => 'turn,turns',
						'server' => 'turn3.example.com',
						'secret' => 'my-test-secret-3',
						'protocols' => 'udp,tcp',
					],
				])
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>There is nothing to delete.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDeleteMatchingSchemes(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.example.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->appConfig->expects($this->once())
			->method('getAppValueArray')
			->with('turn_servers')
			->willReturn([
				[
					'schemes' => 'turn,turns',
					'server' => 'turn.example.com',
					'secret' => 'my-test-secret-1',
					'protocols' => 'udp,tcp',
				],
				[
					'schemes' => 'turn',
					'server' => 'turn.example.com',
					'secret' => 'my-test-secret-1',
					'protocols' => 'udp,tcp',
				]
			]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(
				$this->equalTo('turn_servers'),
				$this->equalTo([
					[
						'schemes' => 'turn',
						'server' => 'turn.example.com',
						'secret' => 'my-test-secret-1',
						'protocols' => 'udp,tcp',
					],
				])
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Deleted turn.example.com.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
