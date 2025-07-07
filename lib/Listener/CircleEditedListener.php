<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\CircleEditedEvent;
use OCA\Circles\Events\EditingCircleEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class CircleEditedListener implements IEventListener {

	public function __construct(
		private readonly ParticipantService $participantService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof EditingCircleEvent && !$event instanceof CircleEditedEvent) {
			// Unrelated
			return;
		}

		$displayName = $event->getCircle()->getDisplayName();
		if ($event instanceof EditingCircleEvent) {
			// In the before event we need to cheat to get the name
			if ($event->getFederatedEvent()?->getData()?->hasKey('name')) {
				$displayName = $event->getFederatedEvent()->getData()->g('name');
			}
		}

		$this->participantService->updateDisplayNameForActor(
			Attendee::ACTOR_CIRCLES,
			$event->getCircle()->getSingleId(),
			$displayName,
		);
	}
}
