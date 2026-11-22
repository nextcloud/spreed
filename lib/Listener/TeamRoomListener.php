<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\EditingCircleMemberEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Queue a resync of the team rooms affected by a member change in Circles.
 *
 * Removed members are removed from the conversation right away, so they
 * can not read or write until the resync runs. Everything else is only a
 * trigger: ResyncTeamRoom reads members and levels from Circles once
 * the change is done. That also covers the changes Circles fires no event
 * for, like the former owner being demoted or a new owner being picked
 * after the owner left.
 *
 * @template-implements IEventListener<Event>
 */
class TeamRoomListener implements IEventListener {
	public function __construct(
		protected readonly Manager $manager,
		protected readonly TeamRoomSyncService $syncService,
		protected readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof RoomCreatedEvent) {
			// Only the creator and the team admins are added when creating:
			// all other members right after, by the resync
			if ($event->getRoom()->isTeamManaged()) {
				$this->syncService->queueResync($event->getRoom());
			}
			return;
		}

		if ($event instanceof EditingCircleMemberEvent) {
			$room = $event->getType() === CircleGenericEvent::LEVEL ? $this->manager->getTeamRoom($event->getCircle()->getSingleId()) : null;
			if (!is_null($room)) {
				$this->syncService->queueResync($room);
			}
			return;
		}

		if ($event instanceof AddingCircleMemberEvent || $event instanceof RemovingCircleMemberEvent) {
			// Only direct members are participants, parent teams are not affected
			$room = $this->manager->getTeamRoom($event->getCircle()->getSingleId());
			if (is_null($room)) {
				return;
			}
			if ($event instanceof RemovingCircleMemberEvent) {
				$this->removeFromRoomImmediately($room, $event);
			}
			$this->syncService->queueResync($room);
		}
	}

	/**
	 * Circles dispatches the event after the member was deleted, on every
	 * server that applies the change. Only the server of the conversation
	 * has a team conversation, on the others there is nothing to do.
	 */
	protected function removeFromRoomImmediately(Room $room, RemovingCircleMemberEvent $event): void {
		if ($event->getFederatedEvent()->getData()->gBool('destroyCircle')) {
			// The last member left and the team is deleted: the conversation is
			// archived for the deleted team instead of being emptied
			return;
		}

		try {
			$this->syncService->removeFormerTeamMember($room, $event->getMember(), $event->getType() === CircleGenericEvent::LEFT);
		} catch (\Throwable $e) {
			// Never fail the change in the Teams app (and its federation), the resync removes them
			$this->logger->warning('Member of team room ' . $room->getToken() . ' could not be removed yet, will be removed by the next resync', ['exception' => $e]);
		}
	}
}
