<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Chat\SystemMessage;


use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\ModifyLobbyEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var ChatManager */
	protected $chatManager;
	/** @var TalkSession */
	protected $talkSession;
	/** @var IUserSession */
	protected $userSession;
	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(ChatManager $chatManager,
								TalkSession $talkSession,
								IUserSession $userSession,
								ITimeFactory $timeFactory) {
		$this->chatManager = $chatManager;
		$this->talkSession = $talkSession;
		$this->userSession = $userSession;
		$this->timeFactory = $timeFactory;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_BEFORE_SESSION_JOIN_CALL, static function(ModifyParticipantEvent $event) {
			$room = $event->getRoom();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($room->hasSessionsInCall()) {
				$listener->sendSystemMessage($room, 'call_joined');
			} else {
				$listener->sendSystemMessage($room, 'call_started');
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, static function(ModifyParticipantEvent $event) {
			$room = $event->getRoom();

			if ($event->getParticipant()->getInCallFlags() === Participant::FLAG_DISCONNECTED) {
				// This happens in case the user was kicked/lobbied
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'call_left', [], $event->getParticipant());
		});

		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_CREATE, static function(RoomEvent $event) {
			$room = $event->getRoom();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'conversation_created');
		});
		$dispatcher->addListener(Room::EVENT_AFTER_NAME_SET, static function(ModifyRoomEvent $event) {
			if ($event->getOldValue() === '' ||
				$event->getNewValue() === '') {
				return;
			}

			$room = $event->getRoom();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'conversation_renamed', [
				'newName' => $event->getNewValue(),
				'oldName' => $event->getOldValue(),
			]);
		});
		$dispatcher->addListener(Room::EVENT_AFTER_PASSWORD_SET, static function(ModifyRoomEvent $event) {
			$room = $event->getRoom();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($event->getNewValue() !== '') {
				$listener->sendSystemMessage($room, 'password_set');
			} else {
				$listener->sendSystemMessage($room, 'password_removed');
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_TYPE_SET, static function(ModifyRoomEvent $event) {
			$room = $event->getRoom();

			if ($event->getNewValue() === Room::PUBLIC_CALL) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guests_allowed');
			} else if ($event->getNewValue() === Room::GROUP_CALL) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guests_disallowed');
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_READONLY_SET, static function(ModifyRoomEvent $event) {
			$room = $event->getRoom();

			if ($room->getType() === Room::CHANGELOG_CONVERSATION) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($event->getNewValue() === Room::READ_ONLY) {
				$listener->sendSystemMessage($room, 'read_only');
			} else if ($event->getNewValue() === Room::READ_WRITE) {
				$listener->sendSystemMessage($room, 'read_only_off');
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_LOBBY_STATE_SET, static function(ModifyLobbyEvent $event) {
			if ($event->getNewValue() === $event->getOldValue()) {
				return;
			}

			$room = $event->getRoom();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($event->isTimerReached()) {
				$listener->sendSystemMessage($room, 'lobby_timer_reached');
			} else if ($event->getNewValue() === Webinary::LOBBY_NONE) {
				$listener->sendSystemMessage($room, 'lobby_none');
			} else if ($event->getNewValue() === Webinary::LOBBY_NON_MODERATORS) {
				$listener->sendSystemMessage($room, 'lobby_non_moderators');
			}
		});

		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, static function(AddParticipantsEvent $event) {
			$participants = $event->getParticipants();
			$user = \OC::$server->getUserSession()->getUser();
			$userId = $user instanceof IUser ? $user->getUID() : null;

			$room = $event->getRoom();

			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			foreach ($participants as $participant) {
				$userJoinedFileRoom = $room->getObjectType() === 'file' &&
						(!isset($participant['participantType']) || $participant['participantType'] !== Participant::USER_SELF_JOINED);
				if ($userJoinedFileRoom || $userId !== $participant['userId']) {
					$listener->sendSystemMessage($room, 'user_added', ['user' => $participant['userId']]);
				}
			}
		});
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, static function(RemoveUserEvent $event) {
			$room = $event->getRoom();

			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->sendSystemMessage($room, 'user_removed', ['user' => $event->getUser()->getUID()]);
		});
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, static function(ModifyParticipantEvent $event) {
			$room = $event->getRoom();

			if ($event->getNewValue() === Participant::MODERATOR) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'moderator_promoted', ['user' => $event->getParticipant()->getUser()]);
			} else if ($event->getNewValue() === Participant::USER) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'moderator_demoted', ['user' => $event->getParticipant()->getUser()]);
			} else if ($event->getNewValue() === Participant::GUEST_MODERATOR) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guest_moderator_promoted', ['session' => sha1($event->getParticipant()->getSessionId())]);
			} else if ($event->getNewValue() === Participant::GUEST) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guest_moderator_demoted', ['session' => sha1($event->getParticipant()->getSessionId())]);
			}
		});
		$listener = function(GenericEvent $event) {
			/** @var IShare $share */
			$share = $event->getSubject();

			if ($share->getShareType() !== IShare::TYPE_ROOM) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			/** @var Manager $manager */
			$manager = \OC::$server->query(Manager::class);

			$room = $manager->getRoomByToken($share->getSharedWith());
			$listener->sendSystemMessage($room, 'file_shared', ['share' => $share->getId()]);
		};
		$dispatcher->addListener('OCP\Share::postShare', $listener);
		$dispatcher->addListener(RoomShareProvider::class . '::' . 'share_file_again', $listener);
	}

	protected function sendSystemMessage(Room $room, string $message, array $parameters = [], Participant $participant = null): void {
		if ($participant instanceof Participant) {
			$actorType = $participant->isGuest() ? 'guests' : 'users';
			$sessionId = $participant->getSessionId();
			$sessionHash = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
			$actorId = $participant->isGuest() ? $sessionHash : $participant->getUser();
		} else {
			$user = $this->userSession->getUser();
			if (!$user instanceof IUser) {
				$actorType = 'guests';
				$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
				$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
			} else {
				$actorType = 'users';
				$actorId = $user->getUID();
			}
		}

		$this->chatManager->addSystemMessage(
			$room, $actorType, $actorId,
			json_encode(['message' => $message, 'parameters' => $parameters]),
			$this->timeFactory->getDateTime(), $message === 'file_shared'
		);
	}
}
