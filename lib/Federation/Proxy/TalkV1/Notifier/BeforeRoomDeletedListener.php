<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeRoomDeletedListener implements IEventListener {
	public function __construct(
		protected BackendNotifier $backendNotifier,
		protected ParticipantService $participantService,
		protected ICloudIdManager $cloudIdManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BeforeRoomDeletedEvent) {
			return;
		}

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		foreach ($participants as $participant) {
			$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());

			$this->backendNotifier->sendRemoteUnShare(
				$cloudId->getRemote(),
				$participant->getAttendee()->getId(),
				$participant->getAttendee()->getAccessToken(),
			);
		}
	}
}
