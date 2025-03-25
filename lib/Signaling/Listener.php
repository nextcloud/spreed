<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling;

use OCA\Talk\Config;
use OCA\Talk\Events\AMessageSentEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\ASystemMessageSentEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\BeforeAttendeeRemovedEvent;
use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Events\BeforeRoomSyncedEvent;
use OCA\Talk\Events\BeforeSessionLeftRoomEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\GuestJoinedRoomEvent;
use OCA\Talk\Events\GuestsCleanedUpEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomExtendedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\RoomSyncedEvent;
use OCA\Talk\Events\SessionLeftRoomEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Events\UserJoinedRoomEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public const EXTERNAL_SIGNALING_PROPERTIES = [
		ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_MODE,
		ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_STATUS,
		ARoomModifiedEvent::PROPERTY_CALL_RECORDING,
		ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS,
		ARoomModifiedEvent::PROPERTY_DESCRIPTION,
		ARoomModifiedEvent::PROPERTY_LISTABLE,
		ARoomModifiedEvent::PROPERTY_LOBBY,
		ARoomModifiedEvent::PROPERTY_NAME,
		ARoomModifiedEvent::PROPERTY_PASSWORD,
		ARoomModifiedEvent::PROPERTY_READ_ONLY,
		ARoomModifiedEvent::PROPERTY_SIP_ENABLED,
		ARoomModifiedEvent::PROPERTY_TYPE,
	];

	protected bool $pauseRoomModifiedListener = false;

	public function __construct(
		protected Config $talkConfig,
		protected Messages $internalSignaling,
		protected BackendNotifier $externalSignaling,
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected SessionService $sessionService,
		protected ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_INTERNAL) {
			$this->handleInternalSignaling($event);
		} else {
			$this->handleExternalSignaling($event);
		}
	}

	protected function handleInternalSignaling(Event $event): void {
		match (get_class($event)) {
			BeforeSessionLeftRoomEvent::class,
			BeforeAttendeeRemovedEvent::class,
			GuestJoinedRoomEvent::class,
			BeforeRoomDeletedEvent::class,
			UserJoinedRoomEvent::class => $this->refreshParticipantList($event->getRoom()),
			ParticipantModifiedEvent::class => $this->refreshParticipantListParticipantModified($event), // in_call, name, permissions
			RoomModifiedEvent::class => $this->refreshParticipantListRoomModified($event), // *_permissions
			default => null, // Ignoring events subscribed by the external signaling
		};
	}

	protected function refreshParticipantList(Room $room): void {
		$this->internalSignaling->addMessageForAllParticipants($room, 'refresh-participant-list');
	}

	protected function refreshParticipantListParticipantModified(ParticipantModifiedEvent $event): void {
		if (!in_array($event->getProperty(), [
			AParticipantModifiedEvent::PROPERTY_IN_CALL,
			AParticipantModifiedEvent::PROPERTY_NAME,
			AParticipantModifiedEvent::PROPERTY_PERMISSIONS,
		], true)) {
			return;
		}

		$this->refreshParticipantList($event->getRoom());
	}

	protected function refreshParticipantListRoomModified(RoomModifiedEvent $event): void {
		if (!in_array($event->getProperty(), [
			ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS,
		], true)) {
			return;
		}

		$this->refreshParticipantList($event->getRoom());
	}

	protected function handleExternalSignaling(Event $event): void {
		match (get_class($event)) {
			RoomModifiedEvent::class,
			LobbyModifiedEvent::class => $this->notifyRoomModified($event),
			RoomExtendedEvent::class => $this->notifyRoomExtended($event),
			BeforeRoomSyncedEvent::class => $this->pauseRoomModifiedListener(),
			RoomSyncedEvent::class => $this->notifyRoomSynced($event),
			BeforeRoomDeletedEvent::class => $this->notifyBeforeRoomDeleted($event),
			CallEndedForEveryoneEvent::class => $this->notifyCallEndedForEveryone($event),
			GuestsCleanedUpEvent::class => $this->notifyGuestsCleanedUp($event),
			AttendeesAddedEvent::class => $this->notifyAttendeesAdded($event),
			AttendeeRemovedEvent::class => $this->notifyAttendeeRemoved($event),
			AttendeesRemovedEvent::class => $this->notifyAttendeesRemoved($event),
			ParticipantModifiedEvent::class => $this->notifyParticipantModified($event),
			SessionLeftRoomEvent::class => $this->notifySessionLeftRoom($event),
			ChatMessageSentEvent::class,
			SystemMessageSentEvent::class,
			SystemMessagesMultipleSentEvent::class => $this->notifyMessageSent($event),
			default => null, // Ignoring events subscribed by the internal signaling
		};
	}

	protected function pauseRoomModifiedListener(): void {
		$this->pauseRoomModifiedListener = true;
	}

	protected function notifyRoomModified(ARoomModifiedEvent $event): void {
		if (!in_array($event->getProperty(), self::EXTERNAL_SIGNALING_PROPERTIES, true)) {
			return;
		}

		if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS) {
			$this->notifyRoomPermissionsModified($event);
			// The room permission itself does not need a signaling message anymore
			return;
		}

		if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_CALL_RECORDING) {
			$this->notifyRoomRecordingModified($event);
		}
		if ($event->getProperty() === ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_STATUS) {
			$this->notifyBreakoutRoomStatusModified($event);
		}
		$this->externalSignaling->roomModified($event->getRoom());
	}

	protected function notifyRoomSynced(RoomSyncedEvent $event): void {
		$this->pauseRoomModifiedListener = false;
		if (empty(array_intersect($event->getProperties(), self::EXTERNAL_SIGNALING_PROPERTIES))) {
			return;
		}

		if (in_array(ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS, $event->getProperties(), true)) {
			$this->notifyRoomPermissionsModified($event);
		}

		if (in_array(ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $event->getProperties(), true)) {
			$this->notifyRoomRecordingModified($event);
		}

		if (in_array(ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_STATUS, $event->getProperties(), true)) {
			$this->notifyBreakoutRoomStatusModified($event);
		}

		$this->externalSignaling->roomModified($event->getRoom());
	}

	protected function notifyRoomRecordingModified(ARoomEvent $event): void {
		$room = $event->getRoom();
		$message = [
			'type' => 'recording',
			'recording' => [
				'status' => $room->getCallRecording(),
			],
		];

		$this->externalSignaling->sendRoomMessage($room, $message);
	}

	protected function notifyCallEndedForEveryone(CallEndedForEveryoneEvent $event): void {
		$sessionIds = $event->getSessionIds();

		if (empty($sessionIds)) {
			return;
		}

		$this->externalSignaling->roomInCallChanged(
			$event->getRoom(),
			$event->getCallFlag(),
			[],
			true
		);
	}

	protected function notifyBeforeRoomDeleted(BeforeRoomDeletedEvent $event): void {
		$room = $event->getRoom();
		$this->externalSignaling->roomDeleted($room, $this->participantService->getParticipantUserIds($room));
	}

	protected function notifyGuestsCleanedUp(GuestsCleanedUpEvent $event): void {
		// TODO: The list of removed session ids should be passed through the event
		// so the signaling server can optimize forwarding the message.
		$sessionIds = [];
		$this->externalSignaling->participantsModified($event->getRoom(), $sessionIds);
	}

	protected function notifyParticipantModified(AParticipantModifiedEvent $event): void {
		if ($event->getProperty() === AParticipantModifiedEvent::PROPERTY_TYPE) {
			// TODO remove handler with "roomModified" in favour of handler with
			// "participantsModified" once the clients no longer expect a
			// "roomModified" message for participant type changes.
			$this->externalSignaling->roomModified($event->getRoom());
		}

		if ($event->getProperty() === AParticipantModifiedEvent::PROPERTY_NAME) {
			$this->notifyParticipantNameModified($event);
		}

		if ($event->getProperty() === AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			$this->notifyParticipantInCallModified($event);
		}

		if ($event->getProperty() === AParticipantModifiedEvent::PROPERTY_TYPE
			|| $event->getProperty() === AParticipantModifiedEvent::PROPERTY_PERMISSIONS) {
			$this->notifyParticipantTypeOrPermissionsModified($event);
		}
	}

	protected function notifyParticipantNameModified(AParticipantModifiedEvent $event): void {
		$sessionIds = [];
		$sessions = $this->sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		if (!empty($sessionIds)) {
			$this->externalSignaling->participantsModified($event->getRoom(), $sessionIds);
		}
	}

	protected function notifyParticipantTypeOrPermissionsModified(AParticipantModifiedEvent $event): void {
		$sessionIds = [];

		// If the participant is not active in the room the "participants"
		// request will be sent anyway, although with an empty "changed"
		// property.
		$sessions = $this->sessionService->getAllSessionsForAttendee($event->getParticipant()->getAttendee());
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		$this->externalSignaling->participantsModified($event->getRoom(), $sessionIds);
	}

	protected function notifyRoomPermissionsModified(ARoomEvent $event): void {
		$sessionIds = [];

		// Setting the room permissions resets the permissions of all
		// participants, even those with custom attendee permissions.

		// FIXME This approach does not scale, as the update message for all
		// the sessions in a conversation can exceed the allowed size of the
		// request in conversations with a large number of participants.
		// However, note that a single message with the general permissions
		// to be set on all participants can not be sent either, as the
		// general permissions could be overriden by custom attendee
		// permissions in specific participants.
		$participants = $this->participantService->getSessionsAndParticipantsForRoom($event->getRoom());
		foreach ($participants as $participant) {
			$session = $participant->getSession();
			if ($session) {
				$sessionIds[] = $session->getSessionId();
			}
		}

		$this->externalSignaling->participantsModified($event->getRoom(), $sessionIds);
	}

	protected function notifyAttendeesAdded(AttendeesAddedEvent $event): void {
		$this->externalSignaling->roomInvited($event->getRoom(), $event->getAttendees());
	}

	protected function notifyAttendeesRemoved(AttendeesRemovedEvent $event): void {
		$this->externalSignaling->roomsDisinvited($event->getRoom(), $event->getAttendees());
	}

	protected function notifyAttendeeRemoved(AttendeeRemovedEvent $event): void {
		$sessionIds = [];

		$sessions = $event->getSessions();
		foreach ($sessions as $session) {
			$sessionIds[] = $session->getSessionId();
		}

		if (!empty($sessionIds)) {
			$this->externalSignaling->roomSessionsRemoved($event->getRoom(), $sessionIds);
		}
	}

	protected function notifySessionLeftRoom(SessionLeftRoomEvent $event): void {
		$sessionIds = [];
		if ($event->getParticipant()->getSession()) {
			// If a previous duplicated session is being removed it must be
			// notified to the external signaling server. Otherwise, only for
			// guests disconnecting is "leaving" and therefor should trigger a
			// disinvite.
			$attendeeParticipantType = $event->getParticipant()->getAttendee()->getParticipantType();
			if ($event->isRejoining()
				|| $attendeeParticipantType === Participant::GUEST
				|| $attendeeParticipantType === Participant::GUEST_MODERATOR) {
				$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
				$this->externalSignaling->roomSessionsRemoved($event->getRoom(), $sessionIds);
			}
		}
	}

	protected function notifyParticipantInCallModified(AParticipantModifiedEvent $event): void {
		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE)) {
			// If everyone is disconnected, we will not do O(n) requests.
			// Instead, the listener of CallEndedForEveryoneEvent
			// will send all sessions to the HPB with 1 request.
			return;
		}

		$sessionIds = [];
		if ($event->getParticipant()->getSession()) {
			$sessionIds[] = $event->getParticipant()->getSession()->getSessionId();
		}

		if (!empty($sessionIds)) {
			$this->externalSignaling->roomInCallChanged(
				$event->getRoom(),
				$event->getNewValue(),
				$sessionIds
			);
		}
	}

	protected function notifyBreakoutRoomStatusModified(ARoomEvent $event): void {
		$room = $event->getRoom();
		if ($room->getBreakoutRoomStatus() === BreakoutRoom::STATUS_STARTED) {
			$this->notifyBreakoutRoomStarted($room);
		} else {
			$this->notifyBreakoutRoomStopped($room);
		}
	}

	protected function notifyBreakoutRoomStarted(Room $room): void {
		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken(), true);

		$parentRoomParticipants = $this->participantService->getSessionsAndParticipantsForRoom($room);

		foreach ($breakoutRooms as $breakoutRoom) {
			$sessionIds = [];

			$breakoutRoomParticipants = $this->participantService->getParticipantsForRoom($breakoutRoom);
			foreach ($breakoutRoomParticipants as $breakoutRoomParticipant) {
				foreach ($this->getSessionIdsForNonModeratorsMatchingParticipant($breakoutRoomParticipant, $parentRoomParticipants) as $sessionId) {
					$sessionIds[] = $sessionId;
				}
			}

			if (!empty($sessionIds)) {
				$this->externalSignaling->switchToRoom($room, $breakoutRoom->getToken(), $sessionIds);
			}
		}
	}

	protected function notifyRoomExtended(RoomExtendedEvent $event): void {
		$room = $event->getRoom();

		if ($room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			return;
		}

		$timeout = $this->timeFactory->getTime() - Session::SESSION_TIMEOUT;
		$participants = $this->participantService->getParticipantsInCall($room, $timeout);

		$sessionIds = [];
		foreach ($participants as $participant) {
			if ($participant->getSession() instanceof Session) {
				$sessionIds[] = $participant->getSession()->getSessionId();
			}
		}

		$newRoom = $event->getNewRoom();
		$this->externalSignaling->switchToRoom($room, $newRoom->getToken(), $sessionIds);
	}

	/**
	 * @param Participant $targetParticipant
	 * @param Participant[] $participants
	 * @return string[]
	 */
	protected function getSessionIdsForNonModeratorsMatchingParticipant(Participant $targetParticipant, array $participants): array {
		$sessionIds = [];

		foreach ($participants as $participant) {
			if ($participant->getAttendee()->getActorType() === $targetParticipant->getAttendee()->getActorType()
				&& $participant->getAttendee()->getActorId() === $targetParticipant->getAttendee()->getActorId()
				&& !$participant->hasModeratorPermissions()) {
				$session = $participant->getSession();
				if ($session) {
					$sessionIds[] = $session->getSessionId();
				}
			}
		}

		return $sessionIds;
	}

	protected function notifyBreakoutRoomStopped(Room $room): void {
		$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken(), true);

		foreach ($breakoutRooms as $breakoutRoom) {
			$sessionIds = [];

			$participants = $this->participantService->getSessionsAndParticipantsForRoom($breakoutRoom);
			foreach ($participants as $participant) {
				$session = $participant->getSession();
				if ($session) {
					$sessionIds[] = $session->getSessionId();
				}
			}

			if (!empty($sessionIds)) {
				$this->externalSignaling->switchToRoom($breakoutRoom, $room->getToken(), $sessionIds);
			}
		}
	}

	protected function notifyMessageSent(AMessageSentEvent $event): void {
		if ($event instanceof ASystemMessageSentEvent && $event->shouldSkipLastActivityUpdate()) {
			return;
		}

		$room = $event->getRoom();
		$message = [
			'type' => 'chat',
			'chat' => [
				'refresh' => true,
			],
		];
		$this->externalSignaling->sendRoomMessage($room, $message);
	}
}
