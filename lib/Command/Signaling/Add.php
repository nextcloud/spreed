<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Signaling;

use OC\Core\Command\Base;
use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Add extends Base {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:signaling:add')
			->setDescription('Add an external signaling server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A server string, ex. wss://signaling.example.org'
			)->addArgument(
				'secret',
				InputArgument::OPTIONAL,
				'A shared secret string.'
			)->addOption(
				'verify',
				null,
				InputOption::VALUE_NONE,
				'DEPRECATED: added servers are verified by default. use --skip-verify to disable it.'
			)->addOption(
				'skip-verify',
				null,
				InputOption::VALUE_NONE,
				'Do not validate SSL certificate if set.'
			)->addOption(
				'password-from-env',
				null,
				InputOption::VALUE_NONE,
				'Read secret from environment variable NC_PASS or interactively from prompt if environment variable is not set.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		$secret = $input->getArgument('secret');
		$skipVerify = $input->getOption('skip-verify');
		$passwordFromEnv = $input->getOption('password-from-env');

		$verify = $skipVerify ? false : true;

		// quick validation, similar to signaling-server.js
		if (trim($server) === '') {
			$output->writeln('<error>Server cannot be empty.</error>');
			return 1;
		}
		$secret = $secret ? trim($secret) : '';
		if (empty($secret) && !$passwordFromEnv) {
			$output->writeln('<error>Secret cannot be empty.</error>');
			return 1;
		}

		if (!empty($secret) && $passwordFromEnv) {
			$output->writeln('<info>Secret cannot be given when reading from environment.</info>');
			return 1;
		}

		if ($passwordFromEnv) {
			$secret = getenv('NC_PASS') ?: '';
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

			if (empty($secret)) {
				$output->writeln('<error>Secret cannot be empty.</error>');
			}

			$secret = $secret ? trim($secret) : '';
		}

		$signaling_servers = $this->appConfig->getAppValueArray(Config::SIGNALING_SERVERS);
		if (empty($signaling_servers)) {
			$servers = [];
		} else {
			$servers = $signaling_servers['servers'];
		}
		$server = mb_strtolower(trim($server));
		if (in_array($server, array_column($servers, 'server'), true)) {
			$output->writeln('<info>Server ' . $server . ' already exists.</info>');
			return 1;
		}
		$servers[] = [
			'server' => $server,
			'verify' => $verify,
		];
		$signaling = [
			'servers' => $servers,
			'secret' => $secret,
		];

		$this->appConfig->setAppValueArray(Config::SIGNALING_SERVERS, $signaling);
		$this->appConfig->setAppValueString(Config::SIGNALING_SERVERS_SECRET, $secret);
		$output->writeln('<info>Added signaling server ' . $server . '.</info>');

		return 0;
	}
}
