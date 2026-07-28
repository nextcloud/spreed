<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Turn;

use OC\Core\Command\Base;
use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Delete extends Base {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:turn:delete')
			->setDescription('Remove an existing TURN server.')
			->addArgument(
				'schemes',
				InputArgument::REQUIRED,
				'Schemes, can be turn or turns or turn,turns'
			)->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name, ex. turn.nextcloud.com'
			)->addArgument(
				'protocols',
				InputArgument::REQUIRED,
				'Protocols, can be udp or tcp or udp,tcp'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$schemes = $input->getArgument('schemes');
		$server = $input->getArgument('server');
		$protocols = $input->getArgument('protocols');

		$servers = $this->appConfig->getAppValueArray(Config::TURN_SERVERS);

		$count = count($servers);
		// remove all occurrences which match $schemes, $server and $protocols
		$servers = array_filter($servers, fn ($s) => $s['schemes'] !== $schemes || $s['server'] !== $server || $s['protocols'] !== $protocols);
		$servers = array_values($servers); // reindex

		$this->appConfig->setAppValueArray(Config::TURN_SERVERS, $servers);
		if ($count > count($servers)) {
			$output->writeln('<info>Deleted ' . $server . '.</info>');
		} else {
			$output->writeln('<info>There is nothing to delete.</info>');
		}
		return 0;
	}
}
