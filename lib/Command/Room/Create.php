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
use OCA\Talk\Manager;
use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
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
			->setName('talk:room:create')
			->setDescription('Create a new room')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The name of the room to create'
			)->addOption(
				'user',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites the given users to the room to create'
			)->addOption(
				'group',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites all members of the given group to the room to create'
			)->addOption(
				'circle',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Invites all members of the given circle to the room to create'
			)->addOption(
				'public',
				null,
				InputOption::VALUE_NONE,
				'Creates the room as public room if set'
			)->addOption(
				'readonly',
				null,
				InputOption::VALUE_NONE,
				'Creates the room with read-only access only if set'
			)->addOption(
				'password',
				null,
				InputOption::VALUE_REQUIRED,
				'Protects the room to create with the given password'
			)->addOption(
				'owner',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets the given user as owner of the room to create'
			)->addOption(
				'moderator',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Promotes the given users to moderators'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int {
		$name = $input->getArgument('name');
		$users = $input->getOption('user');
		$groups = $input->getOption('group');
		$circles = $input->getOption('circle');
		$public = $input->getOption('public');
		$readonly = $input->getOption('readonly');
		$password = $input->getOption('password');
		$owner = $input->getOption('owner');
		$moderators = $input->getOption('moderator');

		$name = trim($name);
		if (!$this->validateRoomName($name)) {
			$output->writeln("<error>Invalid room name.</error>");
			return 1;
		}

		$room = $public ? $this->manager->createPublicRoom($name): $this->manager->createGroupRoom($name);

		try {
			$this->setRoomReadOnly($room, $readonly);

			if ($password !== null) {
				$this->setRoomPassword($room, $password);
			}

			$this->addRoomParticipants($room, $users);
			$this->addRoomParticipantsByGroup($room, $groups);
			$this->addRoomParticipantsByCircle($room, $circles);
			$this->addRoomModerators($room, $moderators);

			if ($owner !== null) {
				$this->setRoomOwner($room, $owner);
			}
		} catch (Exception $e) {
			$room->deleteRoom();

			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}

		$output->writeln('<info>Room successfully created.</info>');
		return 0;
	}
}
