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
use OCA\Spreed\Capabilities;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\SystemMessage\Listener;
use OCA\Spreed\Config;
use OCA\Spreed\GuestManager;
use OCA\Spreed\HookListener;
use OCA\Spreed\Notification\Notifier;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\Search\Provider;
use OCA\Spreed\Settings\Personal;
use OCA\Spreed\Signaling\BackendNotifier;
use OCA\Spreed\Signaling\Messages;
use OCP\AppFramework\App;
use OCP\IServerContainer;
use OCP\Settings\IManager;
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

		$server->getSearch()->registerProvider(Provider::class, ['apps' => ['spreed']]);

		$this->registerNotifier($server);
		$this->getContainer()->registerCapability(Capabilities::class);

		$dispatcher = $server->getEventDispatcher();
		$config = $server->query(Config::class);
		$servers = $config->getSignalingServers();
		if (empty($servers)) {
			$this->registerInternalSignalingHooks($dispatcher);
		} else {
			$this->registerSignalingBackendHooks($dispatcher);
		}
		$this->registerCallActivityHooks($dispatcher);
		$this->registerRoomActivityHooks($dispatcher);
		$this->registerRoomInvitationHook($dispatcher);
		$this->registerCallNotificationHook($dispatcher);
		$this->registerChatHooks($dispatcher);
		$this->registerRoomHooks($dispatcher);
		$this->registerClientLinks($server);

		/** @var Listener $systemMessageListener */
		$systemMessageListener = $this->getContainer()->query(Listener::class);
		$systemMessageListener->register();

		/** @var \OCA\Spreed\Chat\Listener $chatListener */
		$chatListener = $this->getContainer()->query(\OCA\Spreed\Chat\Listener::class);
		$chatListener->register();

		/** @var \OCA\Spreed\PublicShareAuth\Listener $shareAuthListener */
		$shareAuthListener = $this->getContainer()->query(\OCA\Spreed\PublicShareAuth\Listener::class);
		$shareAuthListener->register();

		/** @var \OCA\Spreed\PublicShareAuth\TemplateLoader $shareAuthTemplateLoader */
		$shareAuthTemplateLoader = $this->getContainer()->query(\OCA\Spreed\PublicShareAuth\TemplateLoader::class);
		$shareAuthTemplateLoader->register();
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

	protected function registerClientLinks(IServerContainer $server) {
		if ($server->getAppManager()->isEnabledForUser('firstrunwizard')) {
			/** @var IManager $settingManager */
			$settingManager = $server->getSettingsManager();
			$settingManager->registerSetting('personal', Personal::class);
		}
	}

	protected function registerInternalSignalingHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var Messages $messages */
			$messages = $this->getContainer()->query(Messages::class);
			$messages->addMessageForAllParticipants($room, 'refresh-participant-list');
		};

		$dispatcher->addListener(Room::class . '::postJoinRoom', $listener);
		$dispatcher->addListener(Room::class . '::postJoinRoomGuest', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener);
		$dispatcher->addListener(GuestManager::class . '::updateName', $listener);
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
		$dispatcher->addListener(Room::class . '::postSetParticipantTypeBySession', function(GenericEvent $event) {
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
		$dispatcher->addListener(Room::class . '::postRemoveBySession', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$participant = $event->getArgument('participant');
			$notifier->roomSessionsRemoved($room, [$participant->getSessionId()]);
		});
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$flags = $event->getArgument('flags');
			$notifier->roomInCallChanged($room, $flags, [$sessionId]);
		});
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->roomInCallChanged($room, Participant::FLAG_DISCONNECTED, [$sessionId]);
		});
		$dispatcher->addListener(Room::class . '::postRemoveBySession', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$participant = $event->getArgument('participant');
			$notifier->participantsModified($room, [$participant->getSessionId()]);
		});
		$dispatcher->addListener(Room::class . '::postCleanGuests', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$notifier->participantsModified($room);
		});
		$dispatcher->addListener(GuestManager::class . '::updateName', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

			$room = $event->getSubject();
			$sessionId = $event->getArgument('sessionId');
			$notifier->participantsModified($room, [$sessionId]);
		});
		$dispatcher->addListener(ChatManager::class . '::sendMessage', function(GenericEvent $event) {
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

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
			/** @var BackendNotifier $notifier */
			$notifier = $this->getBackendNotifier();

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

	protected function registerRoomActivityHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			$room->setLastActivity(new \DateTime());
		};

		$dispatcher->addListener(ChatManager::class . '::sendMessage', $listener);
		$dispatcher->addListener(ChatManager::class . '::sendSystemMessage', $listener);
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
			$chatManager->deleteMessages($room);
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);
	}

	protected function registerRoomHooks(EventDispatcherInterface $dispatcher) {
		$listener = function(GenericEvent $event)  {
			/** @var Room $room */
			$room = $event->getSubject();

			if ($event->getArgument('selfJoin')) {
				/** @var \OCA\Spreed\Share\RoomShareProvider $roomShareProvider */
				$roomShareProvider = $this->getContainer()->query(\OCA\Spreed\Share\RoomShareProvider::class);
				$roomShareProvider->deleteInRoom($room->getToken(), $event->getArgument('userId'));
			}
		};
		$dispatcher->addListener(Room::class . '::postUserDisconnectRoom', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var \OCA\Spreed\Share\RoomShareProvider $roomShareProvider */
			$roomShareProvider = $this->getContainer()->query(\OCA\Spreed\Share\RoomShareProvider::class);
			$roomShareProvider->deleteInRoom($room->getToken(), $event->getArgument('user')->getUID());
		};
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var \OCA\Spreed\Share\RoomShareProvider $roomShareProvider */
			$roomShareProvider = $this->getContainer()->query(\OCA\Spreed\Share\RoomShareProvider::class);
			$roomShareProvider->deleteInRoom($room->getToken());
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);
	}
}
