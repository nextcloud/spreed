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
use OCA\Talk\Events\ChatParticipantEvent;
use OCA\Talk\Events\ModifyLobbyEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Events\ParticipantEvent;
use OCA\Talk\Events\RemoveParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\GuestManager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\EventDispatcher\IEventDispatcher;

class Listener {

	public static function register(IEventDispatcher $dispatcher): void {
		self::registerInternalSignaling($dispatcher);
		self::registerExternalSignaling($dispatcher);
	}

	protected static function isUsingInternalSignaling(): bool {
		/** @var Config $config */
		$config = \OC::$server->query(Config::class);
		return empty($config->getSignalingServers());
	}

	protected static function registerInternalSignaling(IEventDispatcher $dispatcher): void {
		$listener = static function(RoomEvent $event) {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$messages->addMessageForAllParticipants($event->getRoom(), 'refresh-participant-list');
		};
		$dispatcher->addListener(Room::class . '::postJoinRoom', $listener);
		$dispatcher->addListener(Room::class . '::postJoinRoomGuest', $listener);
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);
		$dispatcher->addListener(GuestManager::class . '::updateName', $listener);

		$listener = static function(ParticipantEvent $event) {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			$room = $event->getRoom();

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');

			// When "addMessageForAllParticipants" is called the participant is
			// no longer in the room, so the message needs to be explicitly
			// added for the participant.
			/** @var Participant $participant */
			$participant = $event->getParticipant();
			if ($participant->getSessionId() !== '0') {
				$messages->addMessage($participant->getSessionId(), $participant->getSessionId(), 'refresh-participant-list');
			}
		};
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);

		$listener = static function(RoomEvent $event) {
			$room = $event->getRoom();
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$participants = $room->getParticipantsLegacy();
			foreach ($participants['users'] as $participant) {
				$messages->addMessage($participant['sessionId'], $participant['sessionId'], 'refresh-participant-list');
			}
			foreach ($participants['guests'] as $participant) {
				$messages->addMessage($participant['sessionId'], $participant['sessionId'], 'refresh-participant-list');
			}
		};
		$dispatcher->addListener(Room::class . '::preDeleteRoom', $listener);
	}

	protected static function registerExternalSignaling(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::class . '::postAddUsers', static function(AddParticipantsEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomInvited($event->getRoom(), $event->getParticipants());
		});
		$dispatcher->addListener(Room::class . '::postSetName', static function(ModifyRoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetPassword', static function(ModifyRoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetType', static function(ModifyRoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetReadOnly', static function(ModifyRoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetLobbyState', static function(ModifyLobbyEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantType', static function(ModifyParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			// The type of a participant has changed, notify all participants
			// so they can update the room properties.
			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantTypeBySession', static function(ModifyParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			// The type of a participant has changed, notify all participants
			// so they can update the room properties.
			$notifier->roomModified($event->getRoom());
		});
		$dispatcher->addListener(Room::class . '::preDeleteRoom', static function(RoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getRoom();
			$participants = $room->getParticipantsLegacy();
			$notifier->roomDeleted($room, $participants);
		});
		$dispatcher->addListener(Room::class . '::postRemoveUser', static function(RemoveUserEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomsDisinvited($event->getRoom(), [$event->getUser()->getUID()]);
		});
		$dispatcher->addListener(Room::class . '::postRemoveBySession', static function(RemoveParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomSessionsRemoved($event->getRoom(), [$event->getParticipant()->getSessionId()]);
		});

		$listener = static function(ModifyParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->roomInCallChanged(
				$event->getRoom(),
				$event->getNewValue(),
				[$event->getParticipant()->getSessionId()]
			);
		};
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);

		$dispatcher->addListener(Room::class . '::postCleanGuests', static function(RoomEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			// TODO: The list of removed session ids should be passed through the event
			// so the signaling server can optimize forwarding the message.
			$sessionIds = [];
			$notifier->participantsModified($event->getRoom(), $sessionIds);
		});
		$dispatcher->addListener(GuestManager::class . '::updateName', static function(ModifyParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$notifier->participantsModified($event->getRoom(), [$event->getParticipant()->getSessionId()]);
		});
		$dispatcher->addListener(ChatManager::class . '::postSendMessage', static function(ChatParticipantEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getRoom();
			$message = [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			];
			$notifier->sendRoomMessage($room, $message);
		});
		$dispatcher->addListener(ChatManager::class . '::postSendSystemMessage', static function(ChatEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getRoom();
			$message = [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			];
			$notifier->sendRoomMessage($room, $message);
		});
	}
}
