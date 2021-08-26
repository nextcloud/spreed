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
use Psr\Log\LoggerInterface;

class MembershipService {

	/** @var IAppManager */
	protected $appManager;
	/** @var IGroupManager */
	protected $groupManager;
	/** @var AttendeeMapper */
	protected $attendeeMapper;

	public function __construct(IAppManager $appManager,
								IGroupManager $groupManager,
								AttendeeMapper $attendeeMapper) {
		$this->appManager = $appManager;
		$this->groupManager = $groupManager;
		$this->attendeeMapper = $attendeeMapper;
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
			\OC::$server->get(LoggerInterface::class)->debug('Circles not enabled', ['app' => 'spreed']);
			return $users;
		}

		$circleAttendees = $this->attendeeMapper->getActorsByType($room->getId(), Attendee::ACTOR_CIRCLES);
		$circleIds = array_map(static function (Attendee $attendee) {
			return $attendee->getActorId();
		}, $circleAttendees);

		if (empty($circleIds)) {
			return $users;
		}

		$circlesManager = \OC::$server->get(CirclesManager::class);
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
