<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Events\RoomDeletedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected ChatManager $chatManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomDeletedEvent) {
			$this->chatManager->deleteMessages($event->getRoom());
		}
	}
}
