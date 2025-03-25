<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Room;

use InvalidArgumentException;
use OC\Core\Command\Base;
use OCA\Talk\Room;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	use TRoomCommand;

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:room:create')
			->setDescription('Create a new room')
			->addArgument(
				'name',
				InputArgument::REQUIRED,
				'The name of the room to create'
			)->addOption(
				'description',
				null,
				InputOption::VALUE_REQUIRED,
				'The description of the room to create'
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
				'listable',
				null,
				InputOption::VALUE_REQUIRED,
				'Creates the room with the given listable scope'
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
			)->addOption(
				'message-expiration',
				null,
				InputOption::VALUE_REQUIRED,
				'Seconds to expire a message after sent. If zero will disable the expire message duration.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$description = $input->getOption('description');
		$users = $input->getOption('user');
		$groups = $input->getOption('group');
		$public = $input->getOption('public');
		$readonly = $input->getOption('readonly');
		$listable = $input->getOption('listable');
		$password = $input->getOption('password');
		$owner = $input->getOption('owner');
		$moderators = $input->getOption('moderator');
		$messageExpiration = $input->getOption('message-expiration');

		if (!in_array($listable, [
			null,
			(string)Room::LISTABLE_NONE,
			(string)Room::LISTABLE_USERS,
			(string)Room::LISTABLE_ALL,
		], true)) {
			$output->writeln('<error>Invalid value for option "--listable" given.</error>');
			return 1;
		}

		$roomType = $public ? Room::TYPE_PUBLIC : Room::TYPE_GROUP;
		try {
			$room = $this->roomService->createConversation($roomType, $name);
		} catch (InvalidArgumentException $e) {
			if ($e->getMessage() === 'name') {
				$output->writeln('<error>Invalid room name.</error>');
				return 1;
			}
			throw $e;
		}

		try {
			if ($description !== null) {
				$this->setRoomDescription($room, $description);
			}

			$this->setRoomReadOnly($room, $readonly);
			$this->setRoomListable($room, (int)$listable);

			if ($password !== null) {
				$this->setRoomPassword($room, $password);
			}

			$this->addRoomParticipants($room, $users);
			$this->addRoomParticipantsByGroup($room, $groups);
			$this->addRoomModerators($room, $moderators);

			if ($owner !== null) {
				$this->setRoomOwner($room, $owner);
			}

			if ($messageExpiration !== null) {
				$this->setMessageExpiration($room, (int)$messageExpiration);
			}
		} catch (InvalidArgumentException $e) {
			$this->roomService->deleteRoom($room);

			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}
		$output->writeln('Room token: ' . $room->getToken());

		$output->writeln('<info>Room successfully created.</info>');
		return 0;
	}

	#[\Override]
	public function completeOptionValues($optionName, CompletionContext $context) {
		switch ($optionName) {
			case 'user':
				return $this->completeUserValues($context);

			case 'group':
				return $this->completeGroupValues($context);

			case 'owner':
			case 'moderator':
				return $this->completeParticipantValues($context);
			case 'readonly':
				return [(string)Room::READ_ONLY, (string)Room::READ_WRITE];
			case 'listable':
				return [
					(string)Room::LISTABLE_ALL,
					(string)Room::LISTABLE_USERS,
					(string)Room::LISTABLE_NONE,
				];
		}

		return parent::completeOptionValues($optionName, $context);
	}
}
