<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Events\MembershipsCreatedEvent;
use OCA\Circles\Events\MembershipsRemovedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class CircleMembershipListener implements IEventListener {

	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var Manager */
	private $manager;
	/** @var ParticipantService */
	private $participantService;

	public function __construct(IUserManager $userManager,
								IGroupManager $groupManager,
								Manager $manager,
								ParticipantService $participantService) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->manager = $manager;
		$this->participantService = $participantService;
	}

	public function handle(Event $event): void {
		if ($event instanceof AddingCircleMemberEvent) {
			$this->addingCircleMemberEvent($event);
		}

		if ($event instanceof RemovingCircleMemberEvent) {
			$this->removeFormerMemberFromRooms($event->getCircle(), $event->getMember());
		}
	}

	protected function addingCircleMemberEvent(AddingCircleMemberEvent $event): void {
		$roomsForTargetCircle = $this->manager->getRoomsForActor(Attendee::ACTOR_CIRCLES, $event->getCircle()->getSingleId());

		if (empty($roomsForTargetCircle)) {
			// The circle is not in any room => bye!
			return;
		}

		// These members are "memberships" in circles which link to entities such as users, groups or circles
		if ($event->getType() === CircleGenericEvent::MULTIPLE) {
			$newMembers = $event->getMembers();
		} else {
			$newMembers = [$event->getMember()];
		}

		foreach ($newMembers as $newMember) {
			// Get the base circle of the membership
			$basedOnCircle = $newMember->getBasedOn();
			// Get all (nested) memberships in the added $newMember as a flat list
			$userMembers = $basedOnCircle->getInheritedMembers();

			foreach ($userMembers as $userMember) {
				$this->addNewMemberToRooms($roomsForTargetCircle, $userMember);
			}
		}
	}

	protected function addNewMemberToRooms(array $rooms, Member $member): void {
		if ($member->getUserType() !== Member::TYPE_USER || $member->getUserId() === '') {
			// Not a user?
			return;
		}

		$user = $this->userManager->get($member->getUserId());
		if (!$user instanceof IUser) {
			return;
		}

		foreach ($rooms as $room) {
			try {
				$participant = $room->getParticipant($member->getUserId());
				if ($participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
					$this->participantService->updateParticipantType($room, $participant, Participant::USER);
				}
			} catch (ParticipantNotFoundException $e) {
				$this->participantService->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $member->getUserId(),
					'displayName' => $user->getDisplayName(),
				]]);
			}
		}
	}

	protected function removeFormerMemberFromRooms(Circle $circle, Member $member): void {
		if ($member->getUserType() !== Member::TYPE_USER || $member->getUserId() === '') {
			// Not a user?
			return;
		}

		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_CIRCLES, $circle->getSingleId());
		if (empty($rooms)) {
			return;
		}

		// FIXME we now need to check user groups and circles?
	}
}
