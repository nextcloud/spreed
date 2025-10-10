<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
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
use OCP\Security\ISecureRandom;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	public function __construct(
		private BotService $botService,
		private BotServerMapper $botServerMapper,
		private ISecureRandom $secureRandom,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:create')
			->setDescription('Creates a new bot on the server with \'response\' feature only.')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The name under which the messages will be posted (min. 1 char, max. 64 chars)'
			)
			->addArgument(
				'description',
				InputArgument::OPTIONAL,
				'Optional description shown in the admin settings (max. 4000 chars)'
			)
			->addOption(
				'secret',
				's',
				InputOption::VALUE_REQUIRED,
				'Secret used to validate API calls (min. 40 chars, max. 128 chars). When none is provided, a random 64 chars string is generated and output.'
			)
			->addOption(
				'no-setup',
				null,
				InputOption::VALUE_NONE,
				'Prevent moderators from setting up the bot in a conversation'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$description = $input->getArgument('description') ?? '';
		$noSetup = $input->getOption('no-setup');
		$featureFlags = Bot::FEATURE_RESPONSE;

		$secret = $input->getOption('secret') ?? $this->secureRandom->generate(64);
		$url = Bot::URL_RESPONSE_ONLY_PREFIX . bin2hex(random_bytes(16));

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
				$output->writeln('<error>' . get_class($e) . ': ' . $e->getMessage() . '</error>');
				return 1;
			}
		}

		$output->writeln('<info>Bot installed</info>');
		$output->writeln('ID: ' . $botEntity->getId());

		if ($input->getOption('secret') === null) {
			$output->writeln('Secret: ' . $secret);
		}

		return 0;
	}
}
