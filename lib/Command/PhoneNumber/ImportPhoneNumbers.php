<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\PhoneNumber;

use OC\Core\Command\Base;
use OCA\Talk\Model\PhoneNumber;
use OCA\Talk\Model\PhoneNumberMapper;
use OCP\IDBConnection;
use OCP\IPhoneNumberUtil;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPhoneNumbers extends Base {

	public function __construct(
		private IUserManager $userManager,
		private IPhoneNumberUtil $phoneNumberUtil,
		private PhoneNumberMapper $mapper,
		private IDBConnection $db,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:phone-number:import')
			->setDescription('Import a CSV list (format: "number","user") for SIP dial-in')
			->addOption(
				'reset',
				null,
				InputOption::VALUE_NONE,
				'Delete all phone numbers before importing',
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force the numbers to the given user even when they are assigned already',
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$reset = (bool)$input->getOption('reset');
		$force = (bool)$input->getOption('force');

		$this->db->beginTransaction();
		if ($reset) {
			$this->db->truncateTable('talk_phone_numbers', false);
			$force = false;
		}

		$handle = $this->getResourceFromStdin();
		if ($handle === false) {
			$output->writeln('<error>Invalid StdIn provided</error>');
			return self::FAILURE;
		}

		$map = [];
		while ($row = fgetcsv($handle, escape: '')) {
			if (count($row) !== 2 || $row[0] === '' || $row[1] === '') {
				continue;
			}

			$phoneNumberStandard = preg_match('/^[0-9]{1,20}$/', $row[0]) ? $row[0] : $this->phoneNumberUtil->convertToStandardFormat($row[0]);
			if ($phoneNumberStandard === null) {
				$output->writeln('<error>Not a valid phone number ' . $row[0] . '. The format is invalid.</error>');
				return self::FAILURE;
			}
			$row[0] = $phoneNumberStandard;

			$user = $this->userManager->get($row[1]);
			if (!$user instanceof IUser) {
				$output->writeln('<error>Invalid user "' . $row[1] . '" provided</error>');
				return self::FAILURE;
			}
			$row[1] = $user->getUID();

			$map[$row[0]] = $row[1];
		}

		$entries = $this->mapper->findByPhoneNumbers(array_keys($map));

		if (!$force && !empty($entries)) {
			$output->writeln('<error>Phone number already assigned:</error>');
			foreach ($entries as $entry) {
				$output->writeln(' - ' . $entry->getPhoneNumber());
			}
			return self::FAILURE;
		}

		foreach ($map as $phoneNumber => $userId) {
			$entry = new PhoneNumber();
			$entry->setPhoneNumber($phoneNumber);
			$entry->setActorId($userId);
			$this->mapper->insert($entry);

			$output->writeln('<info>Phone number ' . $entry->getPhoneNumber() . ' is now assigned to ' . $entry->getActorId() . '</info>');
		}

		$this->db->commit();
		return self::SUCCESS;
	}

	/**
	 * Get the resource from stdin ("talk:phone-numbers:import < file.csv")
	 * @return resource|false
	 */
	protected function getResourceFromStdin() {
		return fopen('php://stdin', 'rb');
	}
}
