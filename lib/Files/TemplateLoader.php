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
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

/**
 * Helper class to add the Talk UI to the sidebar of the Files app.
 *
 * @template-implements IEventListener<Event>
 */
class TemplateLoader implements IEventListener {
	use TInitialState;

	public function __construct(
		IInitialState $initialState,
		ICacheFactory $memcacheFactory,
		Config $talkConfig,
		IConfig $serverConfig,
		private IAppManager $appManager,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		IGroupManager $groupManager,
		protected IRequest $request,
	) {
		$this->initialState = $initialState;
		$this->memcacheFactory = $memcacheFactory;
		$this->talkConfig = $talkConfig;
		$this->serverConfig = $serverConfig;
		$this->groupManager = $groupManager;
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
		if (!str_starts_with($this->request->getPathInfo(), '/apps/maps')) {
			Util::addScript(Application::APP_ID, 'talk-files-sidebar');
		}

		if ($user instanceof IUser) {
			$this->publishInitialStateForUser($user, $this->rootFolder, $this->appManager);
		} else {
			$this->publishInitialStateForGuest();
		}
	}
}
