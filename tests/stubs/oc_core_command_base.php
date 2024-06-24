<?php
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Core\Command {

	use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
	use Symfony\Component\Console\Application;
	use Symfony\Component\Console\Input\InputDefinition;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Output\OutputInterface;

	class Base {
		public const OUTPUT_FORMAT_PLAIN = 'plain';
		public const OUTPUT_FORMAT_JSON = 'json';
		public const OUTPUT_FORMAT_JSON_PRETTY = 'json_pretty';

		protected string $defaultOutputFormat = self::OUTPUT_FORMAT_PLAIN;

		public function __construct(?string $name = null) {
		}

		protected function configure() {
		}

		public function setName(string $name) {
		}

		public function getApplication(): ?Application {
		}

		public function getDefinition(): InputDefinition {
		}

		protected function writeArrayInOutputFormat(InputInterface $input, OutputInterface $output, array $items, string $prefix = '  - '): void {
		}

		protected function writeTableInOutputFormat(InputInterface $input, OutputInterface $output, array $items): void {
		}

		protected function writeMixedInOutputFormat(InputInterface $input, OutputInterface $output, $item) {
		}

		protected function valueToString($value, bool $returnNull = true): ?string {
		}

		protected function abortIfInterrupted() {
		}

		protected function cancelOperation() {
		}

		public function run(InputInterface $input, OutputInterface $output) {
		}

		public function completeOptionValues($optionName, CompletionContext $context) {
		}

		public function completeArgumentValues($argumentName, CompletionContext $context) {
		}
	}
}
