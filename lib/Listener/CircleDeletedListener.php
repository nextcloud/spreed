<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class CircleDeletedListener implements IEventListener {

	public function __construct(
		private Manager $manager,
		private ParticipantService $participantService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof CircleDestroyedEvent)) {
			// Unrelated
			return;
		}

		$circleId = $event->getCircle()->getSingleId();

		// Remove the circle itself from being a participant
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_CIRCLES, $circleId);
		foreach ($rooms as $room) {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_CIRCLES, $circleId);
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		}
	}
}
