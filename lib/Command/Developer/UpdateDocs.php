<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Command\Developer;

use OC\Core\Command\Base;
use OCP\App\IAppManager;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateDocs extends Base {
	private IConfig $config;
	private IAppManager $appManager;
	/** @var resource */
	private $doc;

	public function __construct(IConfig $config) {
		$this->config = $config;

		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:developer:update-docs')
			->setDescription('Update documentation of commands')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->appManager = \OC::$server->get(IAppManager::class);
		$this->doc = fopen(__DIR__ . '/../../../docs/occ.md', 'a');
		ftruncate($this->doc, 0);

		$info = $this->appManager->getAppInfo('spreed');
		foreach ($info['commands'] as $commandNamespace) {
			$path = str_replace('OCA\Talk', '', $commandNamespace);
			$path = str_replace('\\', '/', $path);
			$path = __DIR__ . '/../../../lib' . $path . '.php';
			if (!file_exists($path)) {
				$output->writeln('<error>The class of the follow namespase is not implemented: ' . $commandNamespace . '</error>');
				return 1;
			}
			$code = file_get_contents($path);
			preg_match("/->setName\('(?<command>[\w:\-_]+)'\)/", $code, $matches);
			if (!array_key_exists('command', $matches)) {
				preg_match("/\tname: '(?<command>[\w:\-_]+)',?\n/", $code, $matches);
				if (!array_key_exists('command', $matches)) {
					$output->writeln('<error>A command need to have a name. Namespace: ' . $commandNamespace . '</error>');
					return 1;
				}
			}
			$command = $matches['command'];
			$this->updateDocumentation($commandNamespace, $command);
		}
		fclose($this->doc);
		return 0;
	}

	protected function updateDocumentation(string $namespace, string $commandName): void {
		$output = new BufferedOutput();

		$command = \OC::$server->get($namespace);

		$output->write('## ' . $command->getName() . "\n\n");
		$output->write($command->getDescription() . "\n\n");
		$output->write(
			'### Usage' . "\n\n" .
			array_reduce(array_merge([$command->getSynopsis()], $command->getAliases(), $command->getUsages()), function ($carry, $usage) {
				return $carry.'* `'.$usage.'`'."\n";
			})
		);
		$this->describeInputDefinition($command, $output);
		$output->write("\n\n");

		fwrite($this->doc, $output->fetch());
		return;
	}

	protected function describeInputDefinition(Command $command, OutputInterface $output): void {
		$definition = $command->getDefinition();
		if ($showArguments = \count($definition->getArguments()) > 0) {
			$output->write("\n");
			$output->write('### Arguments');
			foreach ($definition->getArguments() as $argument) {
				$output->write("\n\n");
				if (null !== $describeInputArgument = $this->describeInputArgument($argument, $output)) {
					$output->write($describeInputArgument);
				}
			}
		}

		if (\count($definition->getOptions()) > 0) {
			if ($showArguments) {
				$output->write("\n\n");
			}

			$output->write('### Options');
			foreach ($definition->getOptions() as $option) {
				$output->write("\n\n");
				if (null !== $describeInputOption = $this->describeInputOption($option, $output)) {
					$output->write($describeInputOption);
				}
			}
		}
	}

	protected function describeInputArgument(InputArgument $argument, OutputInterface $output): void {
		$output->write(
			'#### `'.($argument->getName() ?: '<none>')."`\n\n"
			.($argument->getDescription() ? preg_replace('/\s*[\r\n]\s*/', "\n", $argument->getDescription())."\n\n" : '')
			.'* Is required: '.($argument->isRequired() ? 'yes' : 'no')."\n"
			.'* Is array: '.($argument->isArray() ? 'yes' : 'no')."\n"
			.'* Default: `'.str_replace("\n", '', var_export($argument->getDefault(), true)).'`'
		);
	}

	protected function describeInputOption(InputOption $option, OutputInterface $output) {
		$name = '--'.$option->getName();
		if ($option->isNegatable()) {
			$name .= '|--no-'.$option->getName();
		}
		if ($option->getShortcut()) {
			$name .= '|-'.str_replace('|', '|-', $option->getShortcut()).'';
		}

		$output->write(
			'#### `'.$name.'`'."\n\n"
			.($option->getDescription() ? preg_replace('/\s*[\r\n]\s*/', "\n", $option->getDescription())."\n\n" : '')
			.'* Accept value: '.($option->acceptValue() ? 'yes' : 'no')."\n"
			.'* Is value required: '.($option->isValueRequired() ? 'yes' : 'no')."\n"
			.'* Is multiple: '.($option->isArray() ? 'yes' : 'no')."\n"
			.'* Is negatable: '.($option->isNegatable() ? 'yes' : 'no')."\n"
			.'* Default: `'.str_replace("\n", '', var_export($option->getDefault(), true)).'`'
		);
	}
}
