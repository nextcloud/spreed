<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Files;

use OCA\Talk\Events\BeforeGuestJoinedRoomEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
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
 *
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected Util $util,
		protected ParticipantService $participantService,
		protected IUserManager $userManager,
		protected TalkSession $talkSession,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (get_class($event)) {
			BeforeUserJoinedRoomEvent::class => $this->beforeUserJoinedRoomEvent($event),
			BeforeGuestJoinedRoomEvent::class => $this->beforeGuestJoinedRoomEvent($event),
		};
	}

	protected function beforeUserJoinedRoomEvent(BeforeUserJoinedRoomEvent $event): void {
		try {
			$this->preventUsersWithoutAccessToTheFileFromJoining($event->getRoom(), $event->getUser()->getUID());
			$this->addUserAsPersistentParticipant($event->getRoom(), $event->getUser()->getUID());
		} catch (UnauthorizedException) {
			$event->setCancelJoin(true);
		}
	}

	protected function beforeGuestJoinedRoomEvent(BeforeGuestJoinedRoomEvent $event): void {
		try {
			$this->preventGuestsFromJoiningIfNotPubliclyAccessible($event->getRoom());
		} catch (UnauthorizedException) {
			$event->setCancelJoin(true);
		}
	}

	/**
	 * Prevents users from joining if they do not have access to the file.
	 *
	 * A user has access to the file if the file is publicly accessible (through
	 * a link share, for example) or if the user has direct access to it.
	 *
	 * A user has direct access to a file if they received the file (or an
	 * ancestor) through a user, group, circle or room share (but not through a
	 * link share, for example), or if they are the owner of such a file.
	 *
	 * This method should be called before a user joins a room.
	 *
	 * @param Room $room
	 * @param string $userId
	 * @throws UnauthorizedException
	 */
	protected function preventUsersWithoutAccessToTheFileFromJoining(Room $room, string $userId): void {
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
	protected function addUserAsPersistentParticipant(Room $room, string $userId): void {
		if ($room->getObjectType() !== 'file') {
			return;
		}

		if ($this->util->getAnyNodeOfFileAccessibleByUser($room->getObjectId(), $userId) === null) {
			return;
		}

		try {
			$this->participantService->getParticipant($room, $userId, false);
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
