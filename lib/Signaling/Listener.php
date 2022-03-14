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

namespace OCA\Talk\Signaling;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\ChatEvent;
use OCA\Talk\Events\EndCallForEveryoneEvent;
use OCA\Talk\Events\ModifyEveryoneEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ParticipantEvent;
use OCA\Talk\Events\RemoveParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\GuestManager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCP\EventDispatcher\IEventDispatcher;

class Listener {
	public static function register(IEventDispatcher $dispatcher): void {
		self::registerInternalSignaling($dispatcher);
		self::registerExternalSignaling($dispatcher);
	}

	protected static function isUsingInternalSignaling(): bool {
		/** @var Config $config */
		$config = \OC::$server->get(Config::class);
		return $config->getSignalingMode() === Config::SIGNALING_INTERNAL;
	}

	protected static function registerInternalSignaling(IEventDispatcher $dispatcher): void {
		$listener = static function (RoomEvent $event): void {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Messages $messages */
			$messages = \OC::$server->get(Messages::class);
			$messages->addMessageForAllParticipants($event->getRoom(), 'refresh-participant-list');
		};
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_CONNECT, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_GUEST_CONNECT, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_UPDATE_CALL_FLAGS, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PERMISSIONS_SET, $listener);
		$dispatcher->addListener(GuestManager::EVENT_AFTER_NAME_UPDATE, $listener);

		$listener = static function (ParticipantEvent $event): void {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			$room = $event->getRoom();

			/** @var Messages $messages */
			$messages = \OC::$server->get(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');
		};
		$dispatcher->addListener(Room::EVENT_BEFORE_USER_REMOVE, $listener);
		$dispatcher->addListener(Room::EVENT_BEFORE_PARTICIPANT_REMOVE, $listener);
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DISCONNECT, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_PERMISSIONS_SET, $listener);

		$listener = static function (RoomEvent $event): void {
			$room = $event->getRoom();
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Messages $messages */
			$messages = \OC::$server->get(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');
		};
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DELETE, $listener);
	}

	protected static function registerExternalSignaling(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, static function (AddParticipantsEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$notifier->roomInvited($event->getRoom(), $event->getParticipants());
		});
		$listener = static function (RoomEvent $event): void {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_NAME_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_DESCRIPTION_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PASSWORD_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_TYPE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_READONLY_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_LISTABLE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_LOBBY_STATE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SIP_ENABLED_SET, $listener);
		// TODO remove handler with "roomModified" in favour of handler with
		// "participantsModified" once the clients no longer expect a
		// "roomModified" message for participant type changes.
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, $listener);

		$listener = static function (ModifyParticipantEvent $event): void {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];
			// If the participant is not active in the room the "participants"
			// request will be sent anyway, although with an empty "changed"
			// property.

			/** @var SessionService $sessionService */
			$sessionService = \OC::$server->get(SessionService::class);
			$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
			foreach ($sessions as $session) {
				$sessionIds[] = $session->getSessionId();
			}

			$notifier->participantsModified($event->getRoom(), $sessionIds);
		};
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_PERMISSIONS_SET, $listener);

		$dispatcher->addListener(Room::EVENT_AFTER_PERMISSIONS_SET, static function (RoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];

			// Setting the room permissions resets the permissions of all
			// participants, even those with custom attendee permissions.

			// FIXME This approach does not scale, as the update message for all
			// the sessions in a conversation can exceed the allowed size of the
			// request in conversations with a large number of participants.
			// However, note that a single message with the general permissions
			// to be set on all participants can not be sent either, as the
			// general permissions could be overriden by custom attendee
			// permissions in specific participants.

			/** @var ParticipantService $participantService */
			$participantService = \OC::$server->get(ParticipantService::class);
			$participants = $participantService->getSessionsAndParticipantsForRoom($event->getRoom());
			foreach ($participants as $participant) {
				$session = $participant->getSession();
				if ($session) {
					$sessionIds[] = $session->getSessionId();
				}
			}

			$notifier->participantsModified($event->getRoom(), $sessionIds);
		});

		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DELETE, static function (RoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);
			/** @var ParticipantService $participantService */
			$participantService = \OC::$server->get(ParticipantService::class);

			$room = $event->getRoom();
			$notifier->roomDeleted($room, $participantService->getParticipantUserIds($room));
		});
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, static function (RemoveUserEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$notifier->roomsDisinvited($event->getRoom(), [$event->getUser()->getUID()]);
		});
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_REMOVE, static function (RemoveParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];

			/** @var SessionService $sessionService */
			$sessionService = \OC::$server->get(SessionService::class);
			$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
			foreach ($sessions as $session) {
				$sessionIds[] = $session->getSessionId();
			}

			if ($event->getParticipant()->getSession()) {
				$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
				$notifier->roomSessionsRemoved($event->getRoom(), $sessionIds);
			}

			if (!empty($sessionIds)) {
				$notifier->roomSessionsRemoved($event->getRoom(), $sessionIds);
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DISCONNECT, static function (ParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];
			if ($event->getParticipant()->getSession()) {
				// Only for guests and self-joined users disconnecting is "leaving" and therefor should trigger a disinvite
				$attendeeParticipantType = $event->getParticipant()->getAttendee()->getParticipantType();
				if ($attendeeParticipantType === Participant::GUEST
					|| $attendeeParticipantType === Participant::GUEST_MODERATOR) {
					$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
					$notifier->roomSessionsRemoved($event->getRoom(), $sessionIds);
				}
				if ($attendeeParticipantType === Participant::USER_SELF_JOINED) {
					$notifier->roomsDisinvited($event->getRoom(), [$event->getParticipant()->getAttendee()->getActorId()]);
				}
			}
		});

		$listener = static function (ModifyParticipantEvent $event): void {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			if ($event instanceof ModifyEveryoneEvent) {
				// If everyone is disconnected, we will not do O(n) requests.
				// Instead, the listener of Room::EVENT_AFTER_END_CALL_FOR_EVERYONE
				// will send all sessions to the HPB with 1 request.
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];

			/** @var SessionService $sessionService */
			$sessionService = \OC::$server->get(SessionService::class);
			$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
			foreach ($sessions as $session) {
				$sessionIds[] = $session->getSessionId();
			}

			if (!empty($sessionIds)) {
				$notifier->roomInCallChanged(
					$event->getRoom(),
					$event->getNewValue(),
					$sessionIds
				);
			}
		};
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_UPDATE_CALL_FLAGS, $listener);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, $listener);

		$dispatcher->addListener(Room::EVENT_AFTER_END_CALL_FOR_EVERYONE, static function (EndCallForEveryoneEvent $event): void {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			$sessionIds = $event->getSessionIds();

			if (empty($sessionIds)) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$notifier->roomInCallChanged(
				$event->getRoom(),
				$event->getNewValue(),
				$sessionIds
			);
		});

		$dispatcher->addListener(Room::EVENT_AFTER_GUESTS_CLEAN, static function (RoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			// TODO: The list of removed session ids should be passed through the event
			// so the signaling server can optimize forwarding the message.
			$sessionIds = [];
			$notifier->participantsModified($event->getRoom(), $sessionIds);
		});
		$dispatcher->addListener(GuestManager::EVENT_AFTER_NAME_UPDATE, static function (ModifyParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->get(BackendNotifier::class);

			$sessionIds = [];

			/** @var SessionService $sessionService */
			$sessionService = \OC::$server->get(SessionService::class);
			$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
			foreach ($sessions as $session) {
				$sessionIds[] = $session->getSessionId();
			}

			if (!empty($sessionIds)) {
				$notifier->participantsModified($event->getRoom(), $sessionIds);
			}
		});

		$dispatcher->addListener(ChatManager::EVENT_AFTER_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
		$dispatcher->addListener(ChatManager::EVENT_AFTER_SYSTEM_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
		$dispatcher->addListener(ChatManager::EVENT_AFTER_MULTIPLE_SYSTEM_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
	}

	public static function notifyUsersViaExternalSignalingToRefreshTheChat(ChatEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		if ($event->shouldSkipLastActivityUpdate()) {
			return;
		}

		/** @var BackendNotifier $notifier */
		$notifier = \OC::$server->get(BackendNotifier::class);

		$room = $event->getRoom();
		$message = [
			'type' => 'chat',
			'chat' => [
				'refresh' => true,
			],
		];
		$notifier->sendRoomMessage($room, $message);
	}
}
