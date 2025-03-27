<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\PhoneNumber;

use OC\Core\Command\Base;
use OCA\Talk\Model\PhoneNumberMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveAccount extends Base {

	public function __construct(
		private PhoneNumberMapper $mapper,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:phone-number:remove-account')
			->setDescription('Remove mapping entries by account')
			->addArgument(
				'account',
				InputArgument::REQUIRED,
				'Account to remove all mapping entries for',
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('account');
		$this->mapper->deleteByUser($userId);
		return self::SUCCESS;
	}
}
