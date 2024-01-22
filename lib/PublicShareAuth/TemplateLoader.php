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

namespace OCA\Talk\PublicShareAuth;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\TInitialState;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Helper class to extend the "publicshareauth" template from the server.
 *
 * The additional scripts modify the page in the browser to inject the Talk UI as needed.
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
		IGroupManager $groupManager,
		LoggerInterface $logger,
	) {
		$this->initialState = $initialState;
		$this->talkConfig = $talkConfig;
		$this->memcacheFactory = $memcacheFactory;
		$this->serverConfig = $serverConfig;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
	}

	/**
	 * Load the "Video verification" UI in the public share auth page.
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		if ($event->getScope() !== BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH) {
			// If the scope is not the authentication page we don't load this part of the Talk UI
			return;
		}

		// Check if "Video verification" option was set
		$share = $event->getShare();
		if (!$share->getSendPasswordByTalk()) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'icons');
		Util::addStyle(Application::APP_ID, 'publicshareauth');
		Util::addScript(Application::APP_ID, 'talk-public-share-auth-sidebar');

		$this->publishInitialStateForGuest();
	}
}
