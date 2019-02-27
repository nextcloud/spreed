<?php
declare(strict_types=1);

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Files;

use OCA\Spreed\Exceptions\UnauthorizedException;
use OCA\Spreed\Room;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Custom behaviour for rooms for files.
 *
 * The rooms for files are intended to give the users a way to talk about a
 * specific shared file, for example, when collaboratively editing it. The room
 * is persistent and can be accessed simultaneously by any user with direct
 * access (user, group, circle and room share, but not link share, for example)
 * to that file (or to an ancestor). The room has no owner, although self joined
 * users become persistent participants automatically when they join until they
 * explicitly leave or no longer have access to the file.
 *
 * These rooms are associated to a "file" object, and their custom behaviour is
 * provided by calling the methods of this class as a response to different room
 * events.
 */
class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var Util */
	protected $util;

	public function __construct(EventDispatcherInterface $dispatcher, Util $util) {
		$this->dispatcher = $dispatcher;
		$this->util = $util;
	}

	public function register() {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			try {
				$this->preventUsersWithoutDirectAccessToTheFileFromJoining($room, $event->getArgument('userId'));
			} catch (UnauthorizedException $e) {
				$event->setArgument('cancel', true);
			}
		};
		$this->dispatcher->addListener(Room::class . '::preJoinRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			try {
				$this->preventGuestsFromJoining($room);
			} catch (UnauthorizedException $e) {
				$event->setArgument('cancel', true);
			}
		};
		$this->dispatcher->addListener(Room::class . '::preJoinRoomGuest', $listener);
	}

	/**
	 * Prevents users from joining if they do not have direct access to the
	 * file.
	 *
	 * A user has direct access to a file if she received the file (or an
	 * ancestor) through a user, group, circle or room share (but not through a
	 * link share, for example), or if she is the owner of such a file.
	 *
	 * This method should be called before a user joins a room.
	 *
	 * @param Room $room
	 * @param string $userId
	 * @throws UnauthorizedException
	 */
	public function preventUsersWithoutDirectAccessToTheFileFromJoining(Room $room, string $userId) {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		$share = $this->util->getAnyDirectShareOfFileAccessibleByUser($room->getObjectId(), $userId);
		if (!$share) {
			throw new UnauthorizedException('User does not have direct access to the file');
		}
	}

	/**
	 * Prevents guests from joining the room.
	 *
	 * This method should be called before a guest joins a room.
	 *
	 * @param Room $room
	 * @throws UnauthorizedException
	 */
	public function preventGuestsFromJoining(Room $room) {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		throw new UnauthorizedException('Guests are not allowed in rooms for files');
	}

}
