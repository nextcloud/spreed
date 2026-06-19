<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Talk\Tests\Integration\Behat\GithubActions;

use Behat\Testwork\Output\Printer\OutputPrinter;

/**
 * Minimal output printer that writes the exact bytes it is given to STDOUT.
 *
 * Behat's regular {@see \Behat\Testwork\Output\Printer\StreamOutputPrinter} pipes
 * everything through Symfony's console OutputFormatter, which interprets/strips
 * `<tag>` style markup. GitHub Actions workflow commands must reach the runner
 * byte-for-byte (the error messages we emit can legitimately contain `<` and `>`),
 * so we bypass that machinery and write raw output instead.
 */
final class StdoutPrinter implements OutputPrinter {
	private ?string $outputPath = null;

	public function setOutputPath($path): void {
		$this->outputPath = $path;
	}

	public function getOutputPath() {
		return $this->outputPath;
	}

	public function setOutputStyles(array $styles): void {
	}

	public function getOutputStyles(): array {
		return [];
	}

	public function setOutputDecorated($decorated): void {
	}

	public function isOutputDecorated() {
		return false;
	}

	public function setOutputVerbosity($level): void {
	}

	public function getOutputVerbosity() {
		return self::VERBOSITY_NORMAL;
	}

	public function write($messages): void {
		fwrite(STDOUT, (string)$messages);
	}

	public function writeln($messages = ''): void {
		fwrite(STDOUT, $messages . "\n");
	}

	public function flush(): void {
		fflush(STDOUT);
	}
}
