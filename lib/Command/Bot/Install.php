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
use OCA\Talk\Service\BotService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Base {
	public function __construct(
		private BotService $botService,
		private BotServerMapper $botServerMapper,
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
				'The name under which the messages will be posted (min. 1 char, max. 64 chars)'
			)
			->addArgument(
				'secret',
				InputArgument::REQUIRED,
				'Secret used to validate API calls (min. 40 chars, max. 128 chars)'
			)
			->addArgument(
				'url',
				InputArgument::REQUIRED,
				'Webhook endpoint to post messages to (max. 4000 chars)'
			)
			->addArgument(
				'description',
				InputArgument::OPTIONAL,
				'Optional description shown in the admin settings (max. 4000 chars)'
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
		$name = $input->getArgument('name');
		$secret = $input->getArgument('secret');
		$url = $input->getArgument('url');
		$description = $input->getArgument('description') ?? '';
		$noSetup = $input->getOption('no-setup');

		if (!empty($input->getOption('feature'))) {
			$featureFlags = Bot::featureLabelsToFlags($input->getOption('feature'));
		} else {
			$featureFlags = Bot::FEATURE_WEBHOOK + Bot::FEATURE_RESPONSE;
		}

		try {
			$this->botService->validateBotParameters($name, $secret, $url, $description);
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		try {
			$this->botServerMapper->findByUrl($url);
			$output->writeln('<error>Bot with the same URL is already registered</error>');
			return 2;
		} catch (DoesNotExistException) {
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
			if ($e instanceof Exception && $e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				$output->writeln('<error>Bot with the same secret is already registered</error>');
				return 3;
			} else {
				$output->writeln('<error>' . get_class($e) . ': ' . $e->getMessage() . '</error>');
				return 1;
			}
		}


		$output->writeln('<info>Bot installed</info>');
		return 0;
	}
}
