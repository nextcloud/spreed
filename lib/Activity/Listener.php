<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Activity;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Events\ARoomEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\Activity\Exceptions\InvalidValueException;
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

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof ARoomEvent && $event->getRoom()->isFederatedConversation()) {
			return;
		}

		match (get_class($event)) {
			CallEndedEvent::class,
			CallEndedForEveryoneEvent::class => $this->generateCallActivity($event),
			AttendeesAddedEvent::class => $this->generateInvitationActivity($event->getRoom(), $event->getAttendees()),
		};
	}

	/**
	 * Call activity: "You attended a call with {user1} and {user2}"
	 */
	protected function generateCallActivity(ACallEndedEvent $event): void {
		$room = $event->getRoom();
		$actor = $event->getActor();
		$activeSince = $event->getOldValue();

		$duration = $this->timeFactory->getTime() - $activeSince->getTimestamp();
		$userIds = $this->participantService->getParticipantUserIds($room, $activeSince);
		$cloudIds = $this->participantService->getParticipantActorIdsByActorType($room, [Attendee::ACTOR_FEDERATED_USERS], $activeSince);
		$numGuests = $this->participantService->getActorsCountByType($room, Attendee::ACTOR_GUESTS, $activeSince->getTimestamp());
		$numGuests += $this->participantService->getActorsCountByType($room, Attendee::ACTOR_EMAILS, $activeSince->getTimestamp());

		$message = 'call_ended';
		if (($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) && \count($userIds) === 1) {
			$message = 'call_missed';
		} elseif ($event instanceof CallEndedForEveryoneEvent) {
			$message = 'call_ended_everyone';
		}

		if ($actor instanceof Participant) {
			$actorId = $actor->getAttendee()->getActorId();
			$actorType = $actor->getAttendee()->getActorType();
		} else {
			$actorType = Attendee::ACTOR_GUESTS;
			$actorId = Attendee::ACTOR_ID_SYSTEM;
		}
		$this->chatManager->addSystemMessage($room, $actor, $actorType, $actorId, json_encode([
			'message' => $message,
			'parameters' => [
				'users' => $userIds,
				'cloudIds' => $cloudIds,
				'guests' => $numGuests,
				'duration' => $duration,
			],
		]), $this->timeFactory->getDateTime(), false);

		if (empty($userIds)) {
			return;
		}

		$activity = $this->activityManager->generateEvent();
		try {
			$activity->setApp('spreed')
				->setType('spreed')
				->setAuthor('')
				->setObject('room', $room->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('call', [
					'room' => $room->getId(),
					'users' => $userIds,
					'cloudIds' => $cloudIds,
					'guests' => $numGuests,
					'duration' => $duration,
				]);
		} catch (InvalidValueException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}

		foreach ($userIds as $userId) {
			try {
				$activity->setAffectedUser($userId);
				$this->activityManager->publish($activity);
			} catch (\Throwable $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
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
