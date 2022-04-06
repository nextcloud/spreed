<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Command\User;

use OC\Core\Command\Base;
use OCA\Talk\Manager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Base {
	private IUserManager $userManager;
	private Manager $manager;

	public function __construct(IUserManager $userManager,
								Manager $manager) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->manager = $manager;
	}

	protected function configure(): void {
		$this
			->setName('talk:user:remove')
			->setDescription('Remove a user from all their rooms')
			->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Remove the given users from all rooms'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userIds = $input->getOption('user');

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
			$this->manager->removeUserFromAllRooms($user);
		}

		$output->writeln('<info>Users successfully removed from all rooms.</info>');
		return 0;
	}
}
