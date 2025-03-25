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

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof ParticipantModifiedEvent) {
			return;
		}

		$participant = $event->getParticipant();
		if ($participant->getAttendee()->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			return;
		}

		if (!in_array($event->getProperty(), [
			AParticipantModifiedEvent::PROPERTY_PERMISSIONS,
			AParticipantModifiedEvent::PROPERTY_RESEND_CALL,
		], true)) {
			return;
		}

		// For modifying participants we only notify the affected participant's server
		$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());
		$success = $this->notifyParticipantModified($cloudId, $participant, $event);

		if ($success === null) {
			$this->participantService->removeAttendee($event->getRoom(), $participant, AAttendeeRemovedEvent::REASON_LEFT);
		}
	}

	private function notifyParticipantModified(ICloudId $cloudId, Participant $participant, AParticipantModifiedEvent $event): ?bool {
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
