<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Activity;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\BeforeCallEndedForEveryoneEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\SessionLeftRoomEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected IManager $activityManager,
		protected IUserSession $userSession,
		protected ChatManager $chatManager,
		protected ParticipantService $participantService,
		protected RoomService $roomService,
		protected RecordingService $recordingService,
		protected LoggerInterface $logger,
		protected ITimeFactory $timeFactory,
	) {
	}

	public function handle(Event $event): void {
		match (get_class($event)) {
			BeforeCallEndedForEveryoneEvent::class => $this->generateCallActivity($event->getRoom(), true, $event->getActor()),
			SessionLeftRoomEvent::class,
			AttendeeRemovedEvent::class => $this->generateCallActivity($event->getRoom()),
			ParticipantModifiedEvent::class => $this->handleParticipantModified($event),
			AttendeesAddedEvent::class => $this->generateInvitationActivity($event->getRoom(), $event->getAttendees()),
		};
	}

	protected function setActive(ParticipantModifiedEvent $event): void {
		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getOldValue() !== Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() === Participant::FLAG_DISCONNECTED) {
			return;
		}

		$participant = $event->getParticipant();
		$this->roomService->setActiveSince(
			$event->getRoom(),
			$this->timeFactory->getDateTime(),
			$participant->getSession() ? $participant->getSession()->getInCall() : Participant::FLAG_DISCONNECTED,
			$participant->getAttendee()->getActorType() !== Attendee::ACTOR_USERS
		);
	}

	protected function handleParticipantModified(ParticipantModifiedEvent $event): void {
		if ($event->getProperty() !== AParticipantModifiedEvent::PROPERTY_IN_CALL) {
			return;
		}

		if ($event->getOldValue() === Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() !== Participant::FLAG_DISCONNECTED) {
			$this->setActive($event);
			return;
		}

		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE)) {
			// The call activity was generated already if the call is ended
			// for everyone
			return;
		}

		$this->generateCallActivity($event->getRoom());
	}

	/**
	 * Call activity: "You attended a call with {user1} and {user2}"
	 *
	 * @param Room $room
	 * @param bool $endForEveryone
	 * @param Participant|null $actor
	 * @return bool True if activity was generated, false otherwise
	 */
	protected function generateCallActivity(Room $room, bool $endForEveryone = false, ?Participant $actor = null): bool {
		$activeSince = $room->getActiveSince();
		if (!$activeSince instanceof \DateTime || (!$endForEveryone && $this->participantService->hasActiveSessionsInCall($room))) {
			return false;
		}

		$duration = $this->timeFactory->getTime() - $activeSince->getTimestamp();
		$userIds = $this->participantService->getParticipantUserIds($room, $activeSince);
		$numGuests = $this->participantService->getGuestCount($room, $activeSince);

		$message = 'call_ended';
		if ($endForEveryone) {
			$message = 'call_ended_everyone';
		} elseif (($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) && \count($userIds) === 1) {
			$message = 'call_missed';
		}

		if (!$this->roomService->resetActiveSince($room)) {
			// Race-condition, the room was already reset.
			return false;
		}

		if ($room->getCallRecording() !== Room::RECORDING_NONE && $room->getCallRecording() !== Room::RECORDING_FAILED) {
			$this->recordingService->stop($room);
		}
		if ($actor instanceof Participant) {
			$actorId = $actor->getAttendee()->getActorId();
			$actorType = $actor->getAttendee()->getActorType();
		} else {
			$actorId = $userIds[0] ?? 'guests-only';
			$actorType = $actorId !== 'guests-only' ? Attendee::ACTOR_USERS : Attendee::ACTOR_GUESTS;
		}
		$this->chatManager->addSystemMessage($room, $actorType, $actorId, json_encode([
			'message' => $message,
			'parameters' => [
				'users' => $userIds,
				'guests' => $numGuests,
				'duration' => $duration,
			],
		]), $this->timeFactory->getDateTime(), false);

		if (empty($userIds)) {
			return false;
		}

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor('')
				->setObject('room', $room->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('call', [
					'room' => $room->getId(),
					'users' => $userIds,
					'guests' => $numGuests,
					'duration' => $duration,
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return false;
		}

		foreach ($userIds as $userId) {
			try {
				$event->setAffectedUser($userId);
				$this->activityManager->publish($event);
			} catch (\Throwable $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}

		return true;
	}

	/**
	 * Invitation activity: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 */
	protected function generateInvitationActivity(Room $room, array $attendees): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		$actorId = $actor->getUID();

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor($actorId)
				->setObject('room', $room->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('invitation', [
					'user' => $actor->getUID(),
					'room' => $room->getId(),
				]);
		} catch (\Throwable $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}

		// We know the new participant is in the room,
		// so skip loading them just to make sure they can read it.
		// Must be overwritten later on for one-to-one chats.
		$roomName = $room->getDisplayName($actorId);

		foreach ($attendees as $attendee) {
			if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
				// No user => no activity
				continue;
			}

			if ($actorId === $attendee->getActorId()) {
				// No activity for self-joining and the creator
				continue;
			}

			try {
				if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
					// Overwrite the room name with the other participant
					$roomName = $room->getDisplayName($attendee->getActorId());
				}
				$event
					->setObject('room', $room->getId(), $roomName)
					->setSubject('invitation', [
						'user' => $actor->getUID(),
						'room' => $room->getId(),
						'name' => $roomName,
					])
					->setAffectedUser($attendee->getActorId());
				$this->activityManager->publish($event);
			} catch (\Throwable $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
	}
}
