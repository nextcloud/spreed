<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Stun;

use OC\Core\Command\Base;
use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {

	public function __construct(
		private readonly IAppConfig $appConfig,
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

		$servers = $this->appConfig->getAppValueArray(Config::STUN_SERVERS);

		// check if the server is already in the list
		foreach ($servers as $existingServer) {
			if ($existingServer === "$host:$port") {
				$output->writeln('<error>Server already exists.</error>');
				return 1;
			}
		}

		$servers[] = "$host:$port";

		$this->appConfig->setAppValueArray(Config::STUN_SERVERS, $servers);
		$output->writeln('<info>Added ' . "$host:$port" . '.</info>');
		return 0;
	}
}
