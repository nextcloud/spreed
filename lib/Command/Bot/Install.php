<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Service\BotService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Install extends Base {
	public function __construct(
		private readonly BotService $botService,
		private readonly BotServerMapper $botServerMapper,
	) {
		parent::__construct();
	}

	#[\Override]
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
				'Secret used to validate API calls (min. 40 chars, max. 128 chars).'
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
				. ' - event: The bot reads posted messages from local events' . "\n"
				. ' - reaction: The bot is notified about adding and removing of reactions' . "\n"
				. ' - none: When all features should be disabled for the bot'
			)
			->addOption(
				'password-from-env',
				null,
				InputOption::VALUE_NONE,
				'Read secret from environment variable NC_PASS/OC_PASS or interactively from prompt if environment variable is not set.'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$secret = $input->getArgument('secret');
		$url = $input->getArgument('url');
		$description = $input->getArgument('description') ?? '';
		$noSetup = $input->getOption('no-setup');
		$passwordFromEnv = $input->getOption('password-from-env');

		if ($passwordFromEnv) {
			$output->writeln('<info>Secret given as command line argument will be ignored because --password-from-env was passed</info>');
		}

		if (!empty($input->getOption('feature'))) {
			$featureFlags = Bot::featureLabelsToFlags($input->getOption('feature'));
			if (str_starts_with($url, Bot::URL_APP_PREFIX)) {
				$featureFlags &= ~Bot::FEATURE_WEBHOOK;
			}
		} elseif (str_starts_with($url, Bot::URL_APP_PREFIX)) {
			$featureFlags = Bot::FEATURE_EVENT;
		} else {
			$featureFlags = Bot::FEATURE_WEBHOOK + Bot::FEATURE_RESPONSE;
		}

		if ($passwordFromEnv) {
			$secret = getenv('NC_PASS') ?: getenv('OC_PASS');
			$secret = $secret ? trim($secret) : '';
		}

		if (empty($secret) && $passwordFromEnv && $input->isInteractive()) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');

			$question = new Question('Enter secret: ');
			$question->setHidden(true);
			$secret = $helper->ask($input, $output, $question);

			$question = new Question('Confirm secret: ');
			$question->setHidden(true);
			$confirmSecret = $helper->ask($input, $output, $question);

			if ($secret !== $confirmSecret) {
				$output->writeln('<error>Secrets did not match!</error>');
				return 1;
			}

			$secret = $secret ? trim($secret) : '';
		}

		if ($featureFlags & Bot::FEATURE_EVENT
			&& ($featureFlags & Bot::FEATURE_WEBHOOK
				|| $featureFlags & Bot::FEATURE_RESPONSE
				|| $featureFlags & Bot::FEATURE_REACTION)) {
			$output->writeln('<error>Bots with feature "event" can not support "webhook", "response" or "reaction" feature. They are mutual exclusive</error>');
			return 1;
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
			$botEntity = $this->botServerMapper->insert($bot);
		} catch (\Exception $e) {
			if ($e instanceof Exception && $e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				$output->writeln('<error>Bot with the same secret is already registered</error>');
				return 3;
			} else {
				$output->writeln('<error>' . $e::class . ': ' . $e->getMessage() . '</error>');
				return 1;
			}
		}

		$output->writeln('<info>Bot installed</info>');
		$output->writeln('ID: ' . $botEntity->getId());
		return 0;
	}
}
