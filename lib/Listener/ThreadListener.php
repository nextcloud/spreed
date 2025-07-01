<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ThreadService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class ThreadListener implements IEventListener {
	public function __construct(
		protected readonly ThreadService $threadService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AttendeesRemovedEvent) {
			$attendeeIds = array_map(static fn (Attendee $attendee): int => $attendee->getId(), $event->getAttendees());
			$this->threadService->removeThreadAttendeesByAttendeeIds($attendeeIds);
		}
	}
}
