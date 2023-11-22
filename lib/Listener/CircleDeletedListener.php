<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
