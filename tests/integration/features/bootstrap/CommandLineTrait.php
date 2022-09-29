<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

trait CommandLineTrait {
	/** @var int return code of last command */
	private $lastCode;
	/** @var string stdout of last command */
	private $lastStdOut;
	/** @var string stderr of last command */
	private $lastStdErr;

	/** @var string */
	protected $ocPath = '../../../..';

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

		$args = array_map(function ($arg) {
			return escapeshellarg($arg);
		}, $args);
		$args[] = '--no-ansi';
		$args = implode(' ', $args);

		$descriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open('php console.php ' . $args, $descriptor, $pipes, $this->ocPath, $env);
		$this->lastStdOut = stream_get_contents($pipes[1]);
		$this->lastStdErr = stream_get_contents($pipes[2]);
		$this->lastCode = proc_close($process);

		// Clean opcode cache
		$client = new GuzzleHttp\Client();
		$client->request('GET', 'http://localhost:8080/apps/testing/clean_opcode_cache.php');

		return $this->lastCode;
	}

	/**
	 * @Given /^invoking occ with "([^"]*)"$/
	 */
	public function invokingTheCommand($cmd) {
		if (preg_match('/room-name:(?P<token>\w+)/', $cmd, $matches)) {
			if (array_key_exists($matches['token'], self::$identifierToToken)) {
				$cmd = preg_replace('/room-name:(\w+)/', self::$identifierToToken[$matches['token']], $cmd);
			}
		}
		$args = explode(' ', $cmd);
		$this->runOcc($args);
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
				$msg .= ' Exceptions: ' . implode(', ', $exceptions);
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
	public function theCommandFailedWithExitCode($exitCode) {
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
