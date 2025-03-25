<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Room;

use InvalidArgumentException;
use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Room;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Update extends Base {
	use TRoomCommand;

	#[\Override]
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
				'description',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets a new description for the room'
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
				'listable',
				null,
				InputOption::VALUE_REQUIRED,
				'Modifies the room\'s listable scope'
			)->addOption(
				'password',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets a new password for the room; pass an empty value to remove password protection'
			)->addOption(
				'owner',
				null,
				InputOption::VALUE_REQUIRED,
				'Sets the given user as owner of the room; pass an empty value to remove the owner'
			)->addOption(
				'message-expiration',
				null,
				InputOption::VALUE_REQUIRED,
				'Seconds to expire a message after sent. If zero will disable the expire message duration.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$token = $input->getArgument('token');
		$name = $input->getOption('name');
		$description = $input->getOption('description');
		$public = $input->getOption('public');
		$readOnly = $input->getOption('readonly');
		$listable = $input->getOption('listable');
		$password = $input->getOption('password');
		$owner = $input->getOption('owner');
		$messageExpiration = $input->getOption('message-expiration');

		if (!in_array($public, [null, '0', '1'], true)) {
			$output->writeln('<error>Invalid value for option "--public" given.</error>');
			return 1;
		}

		if (!in_array($readOnly, [null, (string)Room::READ_WRITE, (string)Room::READ_ONLY], true)) {
			$output->writeln('<error>Invalid value for option "--readonly" given.</error>');
			return 1;
		}

		if (!in_array($listable, [
			null,
			(string)Room::LISTABLE_NONE,
			(string)Room::LISTABLE_USERS,
			(string)Room::LISTABLE_ALL,
		], true)) {
			$output->writeln('<error>Invalid value for option "--listable" given.</error>');
			return 1;
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$output->writeln('<error>Room not found.</error>');
			return 1;
		}

		if ($room->isFederatedConversation()) {
			$output->writeln('<error>Room is a federated conversation.</error>');
			return 1;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			$output->writeln('<error>Room is no group call.</error>');
			return 1;
		}

		try {
			if ($name !== null) {
				$this->setRoomName($room, $name);
			}

			if ($description !== null) {
				$this->setRoomDescription($room, $description);
			}

			if ($public !== null) {
				$this->setRoomPublic($room, ($public === '1'));
			}

			if ($readOnly !== null) {
				$this->setRoomReadOnly($room, ($readOnly === '1'));
			}

			if ($listable !== null) {
				$this->setRoomListable($room, (int)$listable);
			}

			if ($password !== null) {
				$this->setRoomPassword($room, $password);
			}

			if ($owner !== null) {
				if ($owner !== '') {
					$this->setRoomOwner($room, $owner);
				} else {
					$this->unsetRoomOwner($room);
				}
			}

			if ($messageExpiration !== null) {
				$this->setMessageExpiration($room, (int)$messageExpiration);
			}
		} catch (InvalidArgumentException $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}

		$output->writeln('<info>Room successfully updated.</info>');
		return 0;
	}

	#[\Override]
	public function completeOptionValues($optionName, CompletionContext $context) {
		switch ($optionName) {
			case 'public':
			case 'readonly':
				return [(string)Room::READ_ONLY, (string)Room::READ_WRITE];
			case 'listable':
				return [
					(string)Room::LISTABLE_ALL,
					(string)Room::LISTABLE_USERS,
					(string)Room::LISTABLE_NONE,
				];

			case 'owner':
				return $this->completeParticipantValues($context);
		}

		return parent::completeOptionValues($optionName, $context);
	}

	#[\Override]
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		switch ($argumentName) {
			case 'token':
				return $this->completeTokenValues($context);
		}

		return parent::completeArgumentValues($argumentName, $context);
	}
}
