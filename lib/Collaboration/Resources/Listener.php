<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Collaboration\Resources;

use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\EmailInvitationSentEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\ResourceException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected IManager $resourceManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AttendeesAddedEvent
			|| $event instanceof AttendeesRemovedEvent
			|| $event instanceof RoomDeletedEvent
			|| $event instanceof EmailInvitationSentEvent
			|| ($event instanceof RoomModifiedEvent
				&& $event->getProperty() === ARoomModifiedEvent::PROPERTY_TYPE)) {
			try {
				$resource = $this->resourceManager->getResourceForUser('room', $event->getRoom()->getToken(), null);
			} catch (ResourceException) {
				return;
			}
			$this->resourceManager->invalidateAccessCacheForResource($resource);
		}
	}
}
