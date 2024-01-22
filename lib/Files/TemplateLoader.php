<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Talk\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\TInitialState;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Helper class to add the Talk UI to the sidebar of the Files app.
 *
 * @template-implements IEventListener<Event>
 */
class TemplateLoader implements IEventListener {
	use TInitialState;

	private IAppManager $appManager;
	private IRootFolder $rootFolder;
	private IUserSession $userSession;

	public function __construct(
		IInitialState $initialState,
		ICacheFactory $memcacheFactory,
		Config $talkConfig,
		IConfig $serverConfig,
		IAppManager $appManager,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IGroupManager $groupManager,
		LoggerInterface $logger,
	) {
		$this->initialState = $initialState;
		$this->memcacheFactory = $memcacheFactory;
		$this->talkConfig = $talkConfig;
		$this->serverConfig = $serverConfig;
		$this->appManager = $appManager;
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
	}


	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addServiceListener(LoadSidebar::class, self::class);
	}

	/**
	 * Loads the Talk UI in the sidebar of the Files app.
	 *
	 * This method should be called when handling the LoadSidebar event of the
	 * Files app.
	 *
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}

		if ($this->serverConfig->getAppValue('spreed', 'conversations_files', '1') !== '1') {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'icons');
		if (strpos(\OC::$server->getRequest()->getPathInfo(), '/apps/maps') !== 0) {
			Util::addScript(Application::APP_ID, 'talk-files-sidebar');
		}

		if ($user instanceof IUser) {
			$this->publishInitialStateForUser($user, $this->rootFolder, $this->appManager);
		} else {
			$this->publishInitialStateForGuest();
		}
	}
}
