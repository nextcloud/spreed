<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat;

use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\ScheduledMessageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		private readonly ChatManager $chatManager,
		private readonly ScheduledMessageService $scheduledMessageService,
		private readonly ReminderService $reminderService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomDeletedEvent) {
			$this->chatManager->deleteMessages($event->getRoom());
			$this->scheduledMessageService->deleteMessagesByRoom($event->getRoom());
		}
		if ($event instanceof AttendeesRemovedEvent) {
			foreach ($event->getAttendees() as $attendee) {
				if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
					$this->reminderService->deleteAllRemindersForUser(
						$attendee->getActorId(),
						$event->getRoom()->getToken(),
					);
				}
			}
		}
	}
}
