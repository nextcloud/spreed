<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Settings\UserPreference;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class NoteToSelfService {
	public function __construct(
		private readonly IUserConfig $userConfig,
		private readonly IUserManager $userManager,
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly AvatarService $avatarService,
		private readonly ParticipantService $participantService,
		private readonly ILockingProvider $lockingProvider,
		private readonly IL10N $l,
	) {
	}

	public function ensureNoteToSelfExistsForUser(string $userId): Room {
		$noteToSelfId = $this->getNoteToSelfConversationId($userId);
		if ($noteToSelfId > 0) {
			try {
				$room = $this->manager->getRoomById($noteToSelfId);
				if ($room->getObjectType() === Room::OBJECT_TYPE_NOTE_TO_SELF && $room->getObjectId() === $userId) {
					return $room;
				}
			} catch (RoomNotFoundException) {
				// Fall through and recreate it …
			}
		}
		$currentUser = $this->userManager->get($userId);
		if (!$currentUser instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		return $this->createNoteToSelfConversation($currentUser);
	}

	public function initialCreateNoteToSelfForUser(string $userId): void {
		$noteToSelfId = $this->getNoteToSelfConversationId($userId);
		if ($noteToSelfId > 0) {
			return;
		}

		$currentUser = $this->userManager->get($userId);
		if (!$currentUser instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		try {
			$this->createNoteToSelfConversation($currentUser);
		} catch (RoomNotFoundException) {
			// another request is creating it, avoid race conditions and double creation
		}
	}

	protected function createNoteToSelfConversation(IUser $user): Room {
		$lockNamespace = 'talk' . '/' . UserPreference::NOTE_TO_SELF . '/';
		$lockKey = mb_substr($lockNamespace . $user->getUID(), 0, 64);

		$retries = 3;
		while (true) {
			try {
				$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
				break;
			} catch (LockedException) {
				if (!$retries--) {
					throw new RoomNotFoundException('Failed due to parallel requests');
				}
			}
			usleep(200000);
		}
		try {
			// This is a little trick to bypass local caching
			$values = $this->userConfig->getValuesByUsers(Application::APP_ID, UserPreference::NOTE_TO_SELF, ValueType::INT, [$user->getUID()]);
			// explicit type conversion for psalm to realize the value is typed as int as above and never mixed.
			$currentRoomId = (int)($values[$user->getUID()] ?? 0);

			if ($currentRoomId > 0) {
				// This process didn't win the race for creating the conversation, so fetch the other one
				try {
					$fetchedRoom = $this->manager->getRoomById($currentRoomId);
					if ($fetchedRoom->getObjectId() === $user->getUID() && $fetchedRoom->getObjectType() === Room::OBJECT_TYPE_NOTE_TO_SELF) {
						$this->userConfig->setValueInt($user->getUID(), Application::APP_ID, UserPreference::NOTE_TO_SELF, $currentRoomId);
						return $fetchedRoom;
					}
				} catch (RoomNotFoundException) {
					// fall through to creation below
				}
			}

			$room = $this->roomService->createConversation(
				Room::TYPE_NOTE_TO_SELF,
				$this->l->t('Note to self'),
				$user,
				Room::OBJECT_TYPE_NOTE_TO_SELF,
				$user->getUID(),
			);

			$this->userConfig->setValueInt($user->getUID(), Application::APP_ID, UserPreference::NOTE_TO_SELF, $room->getId());

			$this->roomService->setDescription(
				$room,
				$this->l->t('A place for your private notes, thoughts and ideas'),
			);

			$this->avatarService->setAvatarFromEmoji($room, '📝', '0082c9');

			$participant = $this->participantService->getParticipantByActor(
				$room,
				Attendee::ACTOR_USERS,
				$user->getUID(),
			);

			$this->participantService->updateFavoriteStatus($participant, true);

			return $room;
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	protected function getNoteToSelfConversationId(string $userId): int {
		return $this->userConfig->getValueInt($userId, Application::APP_ID, UserPreference::NOTE_TO_SELF);
	}
}
