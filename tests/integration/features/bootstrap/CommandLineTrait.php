<?php
/**
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

trait CommandLineTrait {
	/** @var int return code of last command */
	private int $lastCode = 0;
	/** @var string stdout of last command */
	private string $lastStdOut = '';
	/** @var string stderr of last command */
	private string $lastStdErr = '';

	/** @var string */
	protected string $ocPath = '../../../..';

	/**
	 * Invokes an OCC command
	 *
	 * @param []string $args OCC command, the part behind "occ". For example: "files:transfer-ownership"
	 * @param []string $env environment variables
	 * @return int exit code
	 */
	public function runOcc($args = [], $env = null) {
		// Set UTF-8 locale to ensure that escapeshellarg will not strip
		// multibyte characters.
		setlocale(LC_CTYPE, "C.UTF-8");

		$clearOpcodeCache = in_array($args[0], [
			'app:disable',
			'app:enable',
			'config:system:delete',
			'config:system:set',
			'maintenance:mode',
		], true);

		$args = array_map(function ($arg) {
			return escapeshellarg($arg);
		}, $args);
		$args[] = '--no-ansi';
		$argString = implode(' ', $args);

		$descriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open('php console.php ' . $argString, $descriptor, $pipes, $this->ocPath, $env);
		$this->lastStdOut = stream_get_contents($pipes[1]);
		$this->lastStdErr = stream_get_contents($pipes[2]);
		$this->lastCode = proc_close($process);

		if ($clearOpcodeCache) {
			// Clean opcode cache
			$client = new GuzzleHttp\Client();
			$client->request('GET', 'http://localhost:8080/apps/testing/clean_opcode_cache.php');
		}

		return $this->lastCode;
	}

	/**
	 * @Given /^invoking occ with "([^"]*)"$/
	 */
	public function invokingTheCommand($cmd) {
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
				$cmd = preg_replace('/BOT\((\w+)\)/', self::$botNameToId[$matches['name']], $cmd);
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
	public function findExceptions() {
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

	/**
	 * @Then /^the command was successful$/
	 */
	public function theCommandWasSuccessful() {
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

	/**
	 * @Then /^the command failed with exit code ([0-9]+)$/
	 */
	public function theCommandFailedWithExitCode(int $exitCode) {
		Assert::assertEquals($exitCode, $this->lastCode, 'The commands exit code did not match');
	}

	/**
	 * @Then /^the command failed with exception text "([^"]*)"$/
	 */
	public function theCommandFailedWithException($exceptionText) {
		$exceptions = $this->findExceptions();
		if (empty($exceptions)) {
			throw new \Exception('The command did not throw any exceptions');
		}

		if (!in_array($exceptionText, $exceptions)) {
			throw new \Exception('The command did not throw any exception with the text "' . $exceptionText . '"');
		}
	}

	/**
	 * @Then /^the command output contains the text:$/
	 * @Then /^the command output contains the text "([^"]*)"$/
	 */
	public function theCommandOutputContainsTheText($text) {
		if ($this->lastStdOut === '' && $this->lastStdErr !== '') {
			Assert::assertStringContainsString($text, $this->lastStdErr, 'The command did not output the expected text on stdout');
			Assert::assertTrue(false, 'The command did not output the expected text on stdout but stderr');
		}

		Assert::assertStringContainsString($text, $this->lastStdOut, 'The command did not output the expected text on stdout');
	}

	/**
	 * @Then /^the command output is empty$/
	 */
	public function theCommandOutputIsEmpty() {
		Assert::assertEmpty($this->lastStdOut, 'The command did output unexpected text on stdout');
	}

	/**
	 * @Then /^the command output contains the list entry '([^']*)' with value '([^']*)'$/
	 */
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

	/**
	 * @Then /^the command error output contains the text "([^"]*)"$/
	 */
	public function theCommandErrorOutputContainsTheText($text) {
		if ($this->lastStdErr === '' && $this->lastStdOut !== '') {
			Assert::assertStringContainsString($text, $this->lastStdOut, 'The command did not output the expected text on stdout');
			Assert::assertTrue(false, 'The command did not output the expected text on stdout but stderr');
		}

		Assert::assertStringContainsString($text, $this->lastStdErr, 'The command did not output the expected text on stderr');
	}
}
