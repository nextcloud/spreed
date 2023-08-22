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
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCP\Http\Client\IClientService;
use OCP\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Base {
	public function __construct(
		private BotServerMapper $botServerMapper,
		private IClientService $clientService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:install')
			->setDescription('Install a new bot on the server')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The name under which the messages will be posted'
			)
			->addArgument(
				'secret',
				InputArgument::REQUIRED,
				'Secret used to validate API calls'
			)
			->addArgument(
				'url',
				InputArgument::REQUIRED,
				'Webhook endpoint to post messages to'
			)
			->addArgument(
				'description',
				InputArgument::OPTIONAL,
				'Optional description shown in the admin settings'
			)
			->addOption(
				'no-setup',
				null,
				InputOption::VALUE_NONE,
				'Prevent moderators from setting up the bot in a conversation'
			)
			->addOption(
				'feature',
				'f',
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Specify the list of features for the bot' . "\n"
				. ' - webhook: The bot receives posted chat messages as webhooks' . "\n"
				. ' - response: The bot can post messages and reactions as a response' . "\n"
				. ' - none: When all features should be disabled for the bot'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$client = $this->clientService->newClient();
		if (!method_exists($client, 'postAsync')) {
			$output->writeln('<error>You need Nextcloud Server version 27.1 or higher for Bot support (detected: ' . implode('.', Util::getVersion()) . ').</error>');
			return 1;
		}

		$name = $input->getArgument('name');
		$secret = $input->getArgument('secret');
		$url = $input->getArgument('url');
		$description = $input->getArgument('description');
		$noSetup = $input->getOption('no-setup');

		if (!empty($input->getOption('feature'))) {
			$featureFlags = Bot::featureLabelsToFlags($input->getOption('feature'));
		} else {
			$featureFlags = Bot::FEATURE_WEBHOOK + Bot::FEATURE_RESPONSE;
		}

		$bot = new BotServer();
		$bot->setName($name);
		$bot->setSecret($secret);
		$bot->setUrl($url);
		$bot->setUrlHash(sha1($url));
		$bot->setDescription($description);
		$bot->setState($noSetup ? Bot::STATE_NO_SETUP : Bot::STATE_ENABLED);
		$bot->setFeatures($featureFlags);
		try {
			$this->botServerMapper->insert($bot);
		} catch (\Exception $e) {
			$output->writeln('<error>' . get_class($e) . ': ' . $e->getMessage() . '</error>');
			return 1;
		}


		$output->writeln('<info>Bot installed</info>');
		return 0;
	}
}
