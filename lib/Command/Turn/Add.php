<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Turn;

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
			->setName('talk:turn:add')
			->setDescription('Add a TURN server.')
			->addArgument(
				'schemes',
				InputArgument::REQUIRED,
				'Schemes, can be turn or turns or turn,turns.'
			)->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name, ex. turn.nextcloud.com'
			)->addArgument(
				'protocols',
				InputArgument::REQUIRED,
				'Protocols, can be udp or tcp or udp,tcp.'
			)->addOption(
				'secret',
				null,
				InputOption::VALUE_REQUIRED,
				'A shard secret string'
			)->addOption(
				'generate-secret',
				null,
				InputOption::VALUE_NONE,
				'Generate secret if set.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$schemes = $input->getArgument('schemes');
		$server = $input->getArgument('server');
		$protocols = $input->getArgument('protocols');
		$secret = $input->getOption('secret');
		$generate = $input->getOption('generate-secret');

		if (!in_array($schemes, ['turn', 'turns', 'turn,turns'])) {
			$output->writeln('<error>Not allowed schemes, must be turn or turns or turn,turns.</error>');
			return 1;
		}
		if (!in_array($protocols, ['tcp', 'udp', 'udp,tcp'])) {
			$output->writeln('<error>Not allowed protocols, must be udp or tcp or udp,tcp.</error>');
			return 1;
		}
		// quick validation, similar to turn-server.js
		if (trim($server) === '') {
			$output->writeln('<error>Server cannot be empty.</error>');
			return 1;
		}
		if (($generate === false && $secret === null)
			|| ($generate && $secret !== null)) {
			$output->writeln('<error>You must provide --secret or --generate-secret.</error>');
			return 1;
		}
		if (!$generate && trim($secret) === '') {
			$output->writeln('<error>Secret cannot be empty.</error>');
			return 1;
		}
		if ($generate) {
			$secret = $this->getUniqueSecret();
		}
		if (stripos($server, 'https://') === 0) {
			$server = substr($server, 8);
		}
		if (stripos($server, 'http://') === 0) {
			$server = substr($server, 7);
		}

		$config = $this->config->getAppValue('spreed', 'turn_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			$servers = [];
		}

		//Checking if the server is already added
		foreach ($servers as $existingServer) {
			if (
				$existingServer['schemes'] === $schemes
				&& $existingServer['server'] === $server
				&& $existingServer['protocols'] === $protocols
			) {
				$output->writeln('<error>Server already exists with the same configuration.</error>');
				return 1;
			}
		}


		$servers[] = [
			'schemes' => $schemes,
			'server' => $server,
			'secret' => $secret, // @todo: check the order
			'protocols' => $protocols,
		];

		$this->config->setAppValue('spreed', 'turn_servers', json_encode($servers));
		$output->writeln('<info>Added ' . $server . '.</info>');
		return 0;
	}

	protected function getUniqueSecret(): string {
		return sha1(uniqid('', true));
	}
}
