<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class ReferenceInvalidationListener implements IEventListener {

	public function __construct(
		protected IReferenceManager $referenceManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AttendeesAddedEvent
			|| $event instanceof AttendeesRemovedEvent
			|| $event instanceof LobbyModifiedEvent
			|| $event instanceof RoomDeletedEvent
			|| $event instanceof RoomModifiedEvent) {
			$this->referenceManager->invalidateCache($event->getRoom()->getToken());
		}
	}
}
