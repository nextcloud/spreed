<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Maps;

use OCA\Talk\Config;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class MapsPluginLoader implements IEventListener {

	public function __construct(
		protected IRequest $request,
		protected Config $talkConfig,
		protected IUserSession $userSession,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn()) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			return;
		}

		if (str_starts_with($this->request->getPathInfo(), '/apps/maps')) {
			Util::addScript('spreed', 'talk-collections');
			Util::addScript('spreed', 'talk-maps');
			Util::addStyle('spreed', 'talk-maps');
		}
	}
}
