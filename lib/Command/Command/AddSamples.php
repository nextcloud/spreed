<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Command\Command;

use OC\Core\Command\Base;
use OCA\Talk\Model\Command;
use OCA\Talk\Service\CommandService;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddSamples extends Base {
	use TRenderCommand;

	protected array $commands = [];

	public function __construct(
		private CommandService $service,
		protected IAppManager $appManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('talk:command:add-samples')
			->setDescription('Adds some sample commands: /wiki, …')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$appPath = $this->appManager->getAppPath('spreed');
		} catch (AppPathNotFoundException $e) {
			$output->writeln('<error>Could not determine the spreed/ app directory.</error>');
			return 1;
		}

		$this->installCommand(
			$output,
			'wiki',
			'Wikipedia',
			'php ' . $appPath . '/sample-commands/wikipedia.php {ARGUMENTS}'
		);

		$chmod = fileperms($appPath . '/sample-commands/calc.sh');
		if (!($chmod & 0x0040 || $chmod & 0x0008 || $chmod & 0x0001)) {
			$output->writeln('<error>sample-commands/calc.sh is not executable</error>');
		} elseif (!shell_exec('which bc')) {
			$output->writeln('<error>Can not add calculator command, because Basic calculator package (bc - https://www.gnu.org/software/bc/) is missing</error>');
		} else {
			$this->installCommand(
				$output,
				'calculator',
				'Calculator',
				$appPath . '/sample-commands/calc.sh {ARGUMENTS}',
				Command::RESPONSE_USER
			);

			$this->installCommand(
				$output,
				'calc',
				'Calculator',
				'alias:calculator'
			);
		}


		$this->installCommand(
			$output,
			'hackernews',
			'Hacker News',
			'php ' . $appPath . '/sample-commands/hackernews.php {ARGUMENTS}'
		);

		if (empty($this->commands)) {
			return 1;
		}

		$output->writeln('<info>Commands added</info>');
		$output->writeln('');
		$this->renderCommands($input, $output, $this->commands);
		return 0;
	}

	protected function installCommand(OutputInterface $output, string $command, string $name, string $script, int $resonse = Command::RESPONSE_ALL, int $enable = Command::ENABLED_ALL): void {
		try {
			$this->service->find('', $command);
			$output->writeln('<comment>Command ' . $command . ' already exists</comment>');
			return;
		} catch (DoesNotExistException $e) {
		}

		try {
			$this->commands[] = $this->service->create(
				'',
				$command,
				$name,
				$script,
				$resonse,
				$enable
			);
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>An error occured while setting up the ' . $command . ' command</error>');
		}
	}
}
