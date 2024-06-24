<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall extends Base {
	public function __construct(
		private BotConversationMapper $botConversationMapper,
		private BotServerMapper $botServerMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:uninstall')
			->setDescription('Uninstall a bot from the server')
			->addArgument(
				'id',
				InputArgument::OPTIONAL,
				'The ID of the bot'
			)
			->addOption(
				'url',
				null,
				InputOption::VALUE_REQUIRED,
				'The URL of the bot (required when no ID is given, ignored otherwise)'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$botId = (int) $input->getArgument('id');

		try {
			if ($botId === 0) {
				$url = $input->getOption('url');
				if ($url === null) {
					$output->writeln('<error>URL is required when no ID is given</error>');
					return 1;
				}
				$bot = $this->botServerMapper->findByUrl($url);
			} else {
				$bot = $this->botServerMapper->findById($botId);
			}
		} catch (DoesNotExistException) {
			$output->writeln('<error>Bot not found</error>');
			return 1;
		}

		$this->botConversationMapper->deleteByBotId($bot->getId());
		$this->botServerMapper->deleteById($bot->getId());

		$output->writeln('<info>Bot uninstalled</info>');
		return 0;
	}
}
