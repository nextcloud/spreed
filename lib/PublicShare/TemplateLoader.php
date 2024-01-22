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

namespace OCA\Talk\PublicShare;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\TInitialState;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\FileInfo;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Helper class to extend the "publicshare" template from the server.
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
	 * Load the "Talk sidebar" UI in the public share page for the given share.
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		if ($event->getScope() !== null) {
			// If the event has a scope, it's not the default share page, but e.g. authentication
			return;
		}

		if ($this->serverConfig->getAppValue('spreed', 'conversations_files', '1') !== '1' ||
			$this->serverConfig->getAppValue('spreed', 'conversations_files_public_shares', '1') !== '1') {
			return;
		}

		$share = $event->getShare();
		if ($share->getNodeType() !== FileInfo::TYPE_FILE) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'icons');
		Util::addStyle(Application::APP_ID, 'publicshare');
		Util::addScript(Application::APP_ID, 'talk-public-share-sidebar');

		$this->publishInitialStateForGuest();
	}
}
