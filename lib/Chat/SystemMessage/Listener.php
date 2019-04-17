<?php
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
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\Share\RoomShareProvider;
use OCA\Spreed\TalkSession;
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
	/** @var Manager */
	protected $roomManager;
	/** @var TalkSession */
	protected $talkSession;
	/** @var IUserSession */
	protected $userSession;

	public function __construct(EventDispatcherInterface $dispatcher, ChatManager $chatManager, Manager $roomManager, TalkSession $talkSession, IUserSession $userSession) {
		$this->dispatcher = $dispatcher;
		$this->chatManager = $chatManager;
		$this->roomManager = $roomManager;
		$this->talkSession = $talkSession;
		$this->userSession = $userSession;
	}

	public function register() {

        $this->dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'call_left');
		});

		$this->dispatcher->addListener(Room::class . '::createRoom', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'conversation_created');
		});
		$this->dispatcher->addListener(Room::class . '::postSetName', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'conversation_renamed', $event->getArguments());
		});
		$this->dispatcher->addListener(Room::class . '::postSetPassword', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			if ($event->getArgument('password')) {
				$this->sendSystemMessage($room, 'password_set');
			} else {
				$this->sendSystemMessage($room, 'password_removed');
			}
		});
		$this->dispatcher->addListener(Room::class . '::postChangeType', function(GenericEvent $event) {
			$arguments = $event->getArguments();

			/** @var Room $room */
			$room = $event->getSubject();

			if ($arguments['newType'] === Room::PUBLIC_CALL) {
				$this->sendSystemMessage($room, 'guests_allowed', $event->getArguments());
			}
			if ($arguments['oldType'] === Room::PUBLIC_CALL) {
				$this->sendSystemMessage($room, 'guests_disallowed', $event->getArguments());
			}
		});

		$this->dispatcher->addListener(Room::class . '::postAddUsers', function(GenericEvent $event) {
			$participants = $event->getArgument('users');
			$user = $this->userSession->getUser();
			$userId = $user instanceof IUser ? $user->getUID() : null;

			/** @var Room $room */
			$room = $event->getSubject();
			foreach ($participants as $participant) {
				if ($room->getObjectType() === 'file' || $userId !== $participant['userId']) {
					$this->sendSystemMessage($room, 'user_added', ['user' => $participant['userId']]);
				}
			}
		});
		$this->dispatcher->addListener(Room::class . '::postRemoveUser', function(GenericEvent $event) {
			/** @var IUser $user */
			$user = $event->getArgument('user');
			/** @var Room $room */
			$room = $event->getSubject();

			$this->sendSystemMessage($room, 'user_removed', ['user' => $user->getUID()]);
		});
		$this->dispatcher->addListener(Room::class . '::postSetParticipantType', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			if ($event->getArgument('newType') === Participant::MODERATOR) {
				$this->sendSystemMessage($room, 'moderator_promoted', ['user' => $event->getArgument('user')]);
			} else if ($event->getArgument('newType') === Participant::USER) {
				$this->sendSystemMessage($room, 'moderator_demoted', ['user' => $event->getArgument('user')]);
			}
		});
		$this->dispatcher->addListener(Room::class . '::postSetParticipantTypeBySession', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var Participant $participant */
			$participant = $event->getArgument('participant');

			if ($event->getArgument('newType') === Participant::GUEST_MODERATOR) {
				$this->sendSystemMessage($room, 'guest_moderator_promoted', ['session' => sha1($participant->getSessionId())]);
			} else if ($event->getArgument('newType') === Participant::GUEST) {
				$this->sendSystemMessage($room, 'guest_moderator_demoted', ['session' => sha1($participant->getSessionId())]);
			}
		});
		$listener = function(GenericEvent $event) {
			/** @var IShare $share */
			$share = $event->getSubject();

			if ($share->getShareType() !== Share::SHARE_TYPE_ROOM) {
				return;
			}

			$room = $this->roomManager->getRoomByToken($share->getSharedWith());
			$this->sendSystemMessage($room, 'file_shared', ['share' => $share->getId()]);
		};
		$this->dispatcher->addListener('OCP\Share::postShare', $listener);
		$this->dispatcher->addListener(RoomShareProvider::class . '::' . 'share_file_again', $listener);

		$this->dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var IComment $chatMessage */
			$chatMessage = $event->getSubject();

			if ($chatMessage->getVerb() !== 'system') {
				return;
			}

			/** @var SystemMessage $parser */
			$parser = \OC::$server->query(SystemMessage::class);

			$user = $event->getArgument('user');
			if ($user instanceof IUser) {
				$parser->setUserInfo($event->getArgument('l10n'), $event->getArgument('user'));
			} else {
				/** @var Room $room */
				$room = $event->getArgument('room');
				/** @var TalkSession $talkSession */
				$talkSession = \OC::$server->query(TalkSession::class);
				$parser->setGuestInfo($event->getArgument('l10n'), sha1($talkSession->getSessionForRoom($room->getToken())));
			}

			try {
				list($message, $parameters) = $parser->parseMessage($chatMessage);

				$event->setArguments([
					'message' => $message,
					'parameters' => $parameters,
				]);
				$event->stopPropagation();
			} catch (\OutOfBoundsException $e) {
				// Unknown message, ignore
			}
		});
	}

	protected function sendSystemMessage(Room $room, string $message, array $parameters = []) {
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
			new \DateTime(), $message === 'file_shared'
		);
	}
}
