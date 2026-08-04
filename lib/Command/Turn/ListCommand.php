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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:turn:list')
			->setDescription('List TURN servers.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$servers = $this->appConfig->getAppValueArray(Config::TURN_SERVERS);
		$this->writeMixedInOutputFormat($input, $output, $servers);
		return 0;
	}
}
