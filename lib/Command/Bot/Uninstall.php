<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
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
		$botId = (int)$input->getArgument('id');

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
