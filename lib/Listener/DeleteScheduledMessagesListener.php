<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ScheduledMessageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class DeleteScheduledMessagesListener implements IEventListener {
	public function __construct(
		protected readonly ScheduledMessageService $scheduledMessageService,
		protected readonly ParticipantService $participantService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomDeletedEvent) {
			$participants = $this->participantService->getParticipantsForRoom($event->getRoom());
			foreach ($participants as $participant) {
				$this->scheduledMessageService->deleteByActor($participant->getAttendee()->getActorType(), $participant->getAttendee()->getActorId());
			}
		}
	}
}
