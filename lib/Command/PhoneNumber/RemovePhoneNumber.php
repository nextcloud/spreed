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

class RemovePhoneNumber extends Base {

	public function __construct(
		private PhoneNumberMapper $mapper,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:phone-number:remove')
			->setDescription('Remove a mapping entry by phone number')
			->addArgument(
				'phone',
				InputArgument::REQUIRED,
				'Phone number to remove the mapping entry for',
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$phoneNumber = $input->getArgument('phone');
		$this->mapper->deleteByPhoneNumber($phoneNumber);
		return self::SUCCESS;
	}
}
