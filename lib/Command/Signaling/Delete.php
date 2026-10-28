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
			->setName('talk:signaling:delete')
			->setDescription('Remove an existing signaling server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'An external signaling server string, ex. wss://signaling.example.org'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');

		$signaling = $this->appConfig->getAppValueArray(Config::SIGNALING_SERVERS);
		if (empty($signaling)) {
			$signaling = [
				'servers' => [],
				'secret' => '',
			];
		}
		$count = count($signaling['servers']);
		// remove all occurrences of $server
		$servers = array_filter($signaling['servers'], fn ($s) => $s['server'] !== $server);
		$signaling['servers'] = array_values($servers); // reindex

		$this->appConfig->setAppValueArray(Config::SIGNALING_SERVERS, $signaling);
		$this->appConfig->setAppValueString(Config::SIGNALING_SERVERS_SECRET, $signaling['secret']);
		if ($count > count($signaling['servers'])) {
			$output->writeln('<info>Deleted ' . $server . '.</info>');
		} else {
			$output->writeln('<info>There is nothing to delete.</info>');
		}
		return 0;
	}
}
