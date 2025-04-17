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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IPhoneNumberUtil;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddPhoneNumber extends Base {

	public function __construct(
		private IUserManager $userManager,
		private IPhoneNumberUtil $phoneNumberUtil,
		private PhoneNumberMapper $mapper,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:phone-number:add')
			->setDescription('Add a mapping entry to map a phone number to an user')
			->addArgument(
				'phone',
				InputArgument::REQUIRED,
				'Phone number that will be called',
			)
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'User to be added to the conversation',
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force the number to the given user even when it is assigned already',
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$phoneNumber = $input->getArgument('phone');
		$userId = $input->getArgument('user');
		$force = (bool)$input->getOption('force');

		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			$output->writeln('<error>Invalid user "' . $userId . '" provided</error>');
			return self::FAILURE;
		}
		$userId = $user->getUID();

		$phoneNumberStandard = preg_match('/^[0-9]{1,20}$/', $phoneNumber) ? $phoneNumber : $this->phoneNumberUtil->convertToStandardFormat($phoneNumber);
		if ($phoneNumberStandard === null) {
			$output->writeln('<error>Not a valid phone number ' . $phoneNumber . '. The format is invalid.</error>');
			return self::FAILURE;
		}
		$phoneNumber = $phoneNumberStandard;

		try {
			$entry = $this->mapper->findByPhoneNumber($phoneNumber);
		} catch (DoesNotExistException) {
			$entry = null;
		}

		if ($entry !== null) {
			$oldActor = $entry->getActorId();
			if (!$force) {
				$output->writeln('<error>Phone number is already assigned to ' . $oldActor . '</error>');
				return self::FAILURE;
			}

			$entry->setActorId($userId);
			$this->mapper->update($entry);

			$output->writeln('<info>Phone number ' . $entry->getPhoneNumber() . ' is now assigned to ' . $entry->getActorId() . '</info>');
			$output->writeln('Was assigned to ' . $oldActor . ' before');
			return self::SUCCESS;
		}

		$entry = new PhoneNumber();
		$entry->setPhoneNumber($phoneNumber);
		$entry->setActorId($userId);
		$this->mapper->insert($entry);

		$output->writeln('<info>Phone number ' . $entry->getPhoneNumber() . ' is now assigned to ' . $entry->getActorId() . '</info>');
		return self::SUCCESS;
	}
}
