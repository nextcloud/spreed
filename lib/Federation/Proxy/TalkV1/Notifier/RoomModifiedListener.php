<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\AActiveSinceModifiedEvent;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\ActiveSinceModifiedEvent;
use OCA\Talk\Events\ALobbyModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
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
class RoomModifiedListener implements IEventListener {
	public function __construct(
		protected BackendNotifier $backendNotifier,
		protected ParticipantService $participantService,
		protected ICloudIdManager $cloudIdManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ActiveSinceModifiedEvent
				&& !$event instanceof LobbyModifiedEvent
				&& !$event instanceof RoomModifiedEvent) {
			return;
		}

		if (!in_array($event->getProperty(), [
			ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE,
			ARoomModifiedEvent::PROPERTY_AVATAR,
			ARoomModifiedEvent::PROPERTY_DESCRIPTION,
			ARoomModifiedEvent::PROPERTY_IN_CALL,
			ARoomModifiedEvent::PROPERTY_LOBBY,
			ARoomModifiedEvent::PROPERTY_NAME,
			ARoomModifiedEvent::PROPERTY_READ_ONLY,
			ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT,
			ARoomModifiedEvent::PROPERTY_TYPE,
		], true)) {
			return;
		}

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		foreach ($participants as $participant) {
			$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());

			if ($event instanceof AActiveSinceModifiedEvent) {
				$success = $this->notifyActiveSinceModified($cloudId, $participant, $event);
			} elseif ($event instanceof ALobbyModifiedEvent) {
				$success = $this->notifyLobbyModified($cloudId, $participant, $event);
			} else {
				$success = $this->notifyRoomModified($cloudId, $participant, $event);
			}

			if ($success === null) {
				$this->participantService->removeAttendee($event->getRoom(), $participant, AAttendeeRemovedEvent::REASON_LEFT);
			}
		}
	}

	private function notifyActiveSinceModified(ICloudId $cloudId, Participant $participant, AActiveSinceModifiedEvent $event) {
		return $this->backendNotifier->sendRoomModifiedActiveSinceUpdate(
			$cloudId->getRemote(),
			$participant->getAttendee()->getId(),
			$participant->getAttendee()->getAccessToken(),
			$event->getRoom()->getToken(),
			$event->getProperty(),
			$event->getNewValue(),
			$event->getOldValue(),
			$event->getCallFlag(),
		);
	}

	private function notifyLobbyModified(ICloudId $cloudId, Participant $participant, ALobbyModifiedEvent $event) {
		return $this->backendNotifier->sendRoomModifiedLobbyUpdate(
			$cloudId->getRemote(),
			$participant->getAttendee()->getId(),
			$participant->getAttendee()->getAccessToken(),
			$event->getRoom()->getToken(),
			$event->getProperty(),
			$event->getNewValue(),
			$event->getOldValue(),
			$event->getLobbyTimer(),
			$event->isTimerReached(),
		);
	}

	private function notifyRoomModified(ICloudId $cloudId, Participant $participant, ARoomModifiedEvent $event) {
		return $this->backendNotifier->sendRoomModifiedUpdate(
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
