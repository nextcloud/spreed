<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Stun;

use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
			->setName('talk:stun:add')
			->setDescription('Add a new STUN server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name and port number separated by the colons, ex. stun.nextcloud.com:443'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		// check input, similar to stun-server.js
		$host = parse_url($server, PHP_URL_HOST);
		$port = parse_url($server, PHP_URL_PORT);
		if (empty($host) || empty($port)) {
			$output->writeln('<error>Incorrect value. Must be stunserver:port.</error>');
			return 1;
		}

		$config = $this->config->getAppValue('spreed', 'stun_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			$servers = [];
		}

		// check if the server is already in the list
		foreach ($servers as $existingServer) {
			if ($existingServer === "$host:$port") {
				$output->writeln('<error>Server already exists.</error>');
				return 1;
			}
		}

		$servers[] = "$host:$port";

		$this->config->setAppValue('spreed', 'stun_servers', json_encode($servers));
		$output->writeln('<info>Added ' . "$host:$port" . '.</info>');
		return 0;
	}
}
