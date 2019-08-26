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

namespace OCA\Spreed\Signaling;

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Config;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	public static function register(EventDispatcherInterface $dispatcher): void {
		self::registerInternalSignaling($dispatcher);
		self::registerExternalSignaling($dispatcher);
	}

	protected static function isUsingInternalSignaling(): bool {
		/** @var Config $config */
		$config = \OC::$server->query(Config::class);
		return empty($config->getSignalingServers());
	}

	protected static function registerInternalSignaling(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');
		};
		$dispatcher->addListener(Room::class . '::postJoinRoom', $listener);
		$dispatcher->addListener(Room::class . '::postJoinRoomGuest', $listener);
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);
		$dispatcher->addListener(GuestManager::class . '::updateName', $listener);

		$listener = function(GenericEvent $event) {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');

			// When "addMessageForAllParticipants" is called the participant is
			// no longer in the room, so the message needs to be explicitly
			// added for the participant.
			/** @var Participant $participant */
			$participant = $event->getArgument('participant');
			if ($participant->getSessionId() !== '0') {
				$messages->addMessage($participant->getSessionId(), $participant->getSessionId(), 'refresh-participant-list');
			}
		};
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);

		$listener = function(GenericEvent $event) {
			if (!self::isUsingInternalSignaling()) {
				return;
			}

			/** @var Messages $messages */
			$messages = \OC::$server->query(Messages::class);
			$participants = $event->getArgument('participants');
			foreach ($participants['users'] as $participant) {
				$messages->addMessage($participant['sessionId'], $participant['sessionId'], 'refresh-participant-list');
			}
			foreach ($participants['guests'] as $participant) {
				$messages->addMessage($participant['sessionId'], $participant['sessionId'], 'refresh-participant-list');
			}
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);
	}

	protected static function registerExternalSignaling(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(Room::class . '::postAddUsers', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$participants= $event->getArgument('users');
			$notifier->roomInvited($room, $participants);
		});
		$dispatcher->addListener(Room::class . '::postSetName', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetPassword', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postChangeType', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetReadOnly', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetLobbyState', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantType', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			// The type of a participant has changed, notify all participants
			// so they can update the room properties.
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantTypeBySession', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			// The type of a participant has changed, notify all participants
			// so they can update the room properties.
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postDeleteRoom', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$participants = $event->getArgument('participants');
			$notifier->roomDeleted($room, $participants);
		});
		$dispatcher->addListener(Room::class . '::postRemoveUser', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$user = $event->getArgument('user');
			$notifier->roomsDisinvited($room, [$user->getUID()]);
		});
		$dispatcher->addListener(Room::class . '::postRemoveBySession', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$participant = $event->getArgument('participant');
			$notifier->roomSessionsRemoved($room, [$participant->getSessionId()]);
		});
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$flags = $event->getArgument('flags');
			$notifier->roomInCallChanged($room, $flags, [$sessionId]);
		});
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->roomInCallChanged($room, Participant::FLAG_DISCONNECTED, [$sessionId]);
		});
		$dispatcher->addListener(Room::class . '::postRemoveBySession', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$participant = $event->getArgument('participant');
			$notifier->participantsModified($room, [$participant->getSessionId()]);
		});
		$dispatcher->addListener(Room::class . '::postCleanGuests', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			// TODO: The list of removed session ids should be passed through the event
			// so the signaling server can optimize forwarding the message.
			$sessionIds = [];
			$notifier->participantsModified($room, $sessionIds);
		});
		$dispatcher->addListener(GuestManager::class . '::updateName', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->participantsModified($room, [$sessionId]);
		});
		$dispatcher->addListener(ChatManager::class . '::sendMessage', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
			$message = [
				'type' => 'chat',
				'chat' => [
					'refresh' => true,
				],
			];
			$notifier->sendRoomMessage($room, $message);
		});
		$dispatcher->addListener(ChatManager::class . '::sendSystemMessage', function(GenericEvent $event) {
			if (self::isUsingInternalSignaling()) {
				return;
			}

			/** @var BackendNotifier $notifier */
			$notifier = \OC::$server->query(BackendNotifier::class);

			$room = $event->getSubject();
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
