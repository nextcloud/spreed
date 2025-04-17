<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\PhoneNumber;

use OC\Core\Command\Base;
use OCA\Talk\Model\PhoneNumberMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FindPhoneNumber extends Base {

	public function __construct(
		private PhoneNumberMapper $mapper,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:phone-number:find')
			->setDescription('Find a phone number or the phone number of an user')
			->addOption(
				'phone',
				null,
				InputOption::VALUE_REQUIRED,
				'Phone number to search for',
			)
			->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED,
				'User to get number(s) for',
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$phoneNumber = (string)$input->getOption('phone');
		$userId = (string)$input->getOption('user');

		if ($phoneNumber !== '') {
			try {
				$entry = $this->mapper->findByPhoneNumber($phoneNumber);
			} catch (DoesNotExistException) {
				$output->writeln('<error>Phone number ' . $phoneNumber . ' could not be found</error>');
				return self::FAILURE;
			}
			$output->writeln('Phone number ' . $entry->getPhoneNumber() . ' is assigned to ' . $entry->getActorId());
			return self::SUCCESS;
		}

		if ($userId === '') {
			$output->writeln('<error>Neither phone number nor user provided</error>');
			return self::FAILURE;
		}

		$entries = $this->mapper->findByUser($userId);
		if (empty($entries)) {
			$output->writeln('<error>No phone number found for ' . $userId . '</error>');
			return self::FAILURE;
		}

		if (count($entries) === 1) {
			$entry = array_pop($entries);
			$output->writeln($entry->getActorId() . ' has phone number ' . $entry->getPhoneNumber() . ' assigned');
		} else {
			$output->writeln($userId . ' has the following phone numbers assigned:');
			foreach ($entries as $entry) {
				$output->writeln(' - ' . $entry->getPhoneNumber());
			}
		}

		return self::SUCCESS;
	}
}
