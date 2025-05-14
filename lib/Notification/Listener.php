<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Notification;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Controller\ChatController;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\BeforeCallStartedEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\CallStartedEvent;
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
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	protected bool $shouldSendCallNotification = false;
	/** @var array<string, INotification> $preparedCallNotifications Map of language => parsed notification in that language */
	protected array $preparedCallNotifications = [];

	public function __construct(
		protected IConfig $serverConfig,
		protected IDBConnection $connection,
		protected IManager $notificationManager,
		protected Notifier $notificationProvider,
		protected ParticipantService $participantsService,
		protected IEventDispatcher $dispatcher,
		protected IUserSession $userSession,
		protected ITimeFactory $timeFactory,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		match (get_class($event)) {
			CallNotificationSendEvent::class => $this->sendCallNotification($event->getRoom(), $event->getActor()?->getAttendee(), $event->getTarget()->getAttendee()),
			AttendeesAddedEvent::class => $this->generateInvitation($event->getRoom(), $event->getAttendees()),
			UserJoinedRoomEvent::class => $this->handleUserJoinedRoomEvent($event),
			BeforeCallStartedEvent::class => $this->checkCallNotifications($event),
			ParticipantModifiedEvent::class => $this->afterParticipantJoinedCall($event),
			CallStartedEvent::class => $this->afterCallStarted($event),
		};
	}

	/**
	 * Room invitation: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 */
	protected function generateInvitation(Room $room, array $attendees): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

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
		if ($room->getType() === Room::TYPE_ONE_TO_ONE
			|| $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			// No notifications for one-to-one, save a query
			return;
		}

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
	protected function checkCallNotifications(BeforeCallStartedEvent $event): void {
		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT)) {
			$this->shouldSendCallNotification = false;
			return;
		}

		if ($event->getRoom()->getObjectType() === Room::OBJECT_TYPE_FILE) {
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

		// Purge received call notifications on joining
		$this->markCallNotificationsRead($event->getRoom());
	}

	protected function afterCallStarted(CallStartedEvent $event): void {
		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT)) {
			return;
		}

		if ($this->shouldSendCallNotification || $event->getRoom()->isFederatedConversation()) {
			$this->sendCallNotifications($event->getRoom(), $event->getDetailList(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT_FOR));
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
	 * @param list<string> $silentFor
	 */
	protected function sendCallNotifications(Room $room, array $silentFor = []): void {
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

		$this->preparedCallNotifications = [];
		$users = $this->participantsService->getParticipantUsersForCallNotifications($room);

		if (!empty($silentFor)) {
			// Remove users for which the call should be silent
			foreach ($silentFor as $userId) {
				unset($users[$userId]);
			}
		}

		// Room name depends on the notification user for one-to-one,
		// so we avoid pre-parsing it there. Also, it comes with some base load,
		// so we only do it for "big enough" calls.
		$preparseNotificationForPush = count($users) > 10;
		if ($preparseNotificationForPush) {
			$fallbackLang = $this->serverConfig->getSystemValue('force_language', null);
			if (is_string($fallbackLang)) {
				/** @psalm-var array<string, string> $userLanguages */
				$userLanguages = [];
			} else {
				$fallbackLang = $this->serverConfig->getSystemValueString('default_language', 'en');
				/** @psalm-var array<string, string> $userLanguages */
				$userLanguages = $this->serverConfig->getUserValueForUsers('core', 'lang', array_map('strval', array_keys($users)));
			}
		}

		$this->connection->beginTransaction();
		try {
			foreach ($users as $userId => $isImportant) {
				$userId = (string)$userId;
				if ($actorId === $userId) {
					continue;
				}

				if ($preparseNotificationForPush) {
					// Get the settings for this particular user, then check if we have notifications to email them
					$languageCode = $userLanguages[$userId] ?? $fallbackLang;

					if (!isset($this->preparedCallNotifications[$languageCode])) {
						$translatedNotification = clone $notification;

						$this->notificationManager->setPreparingPushNotification(true);
						$this->preparedCallNotifications[$languageCode] = $this->notificationProvider->prepare($translatedNotification, $languageCode);
						$this->notificationManager->setPreparingPushNotification(false);
					}
					$userNotification = $this->preparedCallNotifications[$languageCode];
				} else {
					$userNotification = $notification;
				}

				try {
					$userNotification->setUser($userId);
					$userNotification->setPriorityNotification($isImportant);
					$this->notificationManager->notify($userNotification);
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
	 * Forced call notification when ringing a single participant again
	 */
	protected function sendCallNotification(Room $room, ?Attendee $actor, Attendee $target): void {
		try {
			// Remove previous call notifications
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setObject('call', $room->getToken())
				->setUser($target->getActorId());
			$this->notificationManager->markProcessed($notification);

			$dateTime = $this->timeFactory->getDateTime();
			$notification->setSubject('call', [
				'callee' => $actor?->getActorId(),
			])
				->setDateTime($dateTime)
				->setPriorityNotification($target->isImportant());
			$this->notificationManager->notify($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
	}
}
