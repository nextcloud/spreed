<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomProperty\BreakoutRoomModeException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;

class BreakoutRoomService {
	public function __construct(
		protected Config $config,
		protected Manager $manager,
		protected RoomService $roomService,
		protected ParticipantService $participantService,
		protected ChatManager $chatManager,
		protected INotificationManager $notificationManager,
		protected ITimeFactory $timeFactory,
		protected IEventDispatcher $dispatcher,
		protected IL10N $l,
	) {
	}

	/**
	 * @param string $map
	 * @param int $max
	 * @return array
	 */
	protected function parseAttendeeMap(string $map, int $max): array {
		if ($map === '') {
			return [];
		}

		try {
			$attendeeMap = json_decode($map, true, 2, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			throw new InvalidArgumentException('attendeeMap');
		}

		if (!is_array($attendeeMap)) {
			throw new InvalidArgumentException('attendeeMap');
		}

		if (empty($attendeeMap)) {
			return [];
		}

		try {
			$attendeeMap = array_filter($attendeeMap, static fn (int $roomNumber, int $attendeeId) => true, ARRAY_FILTER_USE_BOTH);
		} catch (\Throwable) {
			throw new InvalidArgumentException('attendeeMap');
		}

		if (empty($attendeeMap)) {
			return [];
		}

		if (max($attendeeMap) >= $max) {
			throw new InvalidArgumentException('attendeeMap');
		}

		if (min($attendeeMap) < 0) {
			throw new InvalidArgumentException('attendeeMap');
		}

		if (min(array_keys($attendeeMap)) <= 0) {
			throw new InvalidArgumentException('attendeeMap');
		}

		return $attendeeMap;
	}

	/**
	 * @param Room $parent
	 * @param 0|1|2|3 $mode
	 * @psalm-param BreakoutRoom::MODE_* $mode
	 * @param int $amount
	 * @param string $attendeeMap
	 * @return Room[]
	 * @throws InvalidArgumentException When the breakout rooms are configured already
	 */
	public function setupBreakoutRooms(Room $parent, int $mode, int $amount, string $attendeeMap): array {
		if (!$this->config->isBreakoutRoomsEnabled()) {
			throw new InvalidArgumentException('config');
		}

		if ($parent->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('room');
		}

		if ($parent->getType() !== Room::TYPE_GROUP) {
			// Can only do breakout rooms in group rooms
			throw new InvalidArgumentException('room');
		}

		if ($parent->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Can not nest breakout rooms
			throw new InvalidArgumentException('room');
		}

		try {
			$this->roomService->setBreakoutRoomMode($parent, $mode);
		} catch (BreakoutRoomModeException) {
			throw new InvalidArgumentException('mode');
		}

		if ($amount < BreakoutRoom::MINIMUM_ROOM_AMOUNT) {
			throw new InvalidArgumentException('amount');
		}

		if ($amount > BreakoutRoom::MAXIMUM_ROOM_AMOUNT) {
			throw new InvalidArgumentException('amount');
		}

		if ($mode === BreakoutRoom::MODE_MANUAL) {
			$cleanedMap = $this->parseAttendeeMap($attendeeMap, $amount);
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
				if (!isset($cleanedMap[$participant->getAttendee()->getId()])) {
					continue;
				}

				$roomNumber = (int)$cleanedMap[$participant->getAttendee()->getId()];

				$map[$roomNumber] ??= [];
				$map[$roomNumber][] = $participant;
			}

			$this->addOthersToBreakoutRooms($breakoutRooms, $map);
		}


		return $breakoutRooms;
	}

	/**
	 * @param Room $parent
	 * @param string $attendeeMap
	 * @return Room[]
	 * @throws InvalidArgumentException When the map was invalid, breakout rooms are disabled or not configured for this conversation
	 */
	public function applyAttendeeMap(Room $parent, string $attendeeMap): array {
		if (!$this->config->isBreakoutRoomsEnabled()) {
			throw new InvalidArgumentException('config');
		}

		if ($parent->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('mode');
		}

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());
		$amount = count($breakoutRooms);
		usort($breakoutRooms, static function (Room $roomA, Room $roomB) {
			return $roomA->getId() - $roomB->getId();
		});

		$cleanedMap = $this->parseAttendeeMap($attendeeMap, $amount);
		$attendeeIds = array_keys($cleanedMap);

		$participants = $this->participantService->getParticipantsForRoom($parent);
		$participants = array_filter($participants, static fn (Participant $participant) => in_array($participant->getAttendee()->getId(), $attendeeIds, true));
		// TODO Removing any non-users here as breakout rooms only support logged in users in version 1
		$participants = array_filter($participants, static fn (Participant $participant) => $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS);

		$userIds = array_map(static fn (Participant $participant) => $participant->getAttendee()->getActorId(), $participants);

		$removals = [];
		foreach ($breakoutRooms as $breakoutRoom) {
			$breakoutRoomParticipants = $this->participantService->getParticipantsForRoom($breakoutRoom);

			foreach ($breakoutRoomParticipants as $participant) {
				$attendee = $participant->getAttendee();
				if ($attendee->getActorType() === Attendee::ACTOR_USERS && in_array($attendee->getActorId(), $userIds, true)) {
					if ($participant->hasModeratorPermissions()) {
						// Can not remove moderators with this method
						throw new InvalidArgumentException('moderator');
					}

					$removals[] = [
						'room' => $breakoutRoom,
						'participant' => $participant,
					];
				}
			}
		}

		foreach ($removals as $removal) {
			$this->participantService->removeAttendee($removal['room'], $removal['participant'], AAttendeeRemovedEvent::REASON_REMOVED);
		}

		$map = [];
		foreach ($participants as $participant) {
			if (!isset($cleanedMap[$participant->getAttendee()->getId()])) {
				continue;
			}

			$roomNumber = (int)$cleanedMap[$participant->getAttendee()->getId()];

			$map[$roomNumber] ??= [];
			$map[$roomNumber][] = $participant;
		}

		$this->addOthersToBreakoutRooms($breakoutRooms, $map);

		return $breakoutRooms;
	}

	/**
	 * @param Room[] $rooms
	 * @param Participant[] $moderators
	 */
	public function addModeratorsToBreakoutRooms(array $rooms, array $moderators): void {
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

	protected function createBreakoutRooms(Room $parent, int $amount): array {
		// Safety caution cleaning up potential orphan rooms
		$this->deleteBreakoutRooms($parent);

		// TRANSLATORS Label for the breakout rooms, this is not a plural! The result will be "Room 1", "Room 2", "Room 3", ...
		$label = $this->l->t('Room {number}');

		$rooms = [];
		for ($i = 1; $i <= $amount; $i++) {
			$breakoutRoom = $this->roomService->createConversation(
				$parent->getType(),
				str_replace('{number}', (string)$i, $label),
				null,
				BreakoutRoom::PARENT_OBJECT_TYPE,
				$parent->getToken()
			);

			$this->roomService->setLobby($breakoutRoom, Webinary::LOBBY_NON_MODERATORS, null, false, false);

			$rooms[] = $breakoutRoom;
		}

		return $rooms;
	}

	public function removeBreakoutRooms(Room $parent): void {
		$this->deleteBreakoutRooms($parent);
		$this->roomService->setBreakoutRoomMode($parent, BreakoutRoom::MODE_NOT_CONFIGURED);
		$this->roomService->setBreakoutRoomStatus($parent, BreakoutRoom::STATUS_STOPPED);
	}

	protected function deleteBreakoutRooms(Room $parent): void {
		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());
		foreach ($breakoutRooms as $breakoutRoom) {
			$this->roomService->deleteRoom($breakoutRoom);
		}
	}

	/**
	 * @param Room $parent
	 * @param Participant $participant
	 * @param string $message
	 * @return Room[]
	 */
	public function broadcastChatMessage(Room $parent, Participant $participant, string $message): array {
		if ($parent->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('mode');
		}

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());
		$attendeeType = $participant->getAttendee()->getActorType();
		$attendeeId = $participant->getAttendee()->getActorId();
		$creationDateTime = new \DateTime();

		$shouldFlush = $this->notificationManager->defer();
		try {
			foreach ($breakoutRooms as $breakoutRoom) {
				$breakoutParticipant = $this->participantService->getParticipantByActor($breakoutRoom, $attendeeType, $attendeeId);
				$comment = $this->chatManager->sendMessage($breakoutRoom, $breakoutParticipant, $attendeeType, $attendeeId, $message, $creationDateTime, rateLimitGuestMentions: false);
				$breakoutRoom->setLastMessage($comment);
			}
		} finally {
			if ($shouldFlush) {
				$this->notificationManager->flush();
			}
		}

		return $breakoutRooms;
	}

	public function requestAssistance(Room $breakoutRoom): void {
		$this->setAssistanceRequest($breakoutRoom, BreakoutRoom::STATUS_ASSISTANCE_REQUESTED);
	}

	public function resetRequestForAssistance(Room $breakoutRoom): void {
		$this->setAssistanceRequest($breakoutRoom, BreakoutRoom::STATUS_ASSISTANCE_RESET);
	}

	protected function setAssistanceRequest(Room $breakoutRoom, int $status): void {
		if ($breakoutRoom->getObjectType() !== BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new InvalidArgumentException('room');
		}

		if ($breakoutRoom->getLobbyState() !== Webinary::LOBBY_NONE) {
			throw new InvalidArgumentException('room');
		}

		if (!in_array($status, [
			BreakoutRoom::STATUS_ASSISTANCE_RESET,
			BreakoutRoom::STATUS_ASSISTANCE_REQUESTED,
		], true)) {
			throw new InvalidArgumentException('status');
		}

		$this->roomService->setBreakoutRoomStatus($breakoutRoom, $status);
		$this->roomService->setLastActivity($breakoutRoom, $this->timeFactory->getDateTime());
	}

	/**
	 * @param Room $parent
	 * @return Room[]
	 */
	public function startBreakoutRooms(Room $parent): array {
		if ($parent->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('mode');
		}

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken(), true);
		foreach ($breakoutRooms as $breakoutRoom) {
			$this->roomService->setLobby($breakoutRoom, Webinary::LOBBY_NONE, null);
		}

		$this->roomService->setBreakoutRoomStatus($parent, BreakoutRoom::STATUS_STARTED);

		return $breakoutRooms;
	}

	/**
	 * @param Room $parent
	 * @return Room[]
	 */
	public function stopBreakoutRooms(Room $parent): array {
		if ($parent->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('mode');
		}

		$this->roomService->setBreakoutRoomStatus($parent, BreakoutRoom::STATUS_STOPPED);

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken(), true);
		foreach ($breakoutRooms as $breakoutRoom) {
			$this->roomService->setLobby($breakoutRoom, Webinary::LOBBY_NON_MODERATORS, null);

			if ($breakoutRoom->getBreakoutRoomStatus() === BreakoutRoom::STATUS_ASSISTANCE_REQUESTED) {
				$this->roomService->setBreakoutRoomStatus($breakoutRoom, BreakoutRoom::STATUS_ASSISTANCE_RESET);
			}
		}

		return $breakoutRooms;
	}

	public function switchBreakoutRoom(Room $parent, Participant $participant, string $targetToken): Room {
		if ($parent->getBreakoutRoomMode() !== BreakoutRoom::MODE_FREE) {
			throw new InvalidArgumentException('mode');
		}

		if ($parent->getBreakoutRoomStatus() !== BreakoutRoom::STATUS_STARTED) {
			throw new InvalidArgumentException('status');
		}

		if ($participant->hasModeratorPermissions()) {
			// Moderators don't switch, they are part of all breakout rooms
			throw new InvalidArgumentException('moderator');
		}

		$attendee = $participant->getAttendee();

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());

		$target = null;
		foreach ($breakoutRooms as $breakoutRoom) {
			if ($targetToken === $breakoutRoom->getToken()) {
				$target = $breakoutRoom;
				break;
			}
		}

		if ($target === null) {
			throw new InvalidArgumentException('target');
		}

		foreach ($breakoutRooms as $breakoutRoom) {
			try {
				$removeParticipant = $this->participantService->getParticipantByActor(
					$breakoutRoom,
					$attendee->getActorType(),
					$attendee->getActorId()
				);

				if ($targetToken !== $breakoutRoom->getToken()) {
					// Remove from all other breakout rooms
					$this->participantService->removeAttendee(
						$breakoutRoom,
						$removeParticipant,
						AAttendeeRemovedEvent::REASON_LEFT
					);
				}
			} catch (ParticipantNotFoundException $e) {
				if ($targetToken === $breakoutRoom->getToken()) {
					// Join the target breakout room
					$this->participantService->addUsers(
						$breakoutRoom,
						[
							[
								'actorType' => $attendee->getActorType(),
								'actorId' => $attendee->getActorId(),
								'displayName' => $attendee->getDisplayName(),
								'participantType' => $attendee->getParticipantType(),
							]
						]
					);
				}
			}
		}

		return $target;
	}

	/**
	 * @param Room $parent
	 * @param Participant $participant
	 * @return Room[]
	 */
	public function getBreakoutRooms(Room $parent, Participant $participant): array {
		if ($parent->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new InvalidArgumentException('mode');
		}

		if (!$participant->hasModeratorPermissions() && $parent->getBreakoutRoomStatus() !== BreakoutRoom::STATUS_STARTED) {
			throw new InvalidArgumentException('status');
		}

		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken(), true);

		$returnAll = $participant->hasModeratorPermissions() || $parent->getBreakoutRoomMode() === BreakoutRoom::MODE_FREE;
		if (!$returnAll) {
			$rooms = [];
			foreach ($breakoutRooms as $breakoutRoom) {
				try {
					$this->participantService->getParticipantByActor(
						$breakoutRoom,
						$participant->getAttendee()->getActorType(),
						$participant->getAttendee()->getActorId()
					);
					$rooms[] = $breakoutRoom;
				} catch (ParticipantNotFoundException $e) {
					// Skip this room
				}
			}
			return $rooms;
		}
		return $breakoutRooms;
	}

	/**
	 * @param Room $parent
	 * @param string $actorType
	 * @param string $actorId
	 * @param bool $throwOnModerator
	 * @return void
	 * @throws InvalidArgumentException When being used for a moderator
	 */
	public function removeAttendeeFromBreakoutRoom(Room $parent, string $actorType, string $actorId, bool $throwOnModerator = true): void {
		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $parent->getToken());

		foreach ($breakoutRooms as $breakoutRoom) {
			try {
				$participant = $this->participantService->getParticipantByActor(
					$breakoutRoom,
					$actorType,
					$actorId
				);

				if ($throwOnModerator && $participant->hasModeratorPermissions()) {
					throw new InvalidArgumentException('moderator');
				}

				$this->participantService->removeAttendee($breakoutRoom, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
			} catch (ParticipantNotFoundException $e) {
				// Skip this room
			}
		}
	}
}
