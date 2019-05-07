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

namespace OCA\Spreed\Notification;

use OCA\Spreed\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var IManager */
	protected $notificationManager;
	/** @var IUserSession */
	protected $userSession;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var ILogger */
	protected $logger;

	public function __construct(IManager $notificationManager,
								IUserSession $userSession,
								ITimeFactory $timeFactory,
								ILogger $logger) {
		$this->notificationManager = $notificationManager;
		$this->userSession = $userSession;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			if ($room->getObjectType() === 'file') {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->generateInvitation($room, $event->getArgument('users'));
		};
		$dispatcher->addListener(Room::class . '::postAddUsers', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->markInvitationRead($room);
		};
		$dispatcher->addListener(Room::class . '::postJoinRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->generateCallNotifications($room);
		};
		$dispatcher->addListener(Room::class . '::preSessionJoinCall', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->markCallNotificationsRead($room);
		};
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
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
		$dateTime = $this->timeFactory->getDateTime();
		try {
			$notification->setApp('spreed')
				->setDateTime($dateTime)
				->setObject('room', $room->getToken())
				->setSubject('invitation', [
					'actorId' => $actor->getUID(),
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return;
		}

		foreach ($participants as $participant) {
			if ($actorId === $participant['userId']) {
				// No activity for self-joining and the creator
				continue;
			}

			try {
				$notification->setUser($participant['userId']);
				$this->notificationManager->notify($notification);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			}
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
			$notification->setApp('spreed')
				->setUser($currentUser->getUID())
				->setObject('room', $room->getToken())
				->setSubject('invitation');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return;
		}
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 *
	 * @param Room $room
	 */
	public function generateCallNotifications(Room $room): void {
		if ($room->getActiveSince() instanceof \DateTime) {
			// Call already active => No new notifications
			return;
		}

		if ($room->getObjectType() === 'file') {
			return;
		}

		$actor = $this->userSession->getUser();
		$actorId = $actor instanceof IUser ? $actor->getUID() :'';

		$notification = $this->notificationManager->createNotification();
		$dateTime = $this->timeFactory->getDateTime();
		try {
			// Remove all old notifications for this room
			$notification->setApp('spreed')
				->setObject('room', $room->getToken());
			$this->notificationManager->markProcessed($notification);

			$notification->setObject('call', $room->getToken());
			$this->notificationManager->markProcessed($notification);

			$notification->setSubject('call', [
					'callee' => $actorId,
				])
				->setDateTime($dateTime);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return;
		}

		$userIds = $room->getNotInCallUserIds();
		foreach ($userIds as $userId) {
			if ($actorId === $userId) {
				continue;
			}

			try {
				$notification->setUser($userId);
				$this->notificationManager->notify($notification);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			}
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
			$notification->setApp('spreed')
				->setUser($currentUser->getUID())
				->setObject('call', $room->getToken())
				->setSubject('call');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return;
		}
	}
}
