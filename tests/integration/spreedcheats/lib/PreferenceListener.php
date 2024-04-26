<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats;

use OCP\Config\BeforePreferenceDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class PreferenceListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof BeforePreferenceDeletedEvent) {
			return;
		}

		if ($event->getAppId() === 'spreed') {
			$event->setValid(true);
		}
	}
}
