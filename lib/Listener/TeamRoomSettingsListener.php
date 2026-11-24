<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\EditingCircleEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\TeamRoomSettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Keep the settings of team rooms and their teams in sync, see TeamRoomSettingsService
 *
 * - EditingCircleEvent: name, description and settings (needs Circles with
 *   the event in CircleConfig, older versions are caught up by the resync)
 * - RoomModifiedEvent: name, description and listable changed in Talk
 * - BeforeUserJoinedRoomEvent: a user joins the open team room, so joins the team
 *
 * @template-implements IEventListener<Event>
 */
class TeamRoomSettingsListener implements IEventListener {
	public function __construct(
		protected readonly Manager $manager,
		protected readonly TeamRoomSettingsService $settingsService,
		protected readonly CirclesAdapter $circlesAdapter,
		protected readonly ParticipantService $participantService,
		protected readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof EditingCircleEvent) {
			$this->teamEdited($event);
		} elseif ($event instanceof RoomModifiedEvent) {
			$this->roomModified($event);
		} elseif ($event instanceof BeforeUserJoinedRoomEvent) {
			$this->userJoining($event);
		}
	}

	protected function teamEdited(EditingCircleEvent $event): void {
		if ($this->settingsService->isChangingTeamFromTalk()) {
			// Talk applies the result to the room itself, see TeamRoomSettingsService::renameTeamRoom()
			return;
		}

		$room = $this->manager->getTeamRoom($event->getCircle()->getSingleId());
		if (is_null($room)) {
			return;
		}

		try {
			// Circles already saved the change, so the team can be read again
			$this->settingsService->applyTeamToRoom($room);
		} catch (\Exception $e) {
			$this->logger->warning('Settings of team ' . $room->getObjectId() . ' could not be applied to its room', ['exception' => $e]);
		}
	}

	protected function roomModified(RoomModifiedEvent $event): void {
		$room = $event->getRoom();
		if (!$room->isTeamManaged()) {
			return;
		}

		if (in_array($event->getProperty(), [
			ARoomModifiedEvent::PROPERTY_NAME,
			ARoomModifiedEvent::PROPERTY_DESCRIPTION,
			ARoomModifiedEvent::PROPERTY_LISTABLE,
		], true)) {
			$this->settingsService->applyRoomToTeam($room, $event->getProperty());
		}
	}

	/**
	 * Joining the open team room = joining the team first: the user only gets
	 * in when they are a member afterwards, otherwise the next resync would
	 * remove them again
	 */
	protected function userJoining(BeforeUserJoinedRoomEvent $event): void {
		$room = $event->getRoom();
		if (!$room->isTeamManaged()) {
			return;
		}

		$userId = $event->getUser()->getUID();
		try {
			$this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $userId);
			// Already a participant, e.g. opening the room again
			return;
		} catch (ParticipantNotFoundException) {
		}

		if (!$this->manager->isRoomListableByUser($room, $userId)) {
			// Not joining through the listing: ParticipantService refuses it
			return;
		}

		$teamId = $room->getObjectId();
		try {
			if (!is_null($this->circlesAdapter->getDirectMember($teamId, $userId))
				|| $this->circlesAdapter->joinTeam($teamId, $userId)) {
				// Member (e.g. missed sync) or joined now: the resync gives the role of the level
				return;
			}
			$this->logger->info('User ' . $userId . ' can not join team room ' . $room->getToken() . ', the team only received a request to join');
		} catch (\Exception $e) {
			$this->logger->info('User ' . $userId . ' can not join team room ' . $room->getToken() . ', joining the team failed', ['exception' => $e]);
		}
		$event->setCancelJoin(true);
	}
}
