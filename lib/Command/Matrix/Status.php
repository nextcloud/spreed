<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Matrix;

use OC\Core\Command\Base;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Base {
	public function __construct(
		private readonly MatrixConfig $config,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly ITimeFactory $timeFactory,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:matrix:status')
			->setDescription('Health of the Matrix integration (use --output=json for monitoring)');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$status = [
			'enabled' => $this->config->isEnabled(),
			'accounts' => $this->accountMapper->getStatistics($this->timeFactory->getDateTime()),
			'rooms' => count($this->roomMapper->getAll()),
			'undecryptable' => $this->roomMapper->countUndecryptable(),
			'settings' => $this->config->getOperationalSettings(),
		];
		$this->writeArrayInOutputFormat($input, $output, $status);
		return $status['accounts']['error'] > 0 ? 2 : 0;
	}
}
