<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Room;

use InvalidArgumentException;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\RoomProperty\DescriptionException;
use OCA\Talk\Exceptions\RoomProperty\ListableException;
use OCA\Talk\Exceptions\RoomProperty\MessageExpirationException;
use OCA\Talk\Exceptions\RoomProperty\NameException;
use OCA\Talk\Exceptions\RoomProperty\PasswordException;
use OCA\Talk\Exceptions\RoomProperty\ReadOnlyException;
use OCA\Talk\Exceptions\RoomProperty\TypeException;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

trait TRoomCommand {

	public function __construct(
		protected Manager $manager,
		protected RoomService $roomService,
		protected ParticipantService $participantService,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
	) {
		parent::__construct();
	}

	/**
	 * @param Room $room
	 * @param string $name
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomName(Room $room, string $name): void {
		$name = trim($name);
		if ($name === $room->getName()) {
			return;
		}

		if (!$this->validateRoomName($name)) {
			throw new InvalidArgumentException('Invalid room name.');
		}

		try {
			$this->roomService->setName($room, $name);
		} catch (NameException) {
			throw new InvalidArgumentException('Unable to change room name.');
		}
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 *
	 * @throws InvalidArgumentException
	 */
	protected function validateRoomName(string $name): bool {
		$name = trim($name);
		return (($name !== '') && !isset($name[255]));
	}

	/**
	 * @param Room $room
	 * @param string $description
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomDescription(Room $room, string $description): void {
		try {
			$this->roomService->setDescription($room, $description);
		} catch (DescriptionException $e) {
			throw new InvalidArgumentException('Invalid room description.');
		}
	}

	/**
	 * @param Room $room
	 * @param bool $public
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomPublic(Room $room, bool $public): void {
		if ($public === ($room->getType() === Room::TYPE_PUBLIC)) {
			return;
		}

		try {
			$this->roomService->setType($room, $public ? Room::TYPE_PUBLIC : Room::TYPE_GROUP);
		} catch (TypeException) {
			throw new InvalidArgumentException('Unable to change room type.');
		}
	}

	/**
	 * @param Room $room
	 * @param bool $readOnly
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomReadOnly(Room $room, bool $readOnly): void {
		if ($readOnly === ($room->getReadOnly() === Room::READ_ONLY)) {
			return;
		}

		try {
			$this->roomService->setReadOnly($room, $readOnly ? Room::READ_ONLY : Room::READ_WRITE);
		} catch (ReadOnlyException) {
			throw new InvalidArgumentException('Unable to change room state.');
		}
	}

	/**
	 * @param Room $room
	 * @param int $listable
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomListable(Room $room, int $listable): void {
		if ($room->getListable() === $listable) {
			return;
		}

		try {
			$this->roomService->setListable($room, $listable);
		} catch (ListableException) {
			throw new InvalidArgumentException('Unable to change room state.');
		}
	}

	/**
	 * @param Room $room
	 * @param string $password
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomPassword(Room $room, string $password): void {
		if ($room->hasPassword() ? $this->roomService->verifyPassword($room, $password)['result'] : ($password === '')) {
			return;
		}

		if (($password !== '') && ($room->getType() !== Room::TYPE_PUBLIC)) {
			throw new InvalidArgumentException('Unable to add password protection to private room.');
		}

		try {
			$this->roomService->setPassword($room, $password);
		} catch (PasswordException $e) {
			if ($e->getReason() === PasswordException::REASON_VALUE) {
				throw new InvalidArgumentException($e->getHint());
			}
			throw new InvalidArgumentException('Unable to change room password.');
		}
	}

	/**
	 * @param Room $room
	 * @param string $userId
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomOwner(Room $room, string $userId): void {
		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
		}

		if ($userId === MatterbridgeManager::BRIDGE_BOT_USERID) {
			throw new InvalidArgumentException('Can not promote the bridge-bot user.');
		}

		$this->unsetRoomOwner($room);

		$this->participantService->updateParticipantType($room, $participant, Participant::OWNER);
	}

	/**
	 * @param Room $room
	 *
	 * @throws InvalidArgumentException
	 */
	protected function unsetRoomOwner(Room $room): void {
		$participants = $this->participantService->getParticipantsForRoom($room);
		foreach ($participants as $participant) {
			if ($participant->getAttendee()->getParticipantType() === Participant::OWNER) {
				$this->participantService->updateParticipantType($room, $participant, Participant::USER);
			}
		}
	}

	/**
	 * @param Room $room
	 * @param string[] $groupIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomParticipantsByGroup(Room $room, array $groupIds): void {
		if (!$groupIds) {
			return;
		}

		foreach ($groupIds as $groupId) {
			$group = $this->groupManager->get($groupId);
			if ($group === null) {
				throw new InvalidArgumentException(sprintf("Group '%s' not found.", $groupId));
			}

			$this->participantService->addGroup($room, $group);
		}
	}

	/**
	 * @param Room $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomParticipants(Room $room, array $userIds): void {
		if (!$userIds) {
			return;
		}

		/** @var array<string, array{actorType: string, actorId: string, displayName: string}> $participants */
		$participants = [];
		foreach ($userIds as $userId) {
			if ($userId === MatterbridgeManager::BRIDGE_BOT_USERID) {
				throw new InvalidArgumentException('Can not add the bridge-bot user.');
			}

			$user = $this->userManager->get($userId);
			if ($user === null) {
				throw new InvalidArgumentException(sprintf("User '%s' not found.", $userId));
			}

			if (isset($participants[$user->getUID()])) {
				// nothing to do, user is going to be a participant already
				continue;
			}

			try {
				$this->participantService->getParticipant($room, $user->getUID(), false);

				// nothing to do, user is a participant already
				continue;
			} catch (ParticipantNotFoundException $e) {
				// we expect the user not to be a participant yet
			}

			$participants[$user->getUID()] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
		}

		$this->participantService->addUsers($room, $participants);
	}

	/**
	 * @param Room $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function removeRoomParticipants(Room $room, array $userIds): void {
		$users = [];
		foreach ($userIds as $userId) {
			try {
				$this->participantService->getParticipant($room, $userId, false);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			$users[] = $this->userManager->get($userId);
		}

		foreach ($users as $user) {
			$this->participantService->removeUser($room, $user, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}

	/**
	 * @param Room $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomModerators(Room $room, array $userIds): void {
		$participants = [];
		foreach ($userIds as $userId) {
			if ($userId === MatterbridgeManager::BRIDGE_BOT_USERID) {
				throw new InvalidArgumentException('Can not promote the bridge-bot user.');
			}

			try {
				$participant = $this->participantService->getParticipant($room, $userId, false);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			if ($participant->getAttendee()->getParticipantType() !== Participant::OWNER) {
				$participants[] = $participant;
			}
		}

		foreach ($participants as $participant) {
			$this->participantService->updateParticipantType($room, $participant, Participant::MODERATOR);
		}
	}

	/**
	 * @param Room $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function removeRoomModerators(Room $room, array $userIds): void {
		$participants = [];
		foreach ($userIds as $userId) {
			try {
				$participant = $this->participantService->getParticipant($room, $userId, false);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			if ($participant->getAttendee()->getParticipantType() === Participant::MODERATOR) {
				$participants[] = $participant;
			}
		}

		foreach ($participants as $participant) {
			$this->participantService->updateParticipantType($room, $participant, Participant::USER);
		}
	}

	protected function completeTokenValues(CompletionContext $context): array {
		return array_map(function (Room $room) {
			return $room->getToken();
		}, $this->manager->searchRoomsByToken($context->getCurrentWord()));
	}

	protected function completeUserValues(CompletionContext $context): array {
		return array_map(function (IUser $user) {
			if ($user->getUID() === MatterbridgeManager::BRIDGE_BOT_USERID) {
				return '';
			}
			return $user->getUID();
		}, $this->userManager->search($context->getCurrentWord()));
	}

	protected function completeGroupValues(CompletionContext $context): array {
		return array_map(function (IGroup $group) {
			return $group->getGID();
		}, $this->groupManager->search($context->getCurrentWord()));
	}

	protected function completeParticipantValues(CompletionContext $context): array {
		$definition = new InputDefinition();

		if ($this->getApplication() !== null) {
			$definition->addArguments($this->getApplication()->getDefinition()->getArguments());
			$definition->addOptions($this->getApplication()->getDefinition()->getOptions());
		}

		$definition->addArguments($this->getDefinition()->getArguments());
		$definition->addOptions($this->getDefinition()->getOptions());

		$input = new ArgvInput($context->getWords(), $definition);
		if ($input->hasArgument('token')) {
			$token = $input->getArgument('token');
		} elseif ($input->hasOption('token')) {
			$token = $input->getOption('token');
		} else {
			return [];
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			return [];
		}

		return array_filter($this->participantService->getParticipantUserIds($room), static function ($userId) use ($context) {
			return stripos($userId, $context->getCurrentWord()) !== false;
		});
	}

	protected function setMessageExpiration(Room $room, int $seconds): void {
		try {
			$this->roomService->setMessageExpiration($room, $seconds);
		} catch (MessageExpirationException) {
			throw new InvalidArgumentException('Unable to change message expiration.');
		}
	}
}
