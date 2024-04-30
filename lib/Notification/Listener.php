<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Notification;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Controller\ChatController;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\UserJoinedRoomEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
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

	public function handle(Event $event): void {
		match (get_class($event)) {
			CallNotificationSendEvent::class => $this->sendCallNotification($event->getRoom(), $event->getActor()->getAttendee(), $event->getTarget()->getAttendee()),
			AttendeesAddedEvent::class => $this->generateInvitation($event->getRoom(), $event->getAttendees()),
			UserJoinedRoomEvent::class => $this->handleUserJoinedRoomEvent($event),
			BeforeParticipantModifiedEvent::class => $this->checkCallNotifications($event),
			ParticipantModifiedEvent::class => $this->afterParticipantJoinedCall($event),
		};
	}

	/**
	 * Room invitation: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 */
	protected function generateInvitation(Room $room, array $attendees): void {
		if ($room->getObjectType() === Room::OBJECT_TYPE_FILE) {
			return;
		}

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

		foreach ($attendees as $attendee) {
			if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
				// No user => no activity
				continue;
			}

			if ($actorId === $attendee->getActorId()) {
				// No activity for self-joining and the creator
				continue;
			}

			try {
				$notification->setUser($attendee->getActorId());
				$this->notificationManager->notify($notification);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
	}

	protected function handleUserJoinedRoomEvent(UserJoinedRoomEvent $event): void {
		$this->markInvitationRead($event->getRoom(), $event->getUser());
		$this->markReactionNotificationsRead($event->getRoom(), $event->getUser());
	}

	/**
	 * Room invitation: "{actor} invited you to {call}"
	 */
	protected function markInvitationRead(Room $room, IUser $user): void {
		$notification = $this->notificationManager->createNotification();
		try {
			$notification->setApp(Application::APP_ID)
				->setUser($user->getUID())
				->setObject('room', $room->getToken())
				->setSubject('invitation');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	/**
	 * Reaction: "{user} reacted with {reaction} in {call}"
	 *
	 * We should not mark reactions read based on the read-status of the comment
	 * they apply to, but the point in time when the reaction was done.
	 * However, these messages are not visible and don't update the read marker,
	 * so we purge them on joining the conversation.
	 * This already happened before on the initial loading of a chat with
	 * {@see ChatController::getMessageContext()}, but not on follow-up visits
	 * (when the room history was not empty in the browser storage) this does
	 * not trigger, so it was possible to end a session with all messages read,
	 * but still having notifications about reactions.
	 * For normal chat messages this happens in {@see Notifier::parseChatMessage()}
	 */
	protected function markReactionNotificationsRead(Room $room, IUser $user): void {
		$notification = $this->notificationManager->createNotification();
		try {
			$notification->setApp(Application::APP_ID)
				->setUser($user->getUID())
				->setObject('chat', $room->getToken())
				->setSubject('reaction');
			$this->notificationManager->markProcessed($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 */
	protected function checkCallNotifications(BeforeParticipantModifiedEvent $event): void {
		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getOldValue() !== Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() === Participant::FLAG_DISCONNECTED) {
			return;
		}

		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT)) {
			$this->shouldSendCallNotification = false;
			return;
		}

		$room = $event->getRoom();
		if ($room->getActiveSince() instanceof \DateTime) {
			// Call already active => No new notifications
			$this->shouldSendCallNotification = false;
			return;
		}

		if ($room->getObjectType() === Room::OBJECT_TYPE_FILE) {
			$this->shouldSendCallNotification = false;
			return;
		}

		$this->shouldSendCallNotification = true;
	}

	protected function afterParticipantJoinedCall(ParticipantModifiedEvent $event): void {
		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getOldValue() !== Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() === Participant::FLAG_DISCONNECTED) {
			return;
		}

		if ($event->getRoom()->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #4.0: ' . microtime(true));
		}
		$this->markCallNotificationsRead($event->getRoom());
		if ($event->getRoom()->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #4.1: ' . microtime(true));
		}
		if ($this->shouldSendCallNotification) {
			$this->sendCallNotifications($event->getRoom());
		}
		if ($event->getRoom()->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #4.2: ' . microtime(true));
		}
	}

	/**
	 * Call notification: "{user} wants to talk with you"
	 *
	 * @param Room $room
	 */
	protected function markCallNotificationsRead(Room $room): void {
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

	/**
	 * Call notification: "{user} wants to talk with you"
	 *
	 * @param Room $room
	 */
	protected function sendCallNotifications(Room $room): void {
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #7.0: ' . microtime(true));
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

		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #7.1: ' . microtime(true));
		}

		$userIds = $this->participantsService->getParticipantUserIdsForCallNotifications($room);
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #7.2: ' . microtime(true));
		}
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
				if ($room->getToken() === 'c9bui2ju') {
					\OC::$server->getLogger()->warning('Debugging step #7.2.' . $userId . ': ' . microtime(true));
				}
			}
		} catch (\Throwable $e) {
			$this->connection->rollBack();
			throw $e;
		}
		$this->connection->commit();
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #7.3: ' . microtime(true));
		}

		if ($shouldFlush) {
			$this->notificationManager->flush();
		}
		if ($room->getToken() === 'c9bui2ju') {
			\OC::$server->getLogger()->warning('Debugging step #7.4: ' . microtime(true));
		}
	}

	/**
	 * Forced call notification when ringing a single participant again
	 */
	protected function sendCallNotification(Room $room, Attendee $actor, Attendee $target): void {
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
