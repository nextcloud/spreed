<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Listener;

use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

class UserDeletedListener implements IEventListener {

	/** @var Manager */
	private $manager;
	/** @var ParticipantService */
	private $participantService;

	public function __construct(Manager $manager,
								ParticipantService $participantService) {
		$this->manager = $manager;
		$this->participantService = $participantService;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			// Unrelated
			return;
		}

		$user = $event->getUser();

		$rooms = $this->manager->getRoomsForUser($user->getUID());
		foreach ($rooms as $room) {
			if ($this->participantService->getNumberOfUsers($room) === 1) {
				$room->deleteRoom();
			} else {
				$this->participantService->removeUser($room, $user, Room::PARTICIPANT_REMOVED);
			}
		}

		$leftRooms = $this->manager->getLeftOneToOneRoomsForUser($user->getUID());
		foreach ($leftRooms as $room) {
			// We are changing the room type and name so a potential follow up
			// user with the same user-id can not reopen the one-to-one conversation.
			$room->setType(Room::GROUP_CALL, true);
			$room->setName($user->getDisplayName(), '');
		}
	}
}
