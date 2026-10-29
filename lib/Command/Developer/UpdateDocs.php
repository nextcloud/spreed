<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Developer;

use OC\Core\Command\Base;
use OCA\Talk\Config\AppConfigEntry;
use OCA\Talk\ConfigLexicon;
use OCP\App\IAppManager;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\Preset;
use OCP\Config\ValueType;
use OCP\Console\Attribute\AsCommand;
use OCP\IConfig;
use OCP\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateDocs extends Base {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
		private readonly ConfigLexicon $configLexicon,
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:developer:update-docs')
			->setDescription('Update documentation of commands and app configs')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$info = $this->appManager->getAppInfo('spreed');
		$documentation = "# Talk occ commands\n\n";
		foreach ($info['commands'] as $namespace) {
			if ($namespace === self::class
				|| $namespace === AgeChatMessages::class) {
				continue;
			}

			$command = $this->getCommand($namespace);
			$documentation .= $this->getDocumentation($command) . "\n";
		}

		$handle = fopen(__DIR__ . '/../../../docs/occ.md', 'w');
		fwrite($handle, $documentation);
		fclose($handle);

		$settingsFile = __DIR__ . '/../../../docs/settings.md';
		file_put_contents($settingsFile, $this->replaceAppConfigTable((string)file_get_contents($settingsFile)));
		return 0;
	}

	public function replaceAppConfigTable(string $settings): string {
		$section = strpos($settings, '## App configuration');
		$start = strpos($settings, "\n| Key ", $section ?: 0);
		$end = strpos($settings, "\n## ", $start ?: 0);
		if ($section === false || $start === false || $end === false) {
			throw new \RuntimeException('Could not find the app configuration table in docs/settings.md');
		}

		return substr($settings, 0, $start + 1) . $this->getAppConfigTable() . substr($settings, $end);
	}

	protected function getAppConfigTable(): string {
		$table = "| Key | Type | Default | Hash | Option | Description |\n";
		$table .= "|---|---|---|---|---|---|\n";
		$configs = $this->configLexicon->getTalkAppConfigs();
		usort($configs, static fn (AppConfigEntry $a, AppConfigEntry $b): int => strcmp($a->entry->getKey(), $b->entry->getKey()));
		foreach ($configs as $config) {
			$table .= $this->getAppConfigRow($config) . "\n";
		}
		return $table;
	}

	public function getAppConfigRow(AppConfigEntry $config): string {
		$entry = $config->entry;
		return '| `' . $entry->getKey() . '` | '
			. $this->formatType($config) . ' | '
			. $this->formatDefault($entry) . ' | '
			. ($config->hash ? 'Yes' : 'No') . ' | '
			. ($config->adminSetting ? '🖌️' : '') . ($config->occCommands !== [] ? '💻' : '') . ' | '
			. ($entry->isDeprecated() ? 'Deprecated: ' : '') . str_replace([PHP_EOL, '|'], ['<br>', '\|'], $entry->getDefinition()) . ' |';
	}

	protected function formatType(AppConfigEntry $config): string {
		if ($config->arrayType !== null) {
			return '`' . str_replace('|', '\|', $config->arrayType) . '`';
		}

		$type = strtolower($config->entry->getValueType()->name);
		if ($config->enumType === null) {
			return $type;
		}

		$values = array_map(static fn (string $value): string => '`' . $value . '`', explode('|', $config->enumType));
		$last = array_pop($values);
		return $type . '<br>' . ($values === [] ? $last : implode(', ', $values) . ' or ' . $last);
	}

	protected function formatDefault(Entry $entry): string {
		$default = $entry->getDefault(Preset::NONE);
		if ($default === null) {
			return '';
		}
		if (str_contains($default, "\n")) {
			return 'See `lib/ConfigLexicon.php`';
		}
		if ($entry->getValueType() === ValueType::BOOL) {
			return \in_array($default, ['1', 'yes', 'true', 'on'], true) ? '`true`' : '`false`';
		}
		return '`' . ($default === '' ? "''" : str_replace('|', '\|', $default)) . '`';
	}

	protected function getCommand(string $namespace): Command {
		$asCommand = (new \ReflectionClass($namespace))->getAttributes(AsCommand::class);
		if ($asCommand !== []) {
			// Attribute based commands are only wrapped into a Command by the console application
			return $this->getApplication()->find($asCommand[0]->newInstance()->name);
		}

		$command = Server::get($namespace);
		// Clean full definition of command that have the default Symfony options
		$command->setApplication($this->getApplication());
		return $command;
	}

	protected function getDocumentation(Command $command): string {
		$doc = '## ' . $command->getName() . "\n\n";
		$doc .= $command->getDescription() . "\n\n";
		$doc
			.= '### Usage' . "\n\n"
			. array_reduce(
				array_merge(
					[$command->getSynopsis()],
					$command->getAliases(),
					$command->getUsages()
				),
				fn ($carry, $usage) => $carry . '* `' . $usage . '`' . "\n"
			);
		$doc .= $this->describeInputDefinition($command);

		return $doc;
	}

	protected function describeInputDefinition(Command $command): string {
		$definition = $command->getDefinition();
		$text = '';
		if (\count($definition->getArguments()) > 0) {
			$text .= "\n";
			$text .= "| Arguments | Description | Is required | Is array | Default |\n";
			$text .= '|---|---|---|---|---|';
			foreach ($definition->getArguments() as $argument) {
				$describeInputArgument = $this->describeInputArgument($argument);
				if ($describeInputArgument) {
					$text .= "\n" . $describeInputArgument;
				}
			}
			$text .= "\n";
		}

		if (\count($definition->getOptions()) > 0) {
			$text .= "\n";

			$text .= "| Options | Description | Accept value | Is value required | Is multiple | Default |\n";
			$text .= '|---|---|---|---|---|---|';
			foreach ($definition->getOptions() as $option) {
				$describeInputOption = $this->describeInputOption($option);
				if ($describeInputOption) {
					$text .= "\n" . $describeInputOption;
				}
			}
			$text .= "\n";
		}
		return $text;
	}

	protected function describeInputArgument(InputArgument $argument): string {
		$description = $argument->getDescription();

		return
			'| `' . ($argument->getName() ?: '<none>') . '` | '
			. ($description ? preg_replace('/\s*[\r\n]\s*/', ' ', $description) : '') . ' | '
			. ($argument->isRequired() ? 'yes' : 'no') . ' | '
			. ($argument->isArray() ? 'yes' : 'no') . ' | '
			. ($argument->isRequired() ? '*Required*' : '`' . str_replace("\n", '', var_export($argument->getDefault(), true)) . '`') . ' |';
	}

	protected function describeInputOption(InputOption $option): string {
		$name = '--' . $option->getName();
		if ($option->getShortcut()) {
			$name .= '\|-' . str_replace('|', '\|-', $option->getShortcut());
		}
		$description = $option->getDescription();

		return
			'| `' . $name . '` | '
			. ($description ? preg_replace('/\s*[\r\n]\s*/', ' ', $description) : '') . ' | '
			. ($option->acceptValue() ? 'yes' : 'no') . ' | '
			. ($option->isValueRequired() ? 'yes' : 'no') . ' | '
			. ($option->isArray() ? 'yes' : 'no') . ' | '
			. ($option->isValueRequired() ? '*Required*' : '`' . str_replace("\n", '', var_export($option->getDefault(), true)) . '`') . ' |';
	}
}
