<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Search;

use OCA\Talk\Config;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class UnifiedSearchFilterPlugin implements IEventListener {

	public function __construct(
		protected Config $talkConfig,
		protected IUserSession $userSession,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$currentUser = $this->userSession->getUser();
		if ($currentUser === null || $this->talkConfig->isDisabledForUser($currentUser)) {
			return;
		}

		Util::addScript('spreed', 'talk-search');
	}
}
