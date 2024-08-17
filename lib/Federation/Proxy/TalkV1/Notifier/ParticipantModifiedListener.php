<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;

/**
 * @template-implements IEventListener<Event>
 */
class ParticipantModifiedListener implements IEventListener {
	public function __construct(
		protected BackendNotifier $backendNotifier,
		protected ParticipantService $participantService,
		protected ICloudIdManager $cloudIdManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ParticipantModifiedEvent) {
			return;
		}

		if (!in_array($event->getProperty(), [
			AParticipantModifiedEvent::PROPERTY_PERMISSIONS,
		], true)) {
			return;
		}

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		foreach ($participants as $participant) {
			$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());

			$success = $this->notifyParticipantModified($cloudId, $participant, $event);

			if ($success === null) {
				$this->participantService->removeAttendee($event->getRoom(), $participant, AAttendeeRemovedEvent::REASON_LEFT);
			}
		}
	}

	private function notifyParticipantModified(ICloudId $cloudId, Participant $participant, AParticipantModifiedEvent $event) {
		return $this->backendNotifier->sendParticipantModifiedUpdate(
			$cloudId->getRemote(),
			$participant->getAttendee()->getId(),
			$participant->getAttendee()->getAccessToken(),
			$event->getRoom()->getToken(),
			$event->getProperty(),
			$event->getNewValue(),
			$event->getOldValue(),
		);
	}
}
