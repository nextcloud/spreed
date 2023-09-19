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

namespace OCA\Talk\NoteToSelf;

use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @template-implements IEventListener<Event>
 */
class NoteToSelf implements IEventListener {
	public function __construct(
		protected IConfig $config,
		protected IUserManager $userManager,
		protected RoomService $roomService,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected IL10N $l,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeRoomsFetchEvent) {
			$this->createNoteToSelf($event);
		}
	}

	public function createNoteToSelf(BeforeRoomsFetchEvent $event): void {
		$userId = $event->getUserId();
		$noteToSelf = $this->getNoteToSelfForUser($userId);

		if ($noteToSelf === 0) {
			$currentUser = $this->userManager->get($userId);
			if (!$currentUser instanceof IUser) {
				return;
			}

			$room = $this->roomService->createConversation(
				Room::TYPE_NOTE_TO_SELF,
				$this->l->t('Note to self'),
				$currentUser,
				'note_to_self',
				$userId
			);
			$this->config->setUserValue($userId, 'spreed', 'note_to_self', (string) $room->getId());

			$this->roomService->setDescription(
				$room,
				$this->l->t('A place for your private notes, thoughts and ideas'),
			);

			$this->avatarService->setAvatarFromEmoji($room, '📝', '0082c9');

			$participant = $this->participantService->getParticipantByActor(
				$room,
				Attendee::ACTOR_USERS,
				$userId
			);

			$this->participantService->updateFavoriteStatus($participant, true);
		}
	}

	public function getNoteToSelfForUser(string $userId): int {
		return (int) $this->config->getUserValue($userId, 'spreed', 'note_to_self', '0');
	}
}
