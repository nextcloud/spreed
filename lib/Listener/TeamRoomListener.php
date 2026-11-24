<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\CircleMemberGenericEvent;
use OCA\Circles\Events\EditingCircleMemberEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ISession;
use Psr\Log\LoggerInterface;

/**
 * Apply member changes in Circles to the team rooms and queue a resync.
 *
 * Changes of single local users are applied right away: removed members
 * can not read or write anymore, added members and new levels are there
 * without waiting. Circles fires the events after storing the change, so
 * the member can be read again. The resync is still queued: it invites
 * members of other servers and email guests, and covers the changes Circles
 * fires no event for, like the former owner being demoted or a new owner
 * being picked after the owner left.
 *
 * @template-implements IEventListener<Event>
 */
class TeamRoomListener implements IEventListener {
	public function __construct(
		protected readonly Manager $manager,
		protected readonly TeamRoomSyncService $syncService,
		protected readonly LoggerInterface $logger,
		protected readonly ISession $session,
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
				$this->asActorOfTeamChange($event, fn () => $this->applyTeamRoomMemberImmediately($room, $event->getMember()));
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
			$this->asActorOfTeamChange($event, function () use ($room, $event): void {
				if ($event instanceof RemovingCircleMemberEvent) {
					$this->removeFromRoomImmediately($room, $event);
				} else {
					$this->applyTeamRoomMemberImmediately($room, $event->getMember());
				}
			});
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
		if ($this->syncService->isChangingTeamFromTalk()) {
			// Talk removes the participant itself, with its actor and reason
			return;
		}

		try {
			$this->syncService->removeFormerTeamMember($room, $event->getMember(), $event->getType() === CircleGenericEvent::LEFT);
		} catch (\Throwable $e) {
			// Never fail the change in the Teams app (and its federation), the resync removes them
			$this->logger->warning('Member of team room ' . $room->getToken() . ' could not be removed yet, will be removed by the next resync', ['exception' => $e]);
		}
	}

	/**
	 * Added members (also joining or accepting the invitation) and level
	 * changes. Only users of this server: the others are invited by the resync.
	 */
	protected function applyTeamRoomMemberImmediately(Room $room, Member $member): void {
		if ($this->syncService->isChangingTeamFromTalk()) {
			// Talk applies the change itself, do not run if call comes from Circles event
			return;
		}

		try {
			$this->syncService->applyTeamMember($room, $member);
		} catch (ForbiddenException) {
			// Banned from the conversation: stays out, also with the resync
		} catch (\Throwable $e) {
			// Never fail the change in the Teams app (and its federation), the resync applies it
			$this->logger->warning('Member of team room ' . $room->getToken() . ' could not be updated yet, will be updated by the next resync', ['exception' => $e]);
		}
	}

	private const ACTOR_SESSION_KEYS = [
		'talk-overwrite-actor-id',
		'talk-overwrite-actor-displayname',
		'talk-overwrite-actor-cli',
	];

	/**
	 * The system messages of the change name the user who made it in the
	 * Teams app, same as CircleMembershipListener does for other rooms. In
	 * web requests Circles applies adding and removing in a separate request
	 * without a user session, there they would show a guest otherwise. A
	 * logged-in user (change in the same request) is used before these values.
	 *
	 * @param callable(): void $change
	 */
	protected function asActorOfTeamChange(CircleMemberGenericEvent $event, callable $change): void {
		$circle = $event->getCircle();
		$member = $event->getMember();
		$actor = $circle->hasInitiator() ? $circle->getInitiator() : ($member->hasInvitedBy() ? $member->getInvitedBy() : null);

		$previous = [];
		foreach (self::ACTOR_SESSION_KEYS as $key) {
			$previous[$key] = $this->session->get($key);
		}

		try {
			if (!is_null($actor) && $this->isLocalUser($actor)) {
				$this->session->set('talk-overwrite-actor-id', $actor->getUserId());
				$this->session->set('talk-overwrite-actor-displayname', $actor->getDisplayName());
			} elseif (!is_null($actor) && $this->isOcc($actor)) {
				$this->session->set('talk-overwrite-actor-cli', 'cli');
			}

			$change();
		} finally {
			foreach ($previous as $key => $value) {
				if (is_null($value)) {
					$this->session->remove($key);
				} else {
					$this->session->set($key, $value);
				}
			}
		}
	}

	/**
	 * Users of other servers can have the same user id as a local user
	 */
	private function isLocalUser(Member|FederatedUser $actor): bool {
		return $actor->getUserType() === Member::TYPE_USER
			&& $actor->isLocal()
			&& !empty($actor->getUserId());
	}

	private function isOcc(Member|FederatedUser $actor): bool {
		return $actor->getUserType() === Member::TYPE_APP
			&& ($actor->getUserId() === 'occ' || $actor->getBasedOn()?->getSource() === Member::APP_OCC);
	}
}
