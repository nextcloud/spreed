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

class Delete extends Base {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:stun:delete')
			->setDescription('Remove an existing STUN server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name and port number separated by the colons, ex. stun.nextcloud.com:443'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');

		$servers = $this->appConfig->getAppValueArray(Config::STUN_SERVERS);

		$count = count($servers);
		// remove all occurrences of $server
		$servers = array_filter($servers, fn ($s) => $s !== $server);
		$servers = array_values($servers); // reindex

		if (empty($servers)) {
			$servers = [Config::DEFAULT_STUN_SERVER];
			$this->appConfig->setAppValueArray(Config::STUN_SERVERS, $servers);
			$output->writeln('<info>You deleted all STUN servers. A default STUN server was added.</info>');
		} else {
			$this->appConfig->setAppValueArray(Config::STUN_SERVERS, $servers);
			if ($count > count($servers)) {
				$output->writeln('<info>Deleted ' . $server . '.</info>');
			} else {
				$output->writeln('<info>There is nothing to delete.</info>');
			}
		}
		return 0;
	}
}
