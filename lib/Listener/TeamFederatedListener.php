<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Markers of the federated members of team conversations (see
 * TeamRoomSyncService::syncFederatedMembers()):
 * - A federated participant or an email guest declined the invitation or
 *   left: they stay a member of the team (Talk can not act in the team in
 *   their name), so the sync must not invite them again.
 * - The conversation was deleted: its markers are deleted as well.
 *
 * @template-implements IEventListener<Event>
 */
class TeamFederatedListener implements IEventListener {
	public function __construct(
		protected readonly TeamRoomSyncService $syncService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomDeletedEvent) {
			if ($event->getRoom()->getObjectType() === Room::OBJECT_TYPE_TEAM) {
				$this->syncService->deleteFederatedMarkers($event->getRoom());
			}
			return;
		}

		if (!$event instanceof AttendeeRemovedEvent
			|| $event->getReason() !== AAttendeeRemovedEvent::REASON_LEFT
			|| $event->getRoom()->getObjectType() !== Room::OBJECT_TYPE_TEAM) {
			return;
		}

		if ($event->getAttendee()->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			$this->syncService->rememberFederatedLeave($event->getRoom(), $event->getAttendee()->getActorId());
		} elseif ($event->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS) {
			$this->syncService->rememberEmailLeave($event->getRoom(), (string)$event->getAttendee()->getInvitedCloudId());
		}
	}
}
