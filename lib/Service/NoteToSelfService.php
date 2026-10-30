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
use OCP\Config\IUserConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\PreConditionNotMetException;
use OCP\Security\ISecureRandom;

class NoteToSelfService {
	public function __construct(
		private readonly IUserConfig $userConfig,
		private readonly IUserManager $userManager,
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly AvatarService $avatarService,
		private readonly ParticipantService $participantService,
		private readonly ISecureRandom $secureRandom,
		private readonly IL10N $l,
	) {
	}

	public function ensureNoteToSelfExistsForUser(string $userId): Room {
		$noteToSelfId = $this->getNoteToSelfConversationId($userId);

		if ($noteToSelfId !== 0) {
			try {
				return $this->manager->getRoomById($noteToSelfId);
			} catch (RoomNotFoundException) {
				// Fall through and recreate it …
			}
		}

		$currentUser = $this->userManager->get($userId);
		if (!$currentUser instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		return $this->createNoteToSelfConversation($currentUser, $noteToSelfId);
	}

	public function initialCreateNoteToSelfForUser(string $userId): void {
		$noteToSelfId = $this->getNoteToSelfConversationId($userId);
		if ($noteToSelfId !== 0) {
			return;
		}

		// Prefixing with zz, so that casting to int does not give a random roomId for other requests
		$randomLock = 'zz' . $this->secureRandom->generate(3);
		$this->userConfig->setValueString($userId, Application::APP_ID, 'note_to_self', $randomLock);

		$currentUser = $this->userManager->get($userId);
		if (!$currentUser instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		$this->createNoteToSelfConversation($currentUser, $randomLock);
	}

	protected function createNoteToSelfConversation(IUser $user, string|int $previousValue): Room {
		$room = $this->roomService->createConversation(
			Room::TYPE_NOTE_TO_SELF,
			$this->l->t('Note to self'),
			$user,
			Room::OBJECT_TYPE_NOTE_TO_SELF,
			$user->getUID()
		);

		try {
			$this->userConfig->setValueString($user->getUID(), Application::APP_ID, 'note_to_self', (string)$room->getId());
		} catch (PreConditionNotMetException) {
			// This process didn't win the race for creating the conversation, so fetch the other one
			$this->roomService->deleteRoom($room);

			// This is a little trick to bypass local caching
			$values = $this->userConfig->getValuesByUsers(Application::APP_ID, 'note_to_self', userIds: [$user->getUID()]);
			if (isset($values[$user->getUID()])) {
				return $this->manager->getRoomById($values[$user->getUID()]['note_to_self']);
			}

			// Failed to read parallel note-to-self creation
			throw new RoomNotFoundException('Failed due to parallel requests');
		}

		$this->roomService->setDescription(
			$room,
			$this->l->t('A place for your private notes, thoughts and ideas'),
		);

		$this->avatarService->setAvatarFromEmoji($room, '📝', '0082c9');

		$participant = $this->participantService->getParticipantByActor(
			$room,
			Attendee::ACTOR_USERS,
			$user->getUID()
		);

		$this->participantService->updateFavoriteStatus($participant, true);

		return $room;
	}

	protected function getNoteToSelfConversationId(string $userId): int {
		return $this->userConfig->getValueInt($userId, Application::APP_ID, 'note_to_self', 0);
	}
}
