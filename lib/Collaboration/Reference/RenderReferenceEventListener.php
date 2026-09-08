<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Loads the Talk reference widget bundle whenever a page might render
 * references (e.g. link previews or Smart Picker widgets), so a Talk
 * conversation link can be rendered with a richer, Talk-specific widget
 * instead of the generic link preview.
 *
 * @template-implements IEventListener<Event>
 */
class RenderReferenceEventListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof RenderReferenceEvent)) {
			return;
		}

		Util::addScript(Application::APP_ID, 'talk-reference');
	}
}
