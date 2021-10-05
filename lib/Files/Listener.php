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

namespace OCA\Talk\Files;

use OCA\Talk\Events\JoinRoomGuestEvent;
use OCA\Talk\Events\JoinRoomUserEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;

/**
 * Custom behaviour for rooms for files.
 *
 * The rooms for files are intended to give the users a way to talk about a
 * specific shared file, for example, when collaboratively editing it. The room
 * is persistent and can be accessed simultaneously by any user or guest if the
 * file is publicly shared (link share, for example), or by any user with direct
 * access (user, group, circle and room share, but not link share, for example)
 * to that file (or to an ancestor). The room has no owner, although self joined
 * users with direct access become persistent participants automatically when
 * they join until they explicitly leave or no longer have access to the file.
 *
 * These rooms are associated to a "file" object, and their custom behaviour is
 * provided by calling the methods of this class as a response to different room
 * events.
 */
class Listener {

	/** @var Util */
	protected $util;
	/** @var ParticipantService */
	protected $participantService;
	/** @var IUserManager */
	protected $userManager;
	/** @var TalkSession */
	protected $talkSession;

	public function __construct(Util $util,
								ParticipantService $participantService,
								IUserManager $userManager,
								TalkSession $talkSession) {
		$this->util = $util;
		$this->participantService = $participantService;
		$this->userManager = $userManager;
		$this->talkSession = $talkSession;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$listener = static function (JoinRoomUserEvent $event): void {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			try {
				$listener->preventUsersWithoutAccessToTheFileFromJoining($event->getRoom(), $event->getUser()->getUID());
				$listener->addUserAsPersistentParticipant($event->getRoom(), $event->getUser()->getUID());
			} catch (UnauthorizedException $e) {
				$event->setCancelJoin(true);
			}
		};
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_CONNECT, $listener);

		$listener = static function (JoinRoomGuestEvent $event): void {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			try {
				$listener->preventGuestsFromJoiningIfNotPubliclyAccessible($event->getRoom());
			} catch (UnauthorizedException $e) {
				$event->setCancelJoin(true);
			}
		};
		$dispatcher->addListener(Room::EVENT_BEFORE_GUEST_CONNECT, $listener);
	}

	/**
	 * Prevents users from joining if they do not have access to the file.
	 *
	 * A user has access to the file if the file is publicly accessible (through
	 * a link share, for example) or if the user has direct access to it.
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
	public function preventUsersWithoutAccessToTheFileFromJoining(Room $room, string $userId): void {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		// If a guest can access the file then any user can too.
		$shareToken = $this->talkSession->getFileShareTokenForRoom($room->getToken());
		if ($shareToken && $this->util->canGuestAccessFile($shareToken)) {
			return;
		}

		$node = $this->util->getAnyNodeOfFileAccessibleByUser($room->getObjectId(), $userId);
		if ($node === null) {
			throw new UnauthorizedException('User does not have access to the file');
		}
	}

	/**
	 * Add user as a persistent participant of a file room.
	 *
	 * Only users with direct access to the file are added as persistent
	 * participants of the room.
	 *
	 * This method should be called before a user joins a room, but only if the
	 * user should be able to join the room.
	 *
	 * @param Room $room
	 * @param string $userId
	 */
	public function addUserAsPersistentParticipant(Room $room, string $userId): void {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		if ($this->util->getAnyNodeOfFileAccessibleByUser($room->getObjectId(), $userId) === null) {
			return;
		}

		try {
			$room->getParticipant($userId, false);
		} catch (ParticipantNotFoundException $e) {
			$user = $this->userManager->get($userId);

			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'displayName' => $user ? $user->getDisplayName() : $userId,
			]]);
		}
	}

	/**
	 * Prevents guests from joining the room if it is not publicly accessible.
	 *
	 * This method should be called before a guest joins a room.
	 *
	 * @param Room $room
	 * @throws UnauthorizedException
	 */
	protected function preventGuestsFromJoiningIfNotPubliclyAccessible(Room $room): void {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		$shareToken = $this->talkSession->getFileShareTokenForRoom($room->getToken());
		if ($shareToken && $this->util->canGuestAccessFile($shareToken)) {
			return;
		}

		throw new UnauthorizedException('Guests are not allowed in this room');
	}
}
