<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
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

		Util::addStyle(Application::APP_ID, 'talk-icons');
		Util::addScript(Application::APP_ID, 'talk-public-share-auth-sidebar');
		Util::addStyle(Application::APP_ID, 'talk-public-share-auth-sidebar');

		$this->publishInitialStateForGuest();
	}
}
