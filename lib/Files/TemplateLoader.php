<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
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
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly Config $talkConfig,
		private readonly IConfig $serverConfig,
		private readonly IAppConfig $appConfig,
		private readonly IUserSession $userSession,
		private readonly IRequest $request,
	) {
	}

	/**
	 * Loads the Talk UI in the sidebar of the Files app.
	 *
	 * This method should be called when handling the LoadSidebar event of the
	 * Files app.
	 *
	 * @param Event $event
	 */
	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}

		if (!$this->appConfig->getAppValueBool(Config::CONVERSATIONS_FILES)) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			return;
		}

		Util::addStyle(Application::APP_ID, 'talk-icons');
		if (!str_starts_with($this->request->getPathInfo(), '/apps/maps')) {
			Util::addScript(Application::APP_ID, 'talk-files-sidebar');
			// Styles are loaded asynchronously, initially no CSS file is bundled
			// Util::addStyle(Application::APP_ID, 'talk-files-sidebar');
		}

		$this->initialState->provideInitialState(
			'signaling_mode',
			$this->talkConfig->getSignalingMode()
		);
	}
}
