<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Membership;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Server;
use Psr\Log\LoggerInterface;

class MembershipService {

	public function __construct(
		protected IAppManager $appManager,
		protected IGroupManager $groupManager,
		protected AttendeeMapper $attendeeMapper,
	) {
	}

	/**
	 * @param Room $room
	 * @param IUser[] $users
	 * @return IUser[]
	 */
	public function getUsersWithoutOtherMemberships(Room $room, array $users): array {
		$users = $this->filterUsersWithOtherGroupMemberships($room, $users);
		$users = $this->filterUsersWithOtherCircleMemberships($room, $users);
		return $users;
	}

	/**
	 * @param Room $room
	 * @param IUser[] $users
	 * @return IUser[]
	 */
	protected function filterUsersWithOtherGroupMemberships(Room $room, array $users): array {
		$groupAttendees = $this->attendeeMapper->getActorsByType($room->getId(), Attendee::ACTOR_GROUPS);
		$groupIds = array_map(static function (Attendee $attendee) {
			return $attendee->getActorId();
		}, $groupAttendees);

		if (empty($groupIds)) {
			return $users;
		}

		return array_filter($users, function (IUser $user) use ($groupIds) {
			// Only delete users when the user is not member via another group
			$userGroups = $this->groupManager->getUserGroupIds($user);
			return empty(array_intersect($userGroups, $groupIds));
		});
	}

	/**
	 * @param Room $room
	 * @param IUser[] $users
	 * @return IUser[]
	 */
	protected function filterUsersWithOtherCircleMemberships(Room $room, array $users): array {
		if (empty($users)) {
			return $users;
		}
		$anyUser = reset($users);
		if (!$this->appManager->isEnabledForUser('circles', $anyUser)) {
			Server::get(LoggerInterface::class)->debug('Circles not enabled', ['app' => 'spreed']);
			return $users;
		}

		$circleAttendees = $this->attendeeMapper->getActorsByType($room->getId(), Attendee::ACTOR_CIRCLES);
		$circleIds = array_map(static function (Attendee $attendee) {
			return $attendee->getActorId();
		}, $circleAttendees);

		if (empty($circleIds)) {
			return $users;
		}

		$circlesManager = Server::get(CirclesManager::class);
		return array_filter($users, static function (IUser $user) use ($circlesManager, $circleIds) {
			// Only delete users when the user is not member via another circle
			$federatedUser = $circlesManager->getFederatedUser($user->getUID(), Member::TYPE_USER);
			$memberships = $federatedUser->getMemberships();
			$userCircles = array_map(static function (Membership $membership) {
				return $membership->getCircleId();
			}, $memberships);
			return empty(array_intersect($userCircles, $circleIds));
		});
	}
}
