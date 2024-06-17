<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Tests\php\Command\Turn;

use OCA\Talk\Command\Turn\Add;
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
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return '';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Server cannot be empty.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testSecretEmpty(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return '';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Secret cannot be empty.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testGenerateSecret(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return null;
				} elseif ($arg === 'generate-secret') {
					return true;
				}
				throw new \Exception();
			});

		$command = $this->getMockBuilder(Add::class)
			->onlyMethods(['getUniqueSecret'])
			->setConstructorArgs([$this->config])
			->getMock();
		$command->expects($this->once())
			->method('getUniqueSecret')
			->willReturn('my-generaeted-test-secret');
		$this->config->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('turn_servers'),
				$this->equalTo(json_encode([
					[
						'schemes' => 'turn,turns',
						'server' => 'turn.test.com',
						'secret' => 'my-generaeted-test-secret',
						'protocols' => 'udp,tcp'
					]
				]))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added turn.test.com.</info>'));

		self::invokePrivate($command, 'execute', [$this->input, $this->output]);
	}

	public function testSecretAndGenerateSecretOptions(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return true;
				}
				throw new \Exception();
			});
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>You must provide --secret or --generate-secret.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testInvalidSchemesString(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'invalid-scheme';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Not allowed schemes, must be turn or turns or turn,turns.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testInvalidProtocolsString(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'invalid-protocol';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Not allowed protocols, must be udp or tcp or udp,tcp.</error>'));
		$this->config->expects($this->never())
			->method('setAppValue');

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToEmptyList(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->config->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('turn_servers'),
				$this->equalTo(json_encode([
					[
						'schemes' => 'turn,turns',
						'server' => 'turn.test.com',
						'secret' => 'my-test-secret',
						'protocols' => 'udp,tcp'
					]
				]))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added turn.test.com.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAddServerToNonEmptyList(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn2.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret-2';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->config->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([
				[
					'schemes' => 'turn',
					'server' => 'turn1.test.com',
					'secret' => 'my-test-secret-1',
					'protocols' => 'udp,tcp'
				]
			]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('turn_servers'),
				$this->equalTo(json_encode([
					[
						'schemes' => 'turn',
						'server' => 'turn1.test.com',
						'secret' => 'my-test-secret-1',
						'protocols' => 'udp,tcp'
					],
					[
						'schemes' => 'turn,turns',
						'server' => 'turn2.test.com',
						'secret' => 'my-test-secret-2',
						'protocols' => 'udp,tcp'
					]
				]))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Added turn2.test.com.</info>'));

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testServerSanitization(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'https://turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->config->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([]));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('turn_servers'),
				$this->equalTo(json_encode([
					[
						'schemes' => 'turn,turns',
						'server' => 'turn.test.com',
						'secret' => 'my-test-secret',
						'protocols' => 'udp,tcp'
					]
				]))
			);

		self::invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
	
	public function testAddDuplicateServer(): void {
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'schemes') {
					return 'turn,turns';
				} elseif ($arg === 'server') {
					return 'turn.test.com';
				} elseif ($arg === 'protocols') {
					return 'udp,tcp';
				}
				throw new \Exception();
			});
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'secret') {
					return 'my-test-secret';
				} elseif ($arg === 'generate-secret') {
					return false;
				}
				throw new \Exception();
			});
		$this->config->method('getAppValue')
			->with('spreed', 'turn_servers')
			->willReturn(json_encode([[
				'schemes' => 'turn,turns',
				'server' => 'turn.test.com',
				'secret' => 'my-test-secret',
				'protocols' => 'udp,tcp'
			]]));
		$this->config->expects($this->never())
			->method('setAppValue');
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Server already exists with the same configuration.</error>'));
	
		$this->assertSame(1, self::invokePrivate($this->command, 'execute', [$this->input, $this->output]));
	}

}
