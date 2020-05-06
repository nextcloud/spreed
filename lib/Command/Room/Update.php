<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Command\Room;

use Exception;
use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Update extends Base {
	use TRoomCommand;

	/** @var IUserManager */
	public $userManager;

	/** @var Manager */
	public $manager;

	public function __construct(IUserManager $userManager, Manager $manager) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->manager = $manager;
	}

	protected function configure(): void {
		$this
			->setName('talk:room:update')
			->setDescription('Updates a room')
			->addArgument(
				'token',
				InputArgument::REQUIRED,
				'The token of the room to update'
			)->addOption(
				'name',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets a new name for the room'
			)->addOption(
				'public',
				null,
				InputOption::VALUE_REQUIRED,
				'Modifies the room to be a public room (value 1) or private room (value 0)'
			)->addOption(
				'readonly',
				null,
				InputOption::VALUE_REQUIRED,
				'Modifies the room to be read-only (value 1) or read-write (value 0)'
			)->addOption(
				'password',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets a new password for the room; pass an empty value to remove password protection'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int {
		$token = $input->getArgument('token');
		$name = $input->getOption('name');
		$public = $input->getOption('public');
		$readOnly = $input->getOption('readonly');
		$password = $input->getOption('password');

		if (!in_array($public, [null, '0', '1'], true)) {
			$output->writeln('<error>Invalid value for option "--public" given.</error>');
			return 1;
		}

		if (!in_array($readOnly, [null, '0', '1'], true)) {
			$output->writeln('<error>Invalid value for option "--readonly" given.</error>');
			return 1;
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$output->writeln('<error>Room not found.</error>');
			return 1;
		}

		if (!in_array($room->getType(), [Room::GROUP_CALL, Room::PUBLIC_CALL], true)) {
			$output->writeln('<error>Room is no group call.</error>');
			return 1;
		}

		try {
			if ($name !== null) {
				$this->setRoomName($room, $name);
			}

			if ($public !== null) {
				$this->setRoomPublic($room, ($public === '1'));
			}

			if ($readOnly !== null) {
				$this->setRoomReadOnly($room, ($readOnly === '1'));
			}

			if ($password !== null) {
				$this->setRoomPassword($room, $password);
			}
		} catch (Exception $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}

		$output->writeln('<info>Room successfully updated.</info>');
		return 0;
	}
}
