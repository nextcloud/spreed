<?php
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

namespace OCA\Spreed\AppInfo;

use OCA\Spreed\Activity\Hooks;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\HookListener;
use OCA\Spreed\Notification\Notifier;
use OCA\Spreed\Room;
use OCA\Spreed\Signaling\BackendNotifier;
use OCA\Spreed\Signaling\Messages;
use OCP\AppFramework\App;
use OCP\IServerContainer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	public function __construct(array $urlParams = []) {
		parent::__construct('spreed', $urlParams);
	}

	public function register() {
		$server = $this->getContainer()->getServer();

		$server->getUserManager()->listen('\OC\User', 'postDelete', function ($user) {
			/** @var HookListener $listener */
			$listener = \OC::$server->query(HookListener::class);
			$listener->deleteUser($user);
		});

		$this->registerNotifier($server);

		$dispatcher = $server->getEventDispatcher();
		$this->registerInternalSignalingHooks($dispatcher);
		$this->registerSignalingBackendHooks($dispatcher);
		$this->registerCallActivityHooks($dispatcher);
		$this->registerRoomInvitationHook($dispatcher);
		$this->registerCallNotificationHook($dispatcher);
		$this->registerChatHooks($dispatcher);
	}

	protected function registerNotifier(IServerContainer $server) {
		$manager = $server->getNotificationManager();
		$manager->registerNotifier(function() use ($server) {
			return $server->query(Notifier::class);
		}, function() use ($server) {
			$l = $server->getL10N('spreed');

			return [
				'id' => 'spreed',
				'name' => $l->t('Talk'),
			];
		});
	}

	protected function registerInternalSignalingHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Messages $messages */
			$messages = $this->getContainer()->query(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');
		};

		$dispatcher->addListener(Room::class . '::postUserEnterRoom', $listener);
		$dispatcher->addListener(Room::class . '::postGuestEnterRoom', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);
	}

	protected function getBackendNotifier() {
		return $this->getContainer()->query(BackendNotifier::class);
	}

	protected function registerSignalingBackendHooks(EventDispatcherInterface $dispatcher) {
		$dispatcher->addListener(Room::class . '::postAddUsers', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$participants= $event->getArgument('users');
			$notifier->roomInvited($room, $participants);
		});
		$dispatcher->addListener(Room::class . '::postSetName', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postSetParticipantType', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			// The type of a participant has changed, notify all participants
			// so they can update the room properties.
			$notifier->roomModified($room);
		});
		$dispatcher->addListener(Room::class . '::postDeleteRoom', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$participants = $event->getArgument('participants');
			$notifier->roomDeleted($room, $participants);
		});
		$dispatcher->addListener(Room::class . '::postRemoveUser', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$user = $event->getArgument('user');
			$notifier->roomsDisinvited($room, [$user->getUID()]);
		});
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->roomInCallChanged($room, true, [$sessionId]);
		});
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->roomInCallChanged($room, false, [$sessionId]);
		});
	}

	protected function registerCallActivityHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Hooks $hooks */
			$hooks = $this->getContainer()->query(Hooks::class);
			$hooks->setActive($room);
		};
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Hooks $hooks */
			$hooks = $this->getContainer()->query(Hooks::class);
			$hooks->generateCallActivity($room);
		};
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);
	}

	protected function registerRoomInvitationHook(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Hooks $activityHooks */
			$activityHooks = $this->getContainer()->query(Hooks::class);
			$activityHooks->generateInvitationActivity($room, $event->getArgument('users'));

			/** @var \OCA\Spreed\Notification\Hooks $notificationHooks */
			$notificationHooks = $this->getContainer()->query(\OCA\Spreed\Notification\Hooks::class);
			$notificationHooks->generateInvitation($room, $event->getArgument('users'));
		};
		$dispatcher->addListener(Room::class . '::postAddUsers', $listener);
	}

	protected function registerCallNotificationHook(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var \OCA\Spreed\Notification\Hooks $notificationHooks */
			$notificationHooks = $this->getContainer()->query(\OCA\Spreed\Notification\Hooks::class);
			$notificationHooks->generateCallNotifications($room);
		};
		$dispatcher->addListener(Room::class . '::preSessionJoinCall', $listener);
	}

	protected function registerChatHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var ChatManager $chatManager */
			$chatManager = $this->getContainer()->query(ChatManager::class);
			$chatManager->deleteMessages((string) $room->getId());
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);
	}
}
