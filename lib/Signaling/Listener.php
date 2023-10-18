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
use OCA\Talk\Events\DuplicatedParticipantEvent;
use OCA\Talk\Events\EndCallForEveryoneEvent;
use OCA\Talk\Events\ModifyEveryoneEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Events\ParticipantEvent;
use OCA\Talk\Events\RemoveParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function handle(Event $event): void {
		if ($event instanceof RoomModifiedEvent) {
			self::notifyAfterRoomSettingsChanged($event);
		}
	}

	public static function register(IEventDispatcher $dispatcher): void {
		self::registerInternalSignaling($dispatcher);
		self::registerExternalSignaling($dispatcher);
	}

	protected static function isUsingInternalSignaling(): bool {
		$config = Server::get(Config::class);
		return $config->getSignalingMode() === Config::SIGNALING_INTERNAL;
	}

	protected static function registerInternalSignaling(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_CONNECT, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_GUEST_CONNECT, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_UPDATE_CALL_FLAGS, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_PERMISSIONS_SET, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(GuestManager::EVENT_AFTER_NAME_UPDATE, [self::class, 'refreshParticipantListUsingRoomEvent']);
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DELETE, [self::class, 'refreshParticipantListUsingRoomEvent']);

		$dispatcher->addListener(Room::EVENT_BEFORE_USER_REMOVE, [self::class, 'refreshParticipantListUsingParticipantEvent']);
		$dispatcher->addListener(Room::EVENT_BEFORE_PARTICIPANT_REMOVE, [self::class, 'refreshParticipantListUsingParticipantEvent']);
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DISCONNECT, [self::class, 'refreshParticipantListUsingParticipantEvent']);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_PERMISSIONS_SET, [self::class, 'refreshParticipantListUsingParticipantEvent']);
	}

	protected static function registerExternalSignaling(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, [self::class, 'notifyAfterUsersAdd']);
		$dispatcher->addListener(Room::EVENT_AFTER_NAME_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_DESCRIPTION_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_PASSWORD_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_TYPE_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_READONLY_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_LISTABLE_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_LOBBY_STATE_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_SIP_ENABLED_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_CALL_RECORDING, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_BREAKOUT_ROOM_MODE, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_BREAKOUT_ROOM_STATUS, [self::class, 'notifyAfterRoomSettingsChanged']);
		// TODO remove handler with "roomModified" in favour of handler with
		// "participantsModified" once the clients no longer expect a
		// "roomModified" message for participant type changes.
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, [self::class, 'notifyAfterRoomSettingsChanged']);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, [self::class, 'notifyAfterParticipantTypeAndPermissionsSet']);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_PERMISSIONS_SET, [self::class, 'notifyAfterParticipantTypeAndPermissionsSet']);
		$dispatcher->addListener(Room::EVENT_AFTER_PERMISSIONS_SET, [self::class, 'notifyAfterPermissionSet']);
		$dispatcher->addListener(Room::EVENT_BEFORE_ROOM_DELETE, [self::class, 'notifyBeforeRoomDeleted']);
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, [self::class, 'notifyAfterUserRemoved']);
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_REMOVE, [self::class, 'notifyAfterParticipantRemoved']);
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DISCONNECT, [self::class, 'notifyAfterRoomDisconected']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_JOIN_CALL, [self::class, 'notifyAfterJoinUpdateAndLeave']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_UPDATE_CALL_FLAGS, [self::class, 'notifyAfterJoinUpdateAndLeave']);
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, [self::class, 'notifyAfterJoinUpdateAndLeave']);
		$dispatcher->addListener(Room::EVENT_AFTER_END_CALL_FOR_EVERYONE, [self::class, 'sendEndCallForEveryone']);
		$dispatcher->addListener(Room::EVENT_AFTER_GUESTS_CLEAN, [self::class, 'notifyParticipantsAfterGuestClean']);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_CALL_RECORDING, [self::class, 'sendSignalingMessageWhenToggleRecording']);
		$dispatcher->addListener(Room::EVENT_AFTER_SET_BREAKOUT_ROOM_STATUS, [self::class, 'notifyParticipantsAfterSetBreakoutRoomStatus']);
		$dispatcher->addListener(GuestManager::EVENT_AFTER_NAME_UPDATE, [self::class, 'notifyParticipantsAfterNameUpdated']);
		$dispatcher->addListener(ChatManager::EVENT_AFTER_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
		$dispatcher->addListener(ChatManager::EVENT_AFTER_SYSTEM_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
		$dispatcher->addListener(ChatManager::EVENT_AFTER_MULTIPLE_SYSTEM_MESSAGE_SEND, [self::class, 'notifyUsersViaExternalSignalingToRefreshTheChat']);
	}

	public static function refreshParticipantListUsingRoomEvent(RoomEvent $event): void {
		if (!self::isUsingInternalSignaling()) {
			return;
		}

		$messages = Server::get(Messages::class);
		$messages->addMessageForAllParticipants($event->getRoom(), 'refresh-participant-list');
	}

	public static function refreshParticipantListUsingParticipantEvent(ParticipantEvent $event): void {
		if (!self::isUsingInternalSignaling()) {
			return;
		}

		$messages = Server::get(Messages::class);
		$messages->addMessageForAllParticipants($event->getRoom(), 'refresh-participant-list');
	}

	public static function notifyAfterUsersAdd(AddParticipantsEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$notifier->roomInvited($event->getRoom(), $event->getParticipants());
	}

	public static function notifyAfterRoomSettingsChanged(RoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$notifier->roomModified($event->getRoom());
	}

	public static function notifyAfterParticipantTypeAndPermissionsSet(ModifyParticipantEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$sessionIds = [];
		// If the participant is not active in the room the "participants"
		// request will be sent anyway, although with an empty "changed"
		// property.

		$sessionService = Server::get(SessionService::class);
		$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		$notifier->participantsModified($event->getRoom(), $sessionIds);
	}

	public static function notifyAfterPermissionSet(RoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

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

		$participantService = Server::get(ParticipantService::class);
		$participants = $participantService->getSessionsAndParticipantsForRoom($event->getRoom());
		foreach ($participants as $participant) {
			$session = $participant->getSession();
			if ($session) {
				$sessionIds[] = $session->getSessionId();
			}
		}

		$notifier->participantsModified($event->getRoom(), $sessionIds);
	}

	public static function notifyBeforeRoomDeleted(RoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);
		$participantService = Server::get(ParticipantService::class);

		$room = $event->getRoom();
		$notifier->roomDeleted($room, $participantService->getParticipantUserIds($room));
	}

	public static function notifyAfterUserRemoved(RemoveUserEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$notifier->roomsDisinvited($event->getRoom(), [$event->getUser()->getUID()]);
	}

	public static function notifyAfterParticipantRemoved(RemoveParticipantEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$sessionIds = [];

		$sessions = $event->getSessions();
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		if (!empty($sessionIds)) {
			$notifier->roomSessionsRemoved($event->getRoom(), $sessionIds);
		}
	}

	public static function notifyAfterRoomDisconected(ParticipantEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$sessionIds = [];
		if ($event->getParticipant()->getSession()) {
			// If a previous duplicated session is being removed it must be
			// notified to the external signaling server. Otherwise only for
			// guests disconnecting is "leaving" and therefor should trigger a
			// disinvite.
			$attendeeParticipantType = $event->getParticipant()->getAttendee()->getParticipantType();
			if ($event instanceof DuplicatedParticipantEvent
				|| $attendeeParticipantType === Participant::GUEST
				|| $attendeeParticipantType === Participant::GUEST_MODERATOR) {
				$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
				$notifier->roomSessionsRemoved($event->getRoom(), $sessionIds);
			}
		}
	}

	public static function notifyAfterJoinUpdateAndLeave(ModifyParticipantEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		if ($event instanceof ModifyEveryoneEvent) {
			// If everyone is disconnected, we will not do O(n) requests.
			// Instead, the listener of Room::EVENT_AFTER_END_CALL_FOR_EVERYONE
			// will send all sessions to the HPB with 1 request.
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$sessionIds = [];
		if ($event->getParticipant()->getSession()) {
			$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
		}

		if (!empty($sessionIds)) {
			$notifier->roomInCallChanged(
				$event->getRoom(),
				$event->getNewValue(),
				$sessionIds
			);
		}
	}

	public static function sendEndCallForEveryone(EndCallForEveryoneEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$sessionIds = $event->getSessionIds();

		if (empty($sessionIds)) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$notifier->roomInCallChanged(
			$event->getRoom(),
			$event->getNewValue(),
			[],
			true
		);
	}

	public static function notifyParticipantsAfterGuestClean(RoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		// TODO: The list of removed session ids should be passed through the event
		// so the signaling server can optimize forwarding the message.
		$sessionIds = [];
		$notifier->participantsModified($event->getRoom(), $sessionIds);
	}

	public static function notifyParticipantsAfterSetBreakoutRoomStatus(RoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$room = $event->getRoom();
		if ($room->getBreakoutRoomStatus() === BreakoutRoom::STATUS_STARTED) {
			self::notifyParticipantsAfterBreakoutRoomStarted($room);
		} else {
			self::notifyParticipantsAfterBreakoutRoomStopped($room);
		}
	}

	private static function notifyParticipantsAfterBreakoutRoomStarted(Room $room): void {
		$manager = Server::get(Manager::class);
		$breakoutRooms = $manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken());

		$switchToData = [];

		$participantService = Server::get(ParticipantService::class);
		$parentRoomParticipants = $participantService->getSessionsAndParticipantsForRoom($room);

		$notifier = Server::get(BackendNotifier::class);

		foreach ($breakoutRooms as $breakoutRoom) {
			$sessionIds = [];

			$breakoutRoomParticipants = $participantService->getParticipantsForRoom($breakoutRoom);
			foreach ($breakoutRoomParticipants as $breakoutRoomParticipant) {
				foreach (self::getSessionIdsForNonModeratorsMatchingParticipant($breakoutRoomParticipant, $parentRoomParticipants) as $sessionId) {
					$sessionIds[] = $sessionId;
				}
			}

			if (!empty($sessionIds)) {
				$notifier->switchToRoom($room, $breakoutRoom->getToken(), $sessionIds);
			}
		}
	}

	private static function getSessionIdsForNonModeratorsMatchingParticipant(Participant $targetParticipant, array $participants) {
		$sessionIds = [];

		foreach ($participants as $participant) {
			if ($participant->getAttendee()->getActorType() === $targetParticipant->getAttendee()->getActorType() &&
					$participant->getAttendee()->getActorId() === $targetParticipant->getAttendee()->getActorId() &&
					!$participant->hasModeratorPermissions()) {
				$session = $participant->getSession();
				if ($session) {
					$sessionIds[] = $session->getSessionId();
				}
			}
		}

		return $sessionIds;
	}

	private static function notifyParticipantsAfterBreakoutRoomStopped(Room $room): void {
		$manager = Server::get(Manager::class);
		$breakoutRooms = $manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken());

		$participantService = Server::get(ParticipantService::class);

		$notifier = Server::get(BackendNotifier::class);

		foreach ($breakoutRooms as $breakoutRoom) {
			$sessionIds = [];

			$participants = $participantService->getSessionsAndParticipantsForRoom($breakoutRoom);
			foreach ($participants as $participant) {
				$session = $participant->getSession();
				if ($session) {
					$sessionIds[] = $session->getSessionId();
				}
			}

			if (!empty($sessionIds)) {
				$notifier->switchToRoom($breakoutRoom, $room->getToken(), $sessionIds);
			}
		}
	}

	public static function notifyParticipantsAfterNameUpdated(ModifyParticipantEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$sessionIds = [];

		$sessionService = Server::get(SessionService::class);
		$sessions = $sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		if (!empty($sessionIds)) {
			$notifier->participantsModified($event->getRoom(), $sessionIds);
		}
	}

	public static function notifyUsersViaExternalSignalingToRefreshTheChat(ChatEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}

		if ($event->shouldSkipLastActivityUpdate()) {
			return;
		}

		$notifier = Server::get(BackendNotifier::class);

		$room = $event->getRoom();
		$message = [
			'type' => 'chat',
			'chat' => [
				'refresh' => true,
			],
		];
		$notifier->sendRoomMessage($room, $message);
	}

	public static function sendSignalingMessageWhenToggleRecording(ModifyRoomEvent $event): void {
		if (self::isUsingInternalSignaling()) {
			return;
		}
		if ($event->getParameter() !== 'callRecording') {
			return;
		}

		$room = $event->getRoom();
		$message = [
			'type' => 'recording',
			'recording' => [
				'status' => $event->getNewValue(),
			],
		];

		$notifier = Server::get(BackendNotifier::class);
		$notifier->sendRoomMessage($room, $message);
	}
}
