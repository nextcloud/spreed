<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class NoteToSelfService {
	public function __construct(
		protected IConfig $config,
		protected IUserManager $userManager,
		protected Manager $manager,
		protected RoomService $roomService,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected IL10N $l,
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

		return $this->createNoteToSelfConversation($currentUser);
	}

	public function initialCreateNoteToSelfForUser(string $userId): void {
		$noteToSelfId = $this->getNoteToSelfConversationId($userId);
		if ($noteToSelfId !== 0) {
			return;
		}

		$currentUser = $this->userManager->get($userId);
		if (!$currentUser instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		$this->createNoteToSelfConversation($currentUser);
	}

	protected function createNoteToSelfConversation(IUser $user): Room {
		$room = $this->roomService->createConversation(
			Room::TYPE_NOTE_TO_SELF,
			$this->l->t('Note to self'),
			$user,
			'note_to_self',
			$user->getUID()
		);
		$this->config->setUserValue($user->getUID(), 'spreed', 'note_to_self', (string) $room->getId());

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
		return (int) $this->config->getUserValue($userId, 'spreed', 'note_to_self', '0');
	}
}
