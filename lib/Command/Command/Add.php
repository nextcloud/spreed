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

use OCA\Talk\Service\CommandService;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {
	use TRenderCommand;

	/** @var CommandService */
	private $service;

	public function __construct(CommandService $service) {
		parent::__construct();
		$this->service = $service;
	}

	protected function configure(): void {
		$this
			->setName('talk:command:add')
			->setDescription('Add a new command')
			->addArgument(
				'cmd',
				InputArgument::REQUIRED,
				'The command as used in the chat "/help" => "help"'
			)
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'Name of the user posting the response'
			)
			->addArgument(
				'script',
				InputArgument::REQUIRED,
				'Script to execute (Must be using absolute paths only)'
			)
			->addArgument(
				'response',
				InputArgument::REQUIRED,
				'Who should see the response: 0 - No one, 1 - User, 2 - All'
			)
			->addArgument(
				'enabled',
				InputArgument::REQUIRED,
				'Who can use this command: 0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$cmd = $input->getArgument('cmd');
		$name = $input->getArgument('name');
		$script = $input->getArgument('script');
		$response = (int) $input->getArgument('response');
		$enabled = (int) $input->getArgument('enabled');

		try {
			$command = $this->service->create('', $cmd, $name, $script, $response, $enabled);
		} catch (\InvalidArgumentException $e) {
			switch ($e->getCode()) {
				case 1:
					$output->writeln('<error>The command already exists or is invalid</error>');
					break;
				case 2:
					$output->writeln('<error>The name is invalid</error>');
					break;
				case 3:
					$output->writeln('<error>The script is invalid</error>');
					break;
				case 4:
					$output->writeln('<error>The response value is invalid</error>');
					break;
				case 5:
					$output->writeln('<error>The enabled value is invalid</error>');
					break;
				case 6:
					$output->writeln('<error>The placeholders {ROOM}, {USER} and {ARGUMENTS} must not be used inside quotes</error>');
					break;
				default:
					$output->writeln('<error>The command could not be added</error>');
					break;
			}
			return 1;
		}


		$output->writeln('<info>Command added</info>');
		$output->writeln('');
		$this->renderCommands(Base::OUTPUT_FORMAT_PLAIN, $output, [$command]);

		$output->writeln('');
		$output->writeln("<comment>If you think your command makes sense for other users as well, feel free to share it in the following github issue:\n https://github.com/nextcloud/spreed/issues/1566</comment>");
		return 0;
	}
}
