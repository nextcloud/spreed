<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Notifier;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\ALobbyModifiedEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\CallStartedEvent;
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

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof CallStartedEvent
				&& !$event instanceof CallEndedEvent
				&& !$event instanceof CallEndedForEveryoneEvent
				&& !$event instanceof LobbyModifiedEvent
				&& !$event instanceof RoomModifiedEvent) {
			return;
		}

		if (!in_array($event->getProperty(), [
			ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE,
			ARoomModifiedEvent::PROPERTY_AVATAR,
			ARoomModifiedEvent::PROPERTY_CALL_RECORDING,
			ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS,
			ARoomModifiedEvent::PROPERTY_DESCRIPTION,
			ARoomModifiedEvent::PROPERTY_IN_CALL,
			ARoomModifiedEvent::PROPERTY_LOBBY,
			ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS,
			ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION,
			ARoomModifiedEvent::PROPERTY_NAME,
			ARoomModifiedEvent::PROPERTY_READ_ONLY,
			ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT,
			ARoomModifiedEvent::PROPERTY_SIP_ENABLED,
			ARoomModifiedEvent::PROPERTY_TYPE,
		], true)) {
			return;
		}

		if ($event->getRoom()->isFederatedConversation()) {
			return;
		}

		$participants = $this->participantService->getParticipantsByActorType($event->getRoom(), Attendee::ACTOR_FEDERATED_USERS);
		foreach ($participants as $participant) {
			$cloudId = $this->cloudIdManager->resolveCloudId($participant->getAttendee()->getActorId());

			if ($event instanceof CallStartedEvent) {
				$success = $this->notifyCallStarted($cloudId, $participant, $event);
			} elseif ($event instanceof CallEndedEvent || $event instanceof CallEndedForEveryoneEvent) {
				$success = $this->notifyCallEnded($cloudId, $participant, $event);
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

	private function notifyCallStarted(ICloudId $cloudId, Participant $participant, CallStartedEvent $event) {
		$details = [];
		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT)) {
			$details = [AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT => true];
		}

		return $this->backendNotifier->sendCallStarted(
			$cloudId->getRemote(),
			$participant->getAttendee()->getId(),
			$participant->getAttendee()->getAccessToken(),
			$event->getRoom()->getToken(),
			$event->getProperty(),
			$event->getNewValue(),
			$event->getCallFlag(),
			$details,
		);
	}

	private function notifyCallEnded(ICloudId $cloudId, Participant $participant, CallEndedEvent|CallEndedForEveryoneEvent $event) {
		$details = [];
		if ($event instanceof CallEndedForEveryoneEvent) {
			$details = [AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE => true];
		}

		return $this->backendNotifier->sendCallEnded(
			$cloudId->getRemote(),
			$participant->getAttendee()->getId(),
			$participant->getAttendee()->getAccessToken(),
			$event->getRoom()->getToken(),
			ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE,
			null,
			Participant::FLAG_DISCONNECTED,
			$details,
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
