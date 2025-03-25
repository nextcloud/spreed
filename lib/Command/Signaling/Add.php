<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Signaling;

use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {

	public function __construct(
		private IConfig $config,
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
				InputArgument::REQUIRED,
				'A shared secret string.'
			)->addOption(
				'verify',
				null,
				InputOption::VALUE_NONE,
				'Validate SSL certificate if set.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		$secret = $input->getArgument('secret');
		$verify = $input->getOption('verify');

		// quick validation, similar to signaling-server.js
		if (trim($server) === '') {
			$output->writeln('<error>Server cannot be empty.</error>');
			return 1;
		}
		if (trim($secret) === '') {
			$output->writeln('<error>Secret cannot be empty.</error>');
			return 1;
		}

		$config = $this->config->getAppValue('spreed', 'signaling_servers');

		$signaling = json_decode($config, true);
		if ($signaling === null || empty($signaling) || !is_array($signaling)) {
			$servers = [];
		} else {
			$servers = is_array($signaling['servers']) ? $signaling['servers'] : [];
		}
		$servers[] = [
			'server' => $server,
			'verify' => $verify,
		];
		$signaling = [
			'servers' => $servers,
			'secret' => $secret,
		];

		$this->config->setAppValue('spreed', 'signaling_servers', json_encode($signaling));
		$output->writeln('<info>Added signaling server ' . $server . '.</info>');
		return 0;
	}
}
