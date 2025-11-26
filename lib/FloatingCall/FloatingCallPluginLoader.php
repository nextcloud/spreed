<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\FloatingCall;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class FloatingCallPluginLoader implements IEventListener {

	public function __construct(
		private IRequest $request,
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

		if (
			!str_starts_with($this->request->getPathInfo(), '/apps/spreed')
			&& !str_starts_with($this->request->getPathInfo(), '/call')
		) {
			Util::addScript('spreed', 'talk-floating-call');
			Util::addStyle('spreed', 'talk-floating-call');
		}
	}
}
