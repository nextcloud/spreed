<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Stun;

use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {

	public function __construct(
		private IConfig $config,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:stun:list')
			->setDescription('List STUN servers.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$config = $this->config->getAppValue('spreed', 'stun_servers');
		$servers = json_decode($config);
		if (!is_array($servers)) {
			$servers = [];
		}

		$this->writeArrayInOutputFormat($input, $output, $servers);
		return 0;
	}
}
