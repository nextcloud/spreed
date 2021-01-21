<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Collaboration\Resources;

use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\RemoveParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\ResourceException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;

class Listener {
	public static function register(IEventDispatcher $dispatcher): void {
		$listener = static function (RoomEvent $event) {
			$room = $event->getRoom();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);

			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}
			$resourceManager->invalidateAccessCacheForResource($resource);
		};
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DELETE, $listener);

		$listener = static function (AddParticipantsEvent $event) {
			$room = $event->getRoom();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			/** @var IUserManager $userManager */
			$userManager = \OC::$server->getUserManager();
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			$participants = $event->getParticipants();
			foreach ($participants as $participant) {
				$user = null;
				if ($participant['actorType'] === Attendee::ACTOR_USERS) {
					$user = $userManager->get($participant['actorId']);
				}

				$resourceManager->invalidateAccessCacheForResourceByUser($resource, $user);
			}
		};
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, $listener);

		$listener = static function (RemoveUserEvent $event) {
			$room = $event->getRoom();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			$resourceManager->invalidateAccessCacheForResourceByUser($resource, $event->getUser());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, $listener);

		$listener = static function (RemoveParticipantEvent $event) {
			$room = $event->getRoom();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);
			/** @var IUserManager $userManager */
			$userManager = \OC::$server->getUserManager();
			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}

			$participant = $event->getParticipant();
			$user = null;
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$user = $userManager->get($participant->getAttendee()->getActorId());
			}
			$resourceManager->invalidateAccessCacheForResourceByUser($resource, $user);
		};
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_REMOVE, $listener);

		$listener = static function (RoomEvent $event) {
			$room = $event->getRoom();
			/** @var IManager $manager */
			$resourceManager = \OC::$server->query(IManager::class);

			try {
				$resource = $resourceManager->getResourceForUser('room', $room->getToken(), null);
			} catch (ResourceException $e) {
				return;
			}
			$resourceManager->invalidateAccessCacheForResourceByUser($resource, null);
		};
		$dispatcher->addListener(Room::EVENT_AFTER_TYPE_SET, $listener);
		$dispatcher->addListener(GuestManager::EVENT_AFTER_EMAIL_INVITE, $listener);
	}
}
