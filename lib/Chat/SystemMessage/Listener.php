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
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var ChatManager */
	protected $chatManager;
	/** @var TalkSession */
	protected $session;
	/** @var string */
	protected $userId;

	public function __construct(EventDispatcherInterface $dispatcher, ChatManager $chatManager, TalkSession $session, $userId) {
		$this->dispatcher = $dispatcher;
		$this->chatManager = $chatManager;
		$this->session = $session;
		$this->userId = $userId;
	}

	public function register() {
		$this->dispatcher->addListener(Room::class . '::postSessionJoinCall', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'joined_call');
		});
		$this->dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'left_call');
		});

		$this->dispatcher->addListener(Room::class . '::createRoom', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'created_conversation');
		});
		$this->dispatcher->addListener(Room::class . '::postSetName', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$this->sendSystemMessage($room, 'renamed_conversation', $event->getArguments());
		});
		$this->dispatcher->addListener(Room::class . '::postSetPassword', function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			if ($event->getArgument('password')) {
				$this->sendSystemMessage($room, 'set_password');
			} else {
				$this->sendSystemMessage($room, 'removed_password');
			}
		});
		$this->dispatcher->addListener(Room::class . '::postChangeType', function(GenericEvent $event) {
			$arguments = $event->getArguments();

			/** @var Room $room */
			$room = $event->getSubject();

			if ($arguments['newType'] === Room::PUBLIC_CALL) {
				$this->sendSystemMessage($room, 'allowed_guests', $event->getArguments());
			}
			if ($arguments['oldType'] === Room::PUBLIC_CALL) {
				$this->sendSystemMessage($room, 'disallowed_guests', $event->getArguments());
			}
		});

		$this->dispatcher->addListener(Room::class . '::postAddUsers', function(GenericEvent $event) {
			$participants = $event->getArgument('users');

			/** @var Room $room */
			$room = $event->getSubject();
			foreach ($participants as $participant) {
				if ($this->userId !== $participant['userId']) {
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
	}

	protected function sendSystemMessage(Room $room, string $message, array $parameters = []) {

		if ($this->userId === null) {
			$actorType = 'guests';
			$sessionId = $this->session->getSessionForRoom($room->getToken());
			$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
		} else {
			$actorType = 'users';
			$actorId = $this->userId;
		}

		$this->chatManager->addSystemMessage(
			$room, $actorType, $actorId,
			json_encode(['message' => $message, 'parameters' => $parameters]),
			new \DateTime()
		);
	}
}
