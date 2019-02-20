<?php
declare(strict_types=1);
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

use OCA\Spreed\Activity\Listener as ActivityListener;
use OCA\Spreed\Capabilities;
use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\Command\Listener as CommandListener;
use OCA\Spreed\Chat\Parser\Listener as ParserListener;
use OCA\Spreed\Chat\SystemMessage\Listener as SystemMessageListener;
use OCA\Spreed\Config;
use OCA\Spreed\Files\Listener as FilesListener;
use OCA\Spreed\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Spreed\Listener;
use OCA\Spreed\Notification\Listener as NotificationListener;
use OCA\Spreed\Notification\Notifier;
use OCA\Spreed\PublicShareAuth\Listener as PublicShareAuthListener;
use OCA\Spreed\PublicShareAuth\TemplateLoader as PublicShareAuthTemplateLoader;
use OCA\Spreed\Room;
use OCA\Spreed\Settings\Personal;
use OCA\Spreed\Share\RoomShareProvider;
use OCA\Spreed\Signaling\Listener as SignalingListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IServerContainer;
use OCP\Settings\IManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	public function __construct(array $urlParams = []) {
		parent::__construct('spreed', $urlParams);
	}

	public function register(): void {
		$server = $this->getContainer()->getServer();

		$this->extendDefaultContentSecurityPolicy();
		$this->registerNotifier($server);
		$this->getContainer()->registerCapability(Capabilities::class);

		$dispatcher = $server->getEventDispatcher();
		Listener::register();
		ActivityListener::register($dispatcher);
		NotificationListener::register($dispatcher);
		SystemMessageListener::register($dispatcher);
		ParserListener::register($dispatcher);
		PublicShareAuthListener::register($dispatcher);
		PublicShareAuthTemplateLoader::register($dispatcher);
		FilesListener::register($dispatcher);
		FilesTemplateLoader::register($dispatcher);
		RoomShareProvider::register($dispatcher);
		SignalingListener::register($dispatcher);
		CommandListener::register($dispatcher);

		$this->registerRoomActivityHooks($dispatcher);
		$this->registerChatHooks($dispatcher);
		$this->registerClientLinks($server);
	}

	protected function registerNotifier(IServerContainer $server): void {
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

	protected function registerClientLinks(IServerContainer $server): void {
		if ($server->getAppManager()->isEnabledForUser('firstrunwizard')) {
			/** @var IManager $settingManager */
			$settingManager = $server->getSettingsManager();
			$settingManager->registerSetting('personal', Personal::class);
		}
	}

	protected function registerRoomActivityHooks(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();
			/** @var ITimeFactory $timeFactory */
			$timeFactory = $this->getContainer()->query(ITimeFactory::class);
			$room->setLastActivity($timeFactory->getDateTime());
		};

		$dispatcher->addListener(ChatManager::class . '::sendMessage', $listener);
		$dispatcher->addListener(ChatManager::class . '::sendSystemMessage', $listener);
	}

	protected function registerChatHooks(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var ChatManager $chatManager */
			$chatManager = $this->getContainer()->query(ChatManager::class);
			$chatManager->deleteMessages($room);
		};
		$dispatcher->addListener(Room::class . '::postDeleteRoom', $listener);
	}

	protected function extendDefaultContentSecurityPolicy(): void {
		/** @var Config $config */
		$config = $this->getContainer()->query(Config::class);

		$csp = new ContentSecurityPolicy();
		foreach ($config->getAllServerUrlsForCSP() as $server) {
			$csp->addAllowedConnectDomain($server);
		}
		$cspManager = $this->getContainer()->getServer()->getContentSecurityPolicyManager();
		$cspManager->addDefaultPolicy($csp);
	}
}
