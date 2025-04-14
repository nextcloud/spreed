<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Step\Given;
use Behat\Step\Then;
use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

// The following attributes are expected to be available in the class that uses
// this trait:
// - currentServer
// - localServerUrl
// - remoteServerUrl
trait CommandLineTrait {
	/** @var int return code of last command */
	private int $lastCode = 0;
	/** @var string stdout of last command */
	private string $lastStdOut = '';
	/** @var string stderr of last command */
	private string $lastStdErr = '';
	protected string $ocPath = '../../../..';

	/**
	 * Invokes an OCC command
	 *
	 * @param string[] $args OCC command, the part behind "occ". For example: "files:transfer-ownership"
	 * @param string[] $env environment variables
	 * @return int exit code
	 */
	public function runOcc(array $args = [], array $env = []): int {
		// Set UTF-8 locale to ensure that escapeshellarg will not strip
		// multibyte characters.
		setlocale(LC_CTYPE, 'C.UTF-8');

		$clearOpcodeCache = in_array($args[0], [
			'app:disable',
			'app:enable',
			'config:system:delete',
			'config:system:set',
			'maintenance:mode',
		], true);

		$args = array_map(static function (string|int $arg) {
			return escapeshellarg((string)$arg);
		}, $args);
		$args[] = '--no-ansi';
		$argString = implode(' ', $args);

		if ($this->currentServer === 'REMOTE') {
			$env['NEXTCLOUD_CONFIG_DIR'] = getenv('NEXTCLOUD_REMOTE_CONFIG_DIR');
			$serverRootDir = getenv('NEXTCLOUD_REMOTE_ROOT_DIR');
		} else {
			$env['NEXTCLOUD_CONFIG_DIR'] = getenv('NEXTCLOUD_HOST_CONFIG_DIR');
			$serverRootDir = getenv('NEXTCLOUD_HOST_ROOT_DIR');
		}

		$descriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open('php ' . $serverRootDir . '/console.php ' . $argString, $descriptor, $pipes, $this->ocPath, $env);
		$this->lastStdOut = stream_get_contents($pipes[1]);
		$this->lastStdErr = stream_get_contents($pipes[2]);
		$this->lastCode = proc_close($process);

		if ($clearOpcodeCache) {
			// Clean opcode cache
			$client = new GuzzleHttp\Client();

			if ($this->currentServer === 'REMOTE') {
				$client->request('GET', $this->remoteServerUrl . 'apps/testing/clean_opcode_cache.php');
			} else {
				$client->request('GET', $this->localServerUrl . 'apps/testing/clean_opcode_cache.php');
			}
		}

		return $this->lastCode;
	}

	#[Given('/^invoking occ with "([^"]*)"$/')]
	public function invokingTheCommand(string $cmd): void {
		// FIXME this way is deprecated
		if (preg_match('/room-name:(?P<token>\w+)/', $cmd, $matches)) {
			if (array_key_exists($matches['token'], self::$identifierToToken)) {
				$cmd = preg_replace('/room-name:(\w+)/', self::$identifierToToken[$matches['token']], $cmd);
			}
		}

		if (preg_match('/ROOM\((?P<name>\w+)\)/', $cmd, $matches)) {
			if (array_key_exists($matches['name'], self::$identifierToToken)) {
				$cmd = preg_replace('/ROOM\((\w+)\)/', self::$identifierToToken[$matches['name']], $cmd);
			}
		}
		if (preg_match('/BOT\((?P<name>\w+)\)/', $cmd, $matches)) {
			if (array_key_exists($matches['name'], self::$botNameToId)) {
				$cmd = preg_replace('/BOT\((\w+)\)/', (string)self::$botNameToId[$matches['name']], $cmd);
			}
		}

		$args = explode(' ', $cmd);
		$this->runOcc($args);
	}

	public function getLastStdOut(): string {
		return $this->lastStdOut;
	}

	/**
	 * Find exception texts in stderr
	 */
	public function findExceptions(): array {
		$exceptions = [];
		$captureNext = false;
		// the exception text usually appears after an "[Exception"] row
		foreach (explode("\n", $this->lastStdErr) as $line) {
			if (preg_match('/\[Exception\]/', $line)) {
				$captureNext = true;
				continue;
			}
			if ($captureNext) {
				$exceptions[] = trim($line);
				$captureNext = false;
			}
		}

		return $exceptions;
	}

	#[Then('/^the command was successful$/')]
	public function theCommandWasSuccessful(): void {
		$exceptions = $this->findExceptions();
		if ($this->lastCode !== 0) {
			echo $this->lastStdErr;

			$msg = 'The command was not successful, exit code was ' . $this->lastCode . '.';
			if (!empty($exceptions)) {
				$msg .= "\n" . ' Exceptions: ' . implode(', ', $exceptions);
			} else {
				$msg .= "\n" . ' ' . $this->lastStdOut;
				$msg .= "\n" . ' ' . $this->lastStdErr;
			}
			throw new \Exception($msg);
		} elseif (!empty($exceptions)) {
			$msg = 'The command was successful but triggered exceptions: ' . implode(', ', $exceptions);
			throw new \Exception($msg);
		}
	}

	#[Then('/^the command failed with exit code ([0-9]+)$/')]
	public function theCommandFailedWithExitCode(int $exitCode): void {
		Assert::assertEquals($exitCode, $this->lastCode, 'The commands exit code did not match');
	}

	#[Then('/^the command failed with exception text "([^"]*)"$/')]
	public function theCommandFailedWithException(string $exceptionText): void {
		$exceptions = $this->findExceptions();
		if (empty($exceptions)) {
			throw new \Exception('The command did not throw any exceptions');
		}

		if (!in_array($exceptionText, $exceptions)) {
			throw new \Exception('The command did not throw any exception with the text "' . $exceptionText . '"');
		}
	}

	#[Then('/^the command output contains the text:$/')]
	#[Then('/^the command output contains the text "([^"]*)"$/')]
	public function theCommandOutputContainsTheText(string $text): void {
		if ($this->lastStdOut === '' && $this->lastStdErr !== '') {
			Assert::assertStringContainsString($text, $this->lastStdErr, 'The command did not output the expected text on stdout');
			Assert::assertTrue(false, 'The command did not output the expected text on stdout but stderr');
		}

		Assert::assertStringContainsString($text, $this->lastStdOut, 'The command did not output the expected text on stdout');
	}

	#[Then('/^the command output is empty$/')]
	public function theCommandOutputIsEmpty(): void {
		Assert::assertEmpty($this->lastStdOut, 'The command did output unexpected text on stdout');
	}

	#[Then("/^the command output contains the list entry '([^']*)' with value '([^']*)'\$/")]
	public function theCommandOutputContainsTheListEntry(string $key, string $value): void {
		if (preg_match('/^"ROOM\(([^"]+)\)"$/', $key, $matches)) {
			$key = '"' . self::$identifierToToken[$matches[1]] . '"';
		}
		$text = '- ' . $key . ': ' . $value;

		if ($this->lastStdOut === '' && $this->lastStdErr !== '') {
			Assert::assertStringContainsString($text, $this->lastStdErr, 'The command did not output the expected text on stdout');
			Assert::assertTrue(false, 'The command did not output the expected text on stdout but stderr');
		}

		Assert::assertStringContainsString($text, $this->lastStdOut, 'The command did not output the expected text on stdout');
	}

	#[Then('/^the command error output contains the text "([^"]*)"$/')]
	public function theCommandErrorOutputContainsTheText(string $text): void {
		if ($this->lastStdErr === '' && $this->lastStdOut !== '') {
			Assert::assertStringContainsString($text, $this->lastStdOut, 'The command did not output the expected text on stdout');
			Assert::assertTrue(false, 'The command did not output the expected text on stdout but stderr');
		}

		Assert::assertStringContainsString($text, $this->lastStdErr, 'The command did not output the expected text on stderr');
	}
}
