<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Notification;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\JoinRoomUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Events\SilentModifyParticipantEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	protected bool $shouldSendCallNotification = false;

	public function __construct(
		protected IDBConnection $connection,
		protected IManager $notificationManager,
		protected ParticipantService $participantsService,
		protected IEventDispatcher $dispatcher,
		protected IUserSession $userSession,
		protected ITimeFactory $timeFactory,
		protected LoggerInterface $logger,
	) {
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$listener = static function (AddParticipantsEvent $event): void {
			$room = $event->getRoom();

			if ($room->getObjectType() === 'file') {
				return;
			}

			$listener = Server::get(self::class);
			$listener->generateInvitation($room, $event->getParticipants());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, $listener);

		$listener = static function (JoinRoomUserEvent $event): void {
			$listener = Server::get(self::class);
			$listener->markInvitationRead($event->getRoom());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_CONNECT, $listener);

		$listener = static function (RoomEvent $event): void {
			$listener = Server::get(self::class);
			$silent = $event instanceof SilentModifyParticipantEvent;
			$listener->checkCallNotifications($event->getRoom(), $silent);
		};
		$dispatcher->addListener(Room::EVENT_BEFORE_SESSION_JOIN_CALL, $listener);

		$listener = static function (RoomEvent $event): void {
			$listener = Server::get(self::class);
			$listener->sendCallNotifications($event->getRoom());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, $listener);

		$listener = static function (RoomEvent $event): void {
			$listener = Server::get(self::class);
			$listener->markCallNotificationsRead($event->getRoom());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, $listener);
	}

	/**
	 * Room invitation: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param array[] $participants
	 */
	public function generateInvitation(Room $room, array $participants): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		$actorId = $actor->getUID();

		$notification = $this->notificationManager->createNotification();
		$shouldFlush = $this->notificationManager->defer();
		$dateTime = $this->timeFactory->getDateTime();
		try {
			$notification->setApp(Application::APP_ID)
				->setDateTime($dateTime)
				->setObject('room', $room->getToken())
				->setSubject('invitation', [
					'actorId' => $actor->getUID(),
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			if ($shouldFlush) {
				$this->notificationManager->flush();
			}
			return;
		}

		foreach ($participants as $participant) {
			if ($participant['actorType'] !== Attendee::ACTOR_USERS) {
				// No user => no activity
				continue;
			}

			if ($actorId === $participant['actorId']) {
				// No activity for self-joining and the creator
				continue;
			}

			try {
				$notification->setUser($participant['actorId']);
				$this->notificationManager->notify($notification);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
	}

	/**
	 * Room invitation: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 */
	public function markInvitationRead(Room $room): void {
		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		try {
			$notification->setApp(Application::APP_ID)
				->setUser($currentUser->getUID())
				->setObject('room', $room->getToken())
				->setSubject('invitation');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 */
	public function checkCallNotifications(Room $room, bool $silent = false): void {
		if ($silent) {
			$this->shouldSendCallNotification = false;
			return;
		}
		if ($room->getActiveSince() instanceof \DateTime) {
			// Call already active => No new notifications
			$this->shouldSendCallNotification = false;
			return;
		}

		if ($room->getObjectType() === 'file') {
			$this->shouldSendCallNotification = false;
			return;
		}

		$this->shouldSendCallNotification = true;
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 *
	 * @param Room $room
	 */
	public function sendCallNotifications(Room $room): void {
		if (!$this->shouldSendCallNotification) {
			return;
		}

		$actor = $this->userSession->getUser();
		$actorId = $actor instanceof IUser ? $actor->getUID() :'';

		$notification = $this->notificationManager->createNotification();
		$shouldFlush = $this->notificationManager->defer();
		$dateTime = $this->timeFactory->getDateTime();
		try {
			// Remove all old notifications for this room
			$notification->setApp(Application::APP_ID)
				->setObject('room', $room->getToken());
			$this->notificationManager->markProcessed($notification);

			$notification->setObject('call', $room->getToken());
			$this->notificationManager->markProcessed($notification);

			$notification->setSubject('call', [
				'callee' => $actorId,
			])
				->setDateTime($dateTime);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			if ($shouldFlush) {
				$this->notificationManager->flush();
			}
			return;
		}

		$userIds = $this->participantsService->getParticipantUserIdsForCallNotifications($room);
		$this->connection->beginTransaction();
		try {
			foreach ($userIds as $userId) {
				if ($actorId === $userId) {
					continue;
				}

				try {
					$notification->setUser($userId);
					$this->notificationManager->notify($notification);
				} catch (\InvalidArgumentException $e) {
					$this->logger->error($e->getMessage(), ['exception' => $e]);
				}
			}
		} catch (\Throwable $e) {
			$this->connection->rollBack();
			throw $e;
		}
		$this->connection->commit();

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 *
	 * @param Room $room
	 */
	public function markCallNotificationsRead(Room $room): void {
		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		try {
			$notification->setApp(Application::APP_ID)
				->setUser($currentUser->getUID())
				->setObject('call', $room->getToken())
				->setSubject('call');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	public function handle(Event $event): void {
		if ($event instanceof CallNotificationSendEvent) {
			$this->sendCallNotification($event->getRoom(), $event->getActor()->getAttendee(), $event->getTarget()->getAttendee());
		}
	}

	public function sendCallNotification(Room $room, Attendee $actor, Attendee $target): void {
		try {
			// Remove previous call notifications
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setObject('call', $room->getToken())
				->setUser($target->getActorId());
			$this->notificationManager->markProcessed($notification);

			$dateTime = $this->timeFactory->getDateTime();
			$notification->setSubject('call', [
				'callee' => $actor->getActorId(),
			])
				->setDateTime($dateTime);
			$this->notificationManager->notify($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
	}
}
