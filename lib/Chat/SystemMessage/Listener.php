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

namespace OCA\Spreed\Chat\SystemMessage;


use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Chat\Parser\SystemMessage;
use OCA\Spreed\Manager;
use OCA\Spreed\Model\Message;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\Share\RoomShareProvider;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var ChatManager */
	protected $chatManager;
	/** @var TalkSession */
	protected $talkSession;
	/** @var IUserSession */
	protected $userSession;
	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(EventDispatcherInterface $dispatcher,
								ChatManager $chatManager,
								TalkSession $talkSession,
								IUserSession $userSession,
								ITimeFactory $timeFactory) {
		$this->dispatcher = $dispatcher;
		$this->chatManager = $chatManager;
		$this->talkSession = $talkSession;
		$this->userSession = $userSession;
		$this->timeFactory = $timeFactory;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(Room::class . '::preSessionJoinCall', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($room->hasSessionsInCall()) {
				$listener->sendSystemMessage($room, 'call_joined');
			} else {
				$listener->sendSystemMessage($room, 'call_started');
			}
		});
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'call_left');
		});

		$dispatcher->addListener(Room::class . '::createRoom', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'conversation_created');
		});
		$dispatcher->addListener(Room::class . '::postSetName', function(GenericEvent $event) {
			if ($event->getArgument('oldName') === '' ||
				  $event->getArgument('newName') === '') {
				return;
			}

			/** @var Room $room */
			$room = $event->getSubject();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			$listener->sendSystemMessage($room, 'conversation_renamed', $event->getArguments());
		});
		$dispatcher->addListener(Room::class . '::postSetPassword', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($event->getArgument('password')) {
				$listener->sendSystemMessage($room, 'password_set');
			} else {
				$listener->sendSystemMessage($room, 'password_removed');
			}
		});
		$dispatcher->addListener(Room::class . '::postChangeType', function(GenericEvent $event) {
			$arguments = $event->getArguments();

			/** @var Room $room */
			$room = $event->getSubject();

			if ($arguments['newType'] === Room::PUBLIC_CALL) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guests_allowed', $event->getArguments());
			}
			if ($arguments['oldType'] === Room::PUBLIC_CALL) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guests_disallowed', $event->getArguments());
			}
		});
		$dispatcher->addListener(Room::class . '::postSetReadOnly', function(GenericEvent $event) {
			$arguments = $event->getArguments();

			/** @var Room $room */
			$room = $event->getSubject();

			if ($room->getType() === Room::CHANGELOG_CONVERSATION) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($arguments['newState'] === Room::READ_ONLY) {
				$listener->sendSystemMessage($room, 'read_only', $event->getArguments());
			} else if ($arguments['newState'] === Room::READ_WRITE) {
				$listener->sendSystemMessage($room, 'read_only_off', $event->getArguments());
			}
		});

		$dispatcher->addListener(Room::class . '::postAddUsers', function(GenericEvent $event) {
			$participants = $event->getArgument('users');
			$user = \OC::$server->getUserSession()->getUser();
			$userId = $user instanceof IUser ? $user->getUID() : null;

			/** @var Room $room */
			$room = $event->getSubject();

			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			foreach ($participants as $participant) {
				if ($room->getObjectType() === 'file' || $userId !== $participant['userId']) {
					$listener->sendSystemMessage($room, 'user_added', ['user' => $participant['userId']]);
				}
			}
		});
		$dispatcher->addListener(Room::class . '::postRemoveUser', function(GenericEvent $event) {
			/** @var IUser $user */
			$user = $event->getArgument('user');
			/** @var Room $room */
			$room = $event->getSubject();

			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				return;
			}

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->sendSystemMessage($room, 'user_removed', ['user' => $user->getUID()]);
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantType', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			if ($event->getArgument('newType') === Participant::MODERATOR) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'moderator_promoted', ['user' => $event->getArgument('user')]);
			} else if ($event->getArgument('newType') === Participant::USER) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'moderator_demoted', ['user' => $event->getArgument('user')]);
			}
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantTypeBySession', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var Participant $participant */
			$participant = $event->getArgument('participant');

			if ($event->getArgument('newType') === Participant::GUEST_MODERATOR) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guest_moderator_promoted', ['session' => sha1($participant->getSessionId())]);
			} else if ($event->getArgument('newType') === Participant::GUEST) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);
				$listener->sendSystemMessage($room, 'guest_moderator_demoted', ['session' => sha1($participant->getSessionId())]);
			}
		});
		$listener = function(GenericEvent $event) {
			/** @var IShare $share */
			$share = $event->getSubject();

			if ($share->getShareType() !== Share::SHARE_TYPE_ROOM) {
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

	protected function sendSystemMessage(Room $room, string $message, array $parameters = []): void {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			$actorType = 'guests';
			$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
			$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
		} else {
			$actorType = 'users';
			$actorId = $user->getUID();
		}

		$this->chatManager->addSystemMessage(
			$room, $actorType, $actorId,
			json_encode(['message' => $message, 'parameters' => $parameters]),
			$this->timeFactory->getDateTime(), $message === 'file_shared'
		);
	}
}
