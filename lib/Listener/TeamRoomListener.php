<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberEditedEvent;
use OCA\Circles\Events\EditingCircleMemberEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
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
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Apply member changes in Circles to the team rooms and queue a resync.
 *
 * Changes of single members are applied right away: removed members can
 * not read or write anymore, added users and new levels are there without
 * waiting. Circles fires these events after storing the change, so the
 * member can be read again. Members of other servers and email guests are
 * invited once Circles finished the change (CircleMemberAddedEvent,
 * CircleMemberEditedEvent). The resync is still queued: it retries failed
 * invitations and covers the changes Circles fires no event for, like the
 * former owner being demoted or a new owner being picked after the owner left.
 *
 * @template-implements IEventListener<Event>
 */
class TeamRoomListener implements IEventListener {
	public function __construct(
		protected readonly Manager $manager,
		protected readonly TeamRoomSyncService $syncService,
		protected readonly LoggerInterface $logger,
		protected readonly ISession $session,
		protected readonly IUserSession $userSession,
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
				if ($this->isLocalUser($event->getMember())) {
					$this->asActorOfTeamChange($event->getCircle(), $event->getMember(), fn () => $this->applyTeamRoomMemberImmediately($room, $event->getMember()));
				}
				$this->syncService->queueResync($room);
			}
			return;
		}

		if ($event instanceof CircleMemberAddedEvent || $event instanceof CircleMemberEditedEvent) {
			$this->applyAfterTeamChange($event);
			return;
		}

		if ($event instanceof CircleDestroyedEvent) {
			$this->archiveForDeletedTeam($event);
			return;
		}

		if ($event instanceof AddingCircleMemberEvent || $event instanceof RemovingCircleMemberEvent) {
			// Only direct members are participants, parent teams are not affected
			$room = $this->manager->getTeamRoom($event->getCircle()->getSingleId());
			if (is_null($room)) {
				return;
			}
			$this->asActorOfTeamChange($event->getCircle(), $event->getMember(), function () use ($room, $event): void {
				if ($event instanceof RemovingCircleMemberEvent) {
					$this->removeFromRoomImmediately($room, $event);
				} elseif ($this->isLocalUser($event->getMember())) {
					// Members of other servers and email guests: see applyAfterTeamChange()
					$this->applyTeamRoomMemberImmediately($room, $event->getMember());
				}
			});
			$this->syncService->queueResync($room);
		}
	}

	/**
	 * Members of other servers and email guests are invited, which means a
	 * request to their server or a mail. That is done once Circles finished
	 * the change on all servers (the result events), never while Circles is
	 * still applying it: a slow or failing invitation must not keep the
	 * change in the Teams app from being finished. Users of this server were
	 * applied right away already.
	 *
	 * Only in requests of a user and on the command line: Circles often
	 * finishes the change in its own request to this server (loopback, without
	 * a user). Links and signatures of this server are built from the address
	 * of the request, there that is the loopback address: other servers refuse
	 * the invitation ("Invalid signature") and invitation mails would link to
	 * it. The resync in the background invites them instead, there the address
	 * is overwrite.cli.url (which has to be the public URL of this server).
	 */
	protected function applyAfterTeamChange(CircleMemberAddedEvent|CircleMemberEditedEvent $event): void {
		if ($event instanceof CircleMemberEditedEvent && $event->getType() !== CircleGenericEvent::LEVEL) {
			return;
		}
		$member = $event->getMember();
		if (is_null($member) || $this->isLocalUser($member)) {
			return;
		}
		$room = $this->manager->getTeamRoom($event->getCircle()->getSingleId());
		if (is_null($room)) {
			return;
		}
		if (is_null($this->userSession->getUser()) && !$this->isCli()) {
			$this->syncService->queueResync($room);
			return;
		}
		$this->asActorOfTeamChange($event->getCircle(), $member, fn () => $this->applyTeamRoomMemberImmediately($room, $member));
	}

	protected function isCli(): bool {
		return PHP_SAPI === 'cli';
	}

	/**
	 * Safeguard: The team room is archived and blocked rather than being deleted:
	 * Administrators can convert it, delete it, or attach it to a team singleId as
	 * the room objectId and with objectType room to a team again.
	 * Circles deletes teams, in its own request (loopback), the system messages
	 * name the user who deleted the team in the Teams app as Actor.
	 * FIXME: when team deletion is stable, deleting a team can also delete a team
	 * FIXME: room, but for now archiving it to avoid potential data loss on failures
	 */
	protected function archiveForDeletedTeam(CircleDestroyedEvent $event): void {
		$room = $this->manager->getTeamRoom($event->getCircle()->getSingleId());
		if (is_null($room)) {
			return;
		}

		try {
			$this->asActorOfTeamChange($event->getCircle(), null, fn () => $this->syncService->archiveForDeletedTeam($room));
		} catch (\Throwable $e) {
			// Never fail the deletion in the Teams app, the resync archives it (the team is not found)
			$this->logger->warning('Team room ' . $room->getToken() . ' of the deleted team could not be archived yet, will be archived by the next resync', ['exception' => $e]);
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
	 * changes, see TeamRoomSyncService::applyTeamMember()
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
	protected function asActorOfTeamChange(Circle $circle, ?Member $member, callable $change): void {
		$actor = $circle->hasInitiator() ? $circle->getInitiator() : ($member?->hasInvitedBy() ? $member->getInvitedBy() : null);

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
