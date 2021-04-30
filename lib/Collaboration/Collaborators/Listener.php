<?php

declare(strict_types=1);
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

namespace OCA\Talk\Collaboration\Collaborators;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Collaboration\AutoComplete\AutoCompleteEvent;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\IUserManager;

class Listener {

	/** @var Manager */
	protected $manager;
	/** @var IUserManager */
	protected $userManager;
	/** @var Config */
	protected $config;
	/** @var string[] */
	protected $allowedGroupIds = [];
	/** @var string */
	protected $roomToken;
	/** @var Room */
	protected $room;

	public function __construct(Manager $manager,
								IUserManager $userManager,
								Config $config) {
		$this->manager = $manager;
		$this->userManager = $userManager;
		$this->config = $config;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(IManager::class . '::filterResults', static function (AutoCompleteEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->get(self::class);

			if ($event->getItemType() !== 'call') {
				return;
			}

			$event->setResults($listener->filterUsersAndGroupsWithoutTalk($event->getResults()));

			$event->setResults($listener->filterBridgeBot($event->getResults()));
			if ($event->getItemId() !== 'new') {
				$event->setResults($listener->filterExistingParticipants($event->getItemId(), $event->getResults()));
			}
		});
	}

	protected function filterUsersAndGroupsWithoutTalk(array $results): array {
		$this->allowedGroupIds = $this->config->getAllowedTalkGroupIds();
		if (empty($this->allowedGroupIds)) {
			return $results;
		}

		if (!empty($results['groups'])) {
			$results['groups'] = array_filter($results['groups'], [$this, 'filterBlockedGroupResult']);
		}
		if (!empty($results['exact']['groups'])) {
			$results['exact']['groups'] = array_filter($results['exact']['groups'], [$this, 'filterBlockedGroupResult']);
		}

		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterBlockedUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterBlockedUserResult']);
		}

		return $results;
	}

	protected function filterBlockedUserResult(array $result): bool {
		$user = $this->userManager->get($result['value']['shareWith']);
		return $user instanceof IUser && !$this->config->isDisabledForUser($user);
	}

	protected function filterBlockedGroupResult(array $result): bool {
		return \in_array($result['value']['shareWith'], $this->allowedGroupIds, true);
	}

	protected function filterBridgeBot(array $results): array {
		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterBridgeBotUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterBridgeBotUserResult']);
		}

		return $results;
	}

	protected function filterExistingParticipants(string $token, array $results): array {
		try {
			$this->room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			return $results;
		}

		if (!empty($results['groups'])) {
			$results['groups'] = array_filter($results['groups'], [$this, 'filterParticipantGroupResult']);
		}
		if (!empty($results['exact']['groups'])) {
			$results['exact']['groups'] = array_filter($results['exact']['groups'], [$this, 'filterParticipantGroupResult']);
		}

		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterParticipantUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterParticipantUserResult']);
		}

		return $results;
	}

	protected function filterBridgeBotUserResult(array $result): bool {
		return $result['value']['shareWith'] !== MatterbridgeManager::BRIDGE_BOT_USERID;
	}

	protected function filterParticipantUserResult(array $result): bool {
		$userId = $result['value']['shareWith'];

		try {
			$participant = $this->room->getParticipant($userId, false);
			if ($participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
				// do list self-joined users so they can be added as permanent participants by moderators
				return true;
			}
			return false;
		} catch (ParticipantNotFoundException $e) {
			return true;
		}
	}

	protected function filterParticipantGroupResult(array $result): bool {
		$groupId = $result['value']['shareWith'];

		try {
			$this->room->getParticipantByActor(Attendee::ACTOR_GROUPS, $groupId);
			return false;
		} catch (ParticipantNotFoundException $e) {
			return true;
		}
	}
}
