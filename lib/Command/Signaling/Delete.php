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

		$config = $this->config->getAppValue('spreed', 'signaling_servers');
		$signaling = json_decode($config, true);
		if ($signaling === null || empty($signaling) || !is_array($signaling)) {
			$signaling = [
				'servers' => [],
				'secret' => '',
			];
		}
		$count = count($signaling['servers']);
		// remove all occurrences of $server
		$servers = array_filter($signaling['servers'], function ($s) use ($server) {
			return $s['server'] !== $server;
		});
		$signaling['servers'] = array_values($servers); // reindex

		$this->config->setAppValue('spreed', 'signaling_servers', json_encode($signaling));
		if ($count > count($signaling['servers'])) {
			$output->writeln('<info>Deleted ' . $server . '.</info>');
		} else {
			$output->writeln('<info>There is nothing to delete.</info>');
		}
		return 0;
	}
}
