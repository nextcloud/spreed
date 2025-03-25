<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\User;

use OC\Core\Command\Base;
use OCA\Talk\Manager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Base {

	public function __construct(
		private IUserManager $userManager,
		private Manager $manager,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:user:remove')
			->setDescription('Remove a user from all their rooms')
			->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Remove the given users from all rooms'
			)
			->addOption(
				'private-only',
				null,
				InputOption::VALUE_NONE,
				'Only remove the user from private rooms, retaining membership in public and open conversations as well as one-to-ones'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userIds = $input->getOption('user');
		$privateOnly = $input->getOption('private-only');

		$users = [];
		foreach ($userIds as $userId) {
			$user = $this->userManager->get($userId);
			if (!$user) {
				$output->writeln('<error>' . sprintf("User '%s' not found.", $userId) . '</error>');
				return 1;
			}
			$users[] = $user;
		}

		foreach ($users as $user) {
			$this->manager->removeUserFromAllRooms($user, $privateOnly);
		}

		$output->writeln('<info>Users successfully removed from all rooms.</info>');
		return 0;
	}
}
