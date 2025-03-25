<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	public function __construct(
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected RoomService $roomService,
		protected IURLGenerator $url,
	) {
	}

	#[\Override]
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

	#[\Override]
	public function deleteConversation(string $id): void {
		$room = $this->manager->getRoomByToken($id);
		$this->roomService->deleteRoom($room);
	}
}
