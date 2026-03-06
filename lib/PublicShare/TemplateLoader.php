<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\PublicShare;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\Util;

/**
 * Helper class to extend the "publicshare" template from the server.
 *
 * The additional scripts modify the page in the browser to inject the Talk UI as needed.
 *
 * @template-implements IEventListener<Event>
 */
class TemplateLoader implements IEventListener {
	public function __construct(
		protected IInitialState $initialState,
		protected Config $talkConfig,
		protected IConfig $serverConfig,
	) {
	}

	/**
	 * Load the "Talk sidebar" UI in the public share page for the given share.
	 * @param Event $event
	 */
	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		if ($event->getScope() !== null) {
			// If the event has a scope, it's not the default share page, but e.g. authentication
			return;
		}

		if ($this->serverConfig->getAppValue('spreed', 'conversations_files', '1') !== '1'
			|| $this->serverConfig->getAppValue('spreed', 'conversations_files_public_shares', '1') !== '1') {
			return;
		}

		$share = $event->getShare();
		if ($share->getNodeType() !== FileInfo::TYPE_FILE) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'talk-icons');
		Util::addScript(Application::APP_ID, 'talk-public-share-sidebar');
		Util::addStyle(Application::APP_ID, 'talk-public-share-sidebar');

		$this->initialState->provideInitialState(
			'signaling_mode',
			$this->talkConfig->getSignalingMode()
		);
	}
}
