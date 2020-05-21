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

use InvalidArgumentException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\IGroup;
use OCP\IUser;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;

trait TRoomCommand {
	/**
	 * @param Room   $room
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

		if (!$room->setName($name)) {
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
	 * @param bool $public
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomPublic(Room $room, bool $public): void {
		if ($public === ($room->getType() === Room::PUBLIC_CALL)) {
			return;
		}

		if (!$public && $room->hasPassword()) {
			throw new InvalidArgumentException('Unable to change password protected public room to private room.');
		}

		if (!$room->setType($public ? Room::PUBLIC_CALL : Room::GROUP_CALL)) {
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

		if (!$room->setReadOnly($readOnly ? Room::READ_ONLY : Room::READ_WRITE)) {
			throw new InvalidArgumentException('Unable to change room state.');
		}
	}

	/**
	 * @param Room   $room
	 * @param string $password
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomPassword(Room $room, string $password): void {
		if ($room->hasPassword() ? $room->verifyPassword($password)['result'] : ($password === '')) {
			return;
		}

		if (($password !== '') && ($room->getType() !== Room::PUBLIC_CALL)) {
			throw new InvalidArgumentException('Unable to add password protection to private room.');
		}

		if (!$room->setPassword($password)) {
			throw new InvalidArgumentException('Unable to change room password.');
		}
	}

	/**
	 * @param Room   $room
	 * @param string $userId
	 *
	 * @throws InvalidArgumentException
	 */
	protected function setRoomOwner(Room $room, string $userId): void {
		try {
			$participant = $room->getParticipant($userId);
		} catch (ParticipantNotFoundException $e) {
			throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
		}

		$this->unsetRoomOwner($room);

		$room->setParticipantType($participant, Participant::OWNER);
	}

	/**
	 * @param Room $room
	 *
	 * @throws InvalidArgumentException
	 */
	protected function unsetRoomOwner(Room $room): void {
		foreach ($room->getParticipants() as $participant) {
			if ($participant->getParticipantType() === Participant::OWNER) {
				$room->setParticipantType($participant, Participant::USER);
			}
		}
	}

	/**
	 * @param Room     $room
	 * @param string[] $groupIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomParticipantsByGroup(Room $room, array $groupIds): void {
		if (!$groupIds) {
			return;
		}

		$groupManager = \OC::$server->getGroupManager();

		$users = [];
		foreach ($groupIds as $groupId) {
			$group = $groupManager->get($groupId);
			if ($group === null) {
				throw new InvalidArgumentException(sprintf("Group '%s' not found.", $groupId));
			}

			$groupUsers = array_map(function (IUser $user) {
				return $user->getUID();
			}, $group->getUsers());

			$users = array_merge($users, array_values($groupUsers));
		}

		$this->addRoomParticipants($room, $users);
	}

	/**
	 * @param Room     $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomParticipants(Room $room, array $userIds): void {
		if (!$userIds) {
			return;
		}

		$userManager = \OC::$server->getUserManager();

		$participants = [];
		foreach ($userIds as $userId) {
			$user = $userManager->get($userId);
			if ($user === null) {
				throw new InvalidArgumentException(sprintf("User '%s' not found.", $userId));
			}

			if (isset($participants[$user->getUID()])) {
				// nothing to do, user is going to be a participant already
				continue;
			}

			try {
				$room->getParticipant($user->getUID());

				// nothing to do, user is a participant already
				continue;
			} catch (ParticipantNotFoundException $e) {
				// we expect the user not to be a participant yet
			}

			$participants[$user->getUID()] = [
				'userId' => $user->getUID(),
			];
		}

		\call_user_func_array([$room, 'addUsers'], $participants);
	}

	/**
	 * @param Room     $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function removeRoomParticipants(Room $room, array $userIds): void {
		$userManager = \OC::$server->getUserManager();

		$users = [];
		foreach ($userIds as $userId) {
			try {
				$room->getParticipant($userId);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			$users[] = $userManager->get($userId);
		}

		foreach ($users as $user) {
			$room->removeUser($user, Room::PARTICIPANT_REMOVED);
		}
	}

	/**
	 * @param Room     $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function addRoomModerators(Room $room, array $userIds): void {
		$participants = [];
		foreach ($userIds as $userId) {
			try {
				$participant = $room->getParticipant($userId);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			if ($participant->getParticipantType() !== Participant::OWNER) {
				$participants[] = $participant;
			}
		}

		foreach ($participants as $participant) {
			$room->setParticipantType($participant, Participant::MODERATOR);
		}
	}

	/**
	 * @param Room     $room
	 * @param string[] $userIds
	 *
	 * @throws InvalidArgumentException
	 */
	protected function removeRoomModerators(Room $room, array $userIds): void {
		$participants = [];
		foreach ($userIds as $userId) {
			try {
				$participant = $room->getParticipant($userId);
			} catch (ParticipantNotFoundException $e) {
				throw new InvalidArgumentException(sprintf("User '%s' is no participant.", $userId));
			}

			if ($participant->getParticipantType() === Participant::MODERATOR) {
				$participants[] = $participant;
			}
		}

		foreach ($participants as $participant) {
			$room->setParticipantType($participant, Participant::USER);
		}
	}

	protected function completeTokenValues(CompletionContext $context): array {
		/** @var Manager $roomManager */
		$roomManager = \OC::$server->query(Manager::class);
		return array_map(function (Room $room) {
			return $room->getToken();
		}, $roomManager->searchRoomsByToken($context->getCurrentWord()));
	}

	protected function completeUserValues(CompletionContext $context): array {
		$userManager = \OC::$server->getUserManager();
		return array_map(function (IUser $user) {
			return $user->getUID();
		}, $userManager->search($context->getCurrentWord()));
	}

	protected function completeGroupValues(CompletionContext $context): array {
		$groupManager = \OC::$server->getGroupManager();
		return array_map(function (IGroup $group) {
			return $group->getGID();
		}, $groupManager->search($context->getCurrentWord()));
	}

	protected function completeParticipantValues(CompletionContext $context): array {
		// TODO: implement me!
		return [];
	}
}
