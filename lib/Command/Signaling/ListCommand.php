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
			->setName('talk:signaling:list')
			->setDescription('List external signaling servers.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$signaling = $this->appConfig->getAppValueArray(Config::SIGNALING_SERVERS);

		$this->writeMixedInOutputFormat($input, $output, $signaling);
		return 0;
	}
}
