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

class Delete extends Base {

	public function __construct(
		private IConfig $config,
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

		$config = $this->config->getAppValue('spreed', 'stun_servers');
		$servers = json_decode($config);
		if (! is_array($servers)) {
			$servers = [];
		}
		$count = count($servers);
		// remove all occurrences of $server
		$servers = array_filter($servers, function ($s) use ($server) {
			return $s !== $server;
		});
		$servers = array_values($servers); // reindex

		if (empty($servers)) {
			$servers = ['stun.nextcloud.com:443'];
			$this->config->setAppValue('spreed', 'stun_servers', json_encode($servers));
			$output->writeln('<info>You deleted all STUN servers. A default STUN server was added.</info>');
		} else {
			$this->config->setAppValue('spreed', 'stun_servers', json_encode($servers));
			if ($count > count($servers)) {
				$output->writeln('<info>Deleted ' . $server . '.</info>');
			} else {
				$output->writeln('<info>There is nothing to delete.</info>');
			}
		}
		return 0;
	}
}
