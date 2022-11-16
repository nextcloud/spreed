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

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;

class BreakoutRoomService {
	protected Manager $manager;
	protected RoomService $roomService;
	protected ParticipantService $participantService;
	protected IEventDispatcher $dispatcher;
	protected IL10N $l;

	public function __construct(Manager $manager,
								RoomService $roomService,
								ParticipantService $participantService,
								IEventDispatcher $dispatcher,
								IL10N $l) {
		$this->manager = $manager;
		$this->roomService = $roomService;
		$this->participantService = $participantService;
		$this->dispatcher = $dispatcher;
		$this->l = $l;
	}

	/**
	 * @param Room $parent
	 * @param int $mode
	 * @psalm-param 0|1|2|3 $mode
	 * @param int $amount
	 * @param string $attendeeMap
	 * @return Room[]
	 * @throws InvalidArgumentException When the breakout rooms are configured already
	 */
	public function setupBreakoutRooms(Room $parent, int $mode, int $amount, string $attendeeMap): array {
		if ($parent->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('room');
		}

		if ($parent->getType() !== Room::TYPE_GROUP
			&& $parent->getType() !== Room::TYPE_PUBLIC) {
			// Can only do breakout rooms in group and public rooms
			throw new InvalidArgumentException('room');
		}

		if ($parent->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Can not nest breakout rooms
			throw new InvalidArgumentException('room');
		}

		if (!$this->roomService->setBreakoutRoomMode($parent, $mode)) {
			throw new InvalidArgumentException('mode');
		}

		if ($amount < BreakoutRoom::MINIMUM_ROOM_AMOUNT) {
			throw new InvalidArgumentException('amount');
		}

		if ($mode === BreakoutRoom::MODE_MANUAL) {
			try {
				$attendeeMap = json_decode($attendeeMap, true, 2, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				throw new InvalidArgumentException('map');
			}
		}

		$breakoutRooms = $this->createBreakoutRooms($parent, $amount);

		$participants = $this->participantService->getParticipantsForRoom($parent);
		// TODO Removing any non-users here as breakout rooms only support logged in users in version 1
		$participants = array_filter($participants, static fn (Participant $participant) => $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS);

		$moderators = array_filter($participants, static fn (Participant $participant) => $participant->hasModeratorPermissions());
		$this->addModeratorsToBreakoutRooms($breakoutRooms, $moderators);

		$others = array_filter($participants, static fn (Participant $participant) => !$participant->hasModeratorPermissions());
		if ($mode === BreakoutRoom::MODE_AUTOMATIC) {
			// Shuffle the attendees, so they are not always distributed in the same way
			shuffle($others);

			$map = [];
			foreach ($others as $index => $participant) {
				$map[$index % $amount] ??= [];
				$map[$index % $amount][] = $participant;
			}

			$this->addOthersToBreakoutRooms($breakoutRooms, $map);
		} elseif ($mode === BreakoutRoom::MODE_MANUAL) {
			$map = [];
			foreach ($others as $participant) {
				$roomNumber = $attendeeMap[$participant->getAttendee()->getId()] ?? null;
				if ($roomNumber === null) {
					continue;
				}

				$roomNumber = (int) $roomNumber;

				$map[$roomNumber] ??= [];
				$map[$roomNumber][] = $participant;
			}

			$this->addOthersToBreakoutRooms($breakoutRooms, $map);
		}


		return $breakoutRooms;
	}

	/**
	 * @param Room[] $rooms
	 * @param Participant[] $moderators
	 */
	protected function addModeratorsToBreakoutRooms(array $rooms, array $moderators): void {
		$moderatorsToAdd = [];
		foreach ($moderators as $moderator) {
			$attendee = $moderator->getAttendee();

			$moderatorsToAdd[] = [
				'actorType' => $attendee->getActorType(),
				'actorId' => $attendee->getActorId(),
				'displayName' => $attendee->getDisplayName(),
				'participantType' => $attendee->getParticipantType(),
			];
		}

		foreach ($rooms as $room) {
			$this->participantService->addUsers($room, $moderatorsToAdd);
		}
	}

	/**
	 * @param array $rooms
	 * @param Participant[][] $participantsMap
	 */
	protected function addOthersToBreakoutRooms(array $rooms, array $participantsMap): void {
		foreach ($rooms as $roomNumber => $room) {
			$toAdd = [];

			$participants = $participantsMap[$roomNumber] ?? [];
			foreach ($participants as $participant) {
				$attendee = $participant->getAttendee();

				$toAdd[] = [
					'actorType' => $attendee->getActorType(),
					'actorId' => $attendee->getActorId(),
					'displayName' => $attendee->getDisplayName(),
					'participantType' => $attendee->getParticipantType(),
				];
			}

			if (empty($toAdd)) {
				continue;
			}

			$this->participantService->addUsers($room, $toAdd);
		}
	}

	public function createBreakoutRooms(Room $parent, int $amount): array {
		// Safety caution cleaning up potential orphan rooms
		$this->deleteBreakoutRooms($parent);

		$rooms = [];
		for ($i = 1; $i <= $amount; $i++) {
			$rooms[] = $this->roomService->createConversation(
				$parent->getType(),
				str_replace('{number}', (string) $i, $this->l->t('Room {number}')),
				null,
				BreakoutRoom::PARENT_OBJECT_TYPE,
				$parent->getToken()
			);
		}

		return $rooms;
	}

	public function removeBreakoutRooms(Room $parent): void {
		$this->deleteBreakoutRooms($parent);
		$this->roomService->setBreakoutRoomMode($parent, BreakoutRoom::MODE_NOT_CONFIGURED);
	}

	protected function deleteBreakoutRooms(Room $parent): void {
		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());
		foreach ($breakoutRooms as $breakoutRoom) {
			$this->roomService->deleteRoom($breakoutRoom);
		}
	}
}
