<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCP\EventDispatcher\Event;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroup;
use OCP\IUser;

class GroupMembershipListener extends AMembershipListener {
	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof UserAddedEvent) {
			$this->addNewMemberToRooms($event->getGroup(), $event->getUser());
		}
		if ($event instanceof UserRemovedEvent) {
			$this->removeFormerMemberFromRooms($event->getGroup(), $event->getUser());
		}
	}

	protected function addNewMemberToRooms(IGroup $group, IUser $user): void {
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_GROUPS, $group->getGID());

		if (empty($rooms)) {
			return;
		}

		$bannedRoomIds = $this->banService->getBannedRoomsForUserId($user->getUID());
		foreach ($rooms as $room) {
			if (isset($bannedRoomIds[$room->getId()])) {
				$this->logger->warning('User ' . $user->getUID() . ' is banned from conversation ' . $room->getToken() . ' and was skipped while adding them to group ' . $group->getDisplayName());
				continue;
			}

			try {
				$participant = $this->participantService->getParticipant($room, $user->getUID());
				if ($participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
					$this->participantService->updateParticipantType($room, $participant, Participant::USER);
				}
			} catch (ParticipantNotFoundException $e) {
				$this->participantService->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
				]]);
			}
		}
	}

	protected function removeFormerMemberFromRooms(IGroup $group, IUser $user): void {
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_GROUPS, $group->getGID());
		if (empty($rooms)) {
			return;
		}

		$this->removeFromRoomsUnlessStillLinked($rooms, $user);
	}
}
