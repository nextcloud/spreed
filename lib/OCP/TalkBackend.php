<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\OCP;

use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\IURLGenerator;
use OCP\Talk\IConversation;
use OCP\Talk\IConversationOptions;
use OCP\Talk\ITalkBackend;

class TalkBackend implements ITalkBackend {
	protected Manager $manager;
	protected ParticipantService $participantService;
	protected RoomService $roomService;
	protected IURLGenerator $url;

	public function __construct(Manager $manager,
								ParticipantService $participantService,
								RoomService $roomService,
								IURLGenerator $url) {
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->roomService = $roomService;
		$this->url = $url;
	}

	public function createConversation(string $name, array $moderators, IConversationOptions $options): IConversation {
		$room = $this->manager->createRoom(
			$options->isPublic() ? Room::TYPE_PUBLIC : Room::TYPE_GROUP,
			$name
		);

		if (!empty($moderators)) {
			$users = [];
			foreach ($moderators as $moderator) {
				$users[] = [
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $moderator->getUID(),
					'participantType' => Participant::MODERATOR,
				];
			}

			$this->participantService->addUsers($room, $users);
		}

		return new Conversation($this->url, $room);
	}

	public function deleteConversation(string $id): void {
		$room = $this->manager->getRoomByToken($id);
		$this->roomService->deleteRoom($room);
	}
}
