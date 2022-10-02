<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Room;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;

class ReferenceInvalidationListener {
	public static function register(IEventDispatcher $dispatcher): void {
		$listener = static function (RoomEvent $event): void {
			$room = $event->getRoom();
			$referenceManager = Server::get(IReferenceManager::class);

			$referenceManager->invalidateCache($room->getToken());
		};

		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DELETE, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_DESCRIPTION_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_LISTABLE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_LOBBY_STATE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_NAME_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_REMOVE, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PASSWORD_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_MESSAGE_EXPIRATION, $listener);
	}
}
