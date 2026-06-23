<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Talk\Tests\Integration\Behat\GithubActions;

use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Exception\ExceptionPresenter;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Output\Printer\OutputPrinter;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResult;

/**
 * Behat formatter that turns failing steps into GitHub Actions error annotations.
 *
 * For every step that does not pass it emits a workflow command of the form
 *
 *     ::error file={path},line={line},title={title}::{message}
 *
 * which GitHub Actions parses out of the job log and surfaces as an inline
 * annotation on the relevant line of the feature file (and in the run summary).
 *
 * @see https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-an-error-message
 *
 * This formatter only produces the extra annotations; it is meant to run
 * alongside the regular `pretty`/`junit` formatters so the existing output is
 * fully retained.
 */
final class GithubActionsFormatter implements Formatter {
	public const NAME = 'github_actions';

	/**
	 * GitHub silently drops annotation messages longer than ~4 KB, so we cap the
	 * (decoded) message and replace the overflow with a pointer to the full
	 * pretty/junit output. Configurable via the `max_message_length` parameter.
	 */
	private const DEFAULT_MAX_MESSAGE_LENGTH = 4000;

	/**
	 * Absolute path the emitted file paths are made relative to. GitHub matches
	 * annotation paths against the repository root, so we report e.g.
	 * `tests/integration/features/foo.feature` rather than an absolute path.
	 */
	private string $basePath;

	private ?ScenarioInterface $currentScenario = null;

	/**
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		private array $parameters,
		private readonly OutputPrinter $printer,
		private readonly ExceptionPresenter $exceptionPresenter,
	) {
		$this->basePath = $this->resolveBasePath();
	}

	public static function getSubscribedEvents(): array {
		return [
			ScenarioTested::BEFORE => 'beforeScenario',
			ExampleTested::BEFORE => 'beforeScenario',
			AfterStepTested::AFTER => 'afterStep',
		];
	}

	public function getName(): string {
		return self::NAME;
	}

	public function getDescription(): string {
		return 'Emits failing steps as GitHub Actions error annotations.';
	}

	public function getOutputPrinter(): OutputPrinter {
		return $this->printer;
	}

	public function setParameter($name, $value): void {
		$this->parameters[$name] = $value;

		if ($name === 'base_path' && is_string($value) && $value !== '') {
			$this->basePath = rtrim($value, '/');
		}
	}

	public function getParameter($name) {
		return $this->parameters[$name] ?? null;
	}

	public function beforeScenario(ScenarioTested $event): void {
		$this->currentScenario = $event->getScenario();
	}

	public function afterStep(AfterStepTested $event): void {
		$result = $event->getTestResult();
		if ($this->isAcceptable($result)) {
			return;
		}

		$step = $event->getStep();
		$feature = $event->getFeature();

		$file = $this->makeRelative((string)$feature->getFile());
		$line = $step->getLine();

		$title = $this->buildTitle($feature->getTitle());
		$message = $this->truncate($this->buildMessage($step->getKeyword(), $step->getText(), $result));

		$this->printer->writeln($this->command($file, $line, $title, $message));
	}

	/**
	 * A step that passed or was skipped is not worth annotating; everything else
	 * (failed, undefined or pending) breaks the build and is reported as an error.
	 */
	private function isAcceptable(TestResult $result): bool {
		return in_array($result->getResultCode(), [TestResult::PASSED, TestResult::SKIPPED], true);
	}

	private function buildTitle(string $featureTitle): string {
		$parts = array_filter([
			$featureTitle,
			$this->currentScenario?->getTitle(),
		]);

		return implode(' :: ', $parts) ?: 'Behat failure';
	}

	private function buildMessage(string $keyword, string $stepText, TestResult $result): string {
		$message = trim($keyword) . ' ' . $stepText;

		if ($result instanceof ExceptionResult && $result->getException() !== null) {
			// Delegate to Behat's exception presenter (the same one the `pretty`
			// formatter uses). For PHPUnit assertion failures this is what turns a
			// bare "Failed asserting that two arrays are equal." into the full
			// message including the "--- Expected / +++ Actual" comparison diff,
			// which is otherwise attached to the exception out-of-band and lost
			// when only reading Exception::getMessage().
			$presented = $this->exceptionPresenter->presentException(
				$result->getException(),
				OutputPrinter::VERBOSITY_NORMAL,
				applyEditorUrl: false,
			);
			$message .= "\n\n" . rtrim($presented);
		} elseif ($result->getResultCode() === TestResult::PENDING) {
			$message .= "\n\nStep is pending (not yet implemented).";
		} else {
			$message .= "\n\nStep is undefined.";
		}

		return $message;
	}

	/**
	 * Caps the message so it survives GitHub's annotation size limit. The full
	 * failure is always available in the pretty/junit output, so a clipped
	 * annotation only loses the tail of the diff/stack trace.
	 */
	private function truncate(string $message): string {
		$max = $this->maxMessageLength();
		if ($max <= 0 || mb_strlen($message) <= $max) {
			return $message;
		}

		$notice = "\n… (truncated, see the full output in the job log)";
		$keep = max(0, $max - mb_strlen($notice));

		return rtrim(mb_substr($message, 0, $keep)) . $notice;
	}

	private function maxMessageLength(): int {
		$configured = $this->parameters['max_message_length'] ?? null;

		return is_numeric($configured) ? (int)$configured : self::DEFAULT_MAX_MESSAGE_LENGTH;
	}

	private function command(string $file, int $line, string $title, string $message): string {
		$properties = [
			'file' => $file,
			'line' => (string)$line,
			'title' => $title,
		];

		$parts = [];
		foreach ($properties as $key => $value) {
			$parts[] = $key . '=' . $this->escapeProperty($value);
		}

		return '::error ' . implode(',', $parts) . '::' . $this->escapeData($message);
	}

	/**
	 * Escapes a workflow command message body.
	 *
	 * @see https://github.com/actions/toolkit/blob/main/packages/core/src/command.ts
	 */
	private function escapeData(string $value): string {
		return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
	}

	/**
	 * Escapes a workflow command property value (additionally escapes `:` and `,`).
	 */
	private function escapeProperty(string $value): string {
		return str_replace(
			['%', "\r", "\n", ':', ','],
			['%25', '%0D', '%0A', '%3A', '%2C'],
			$value,
		);
	}

	private function makeRelative(string $path): string {
		if ($path === '' || $this->basePath === '') {
			return $path;
		}

		$prefix = $this->basePath . '/';
		if (str_starts_with($path, $prefix)) {
			return substr($path, strlen($prefix));
		}

		return $path;
	}

	private function resolveBasePath(): string {
		$configured = $this->parameters['base_path'] ?? null;
		if (is_string($configured) && $configured !== '') {
			return rtrim($configured, '/');
		}

		// Behat is invoked from tests/integration, so the repository root that
		// GitHub resolves annotation paths against is two directories up.
		$root = realpath(getcwd() . '/../..');

		return $root !== false ? $root : (string)getcwd();
	}
}
