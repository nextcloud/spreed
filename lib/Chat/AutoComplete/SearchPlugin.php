<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Chat\AutoComplete;

use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Files\Util;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IL10N;
use OCP\IUserManager;

class SearchPlugin implements ISearchPlugin {

	protected ?Room $room = null;

	public function __construct(
		protected IUserManager $userManager,
		protected GuestManager $guestManager,
		protected TalkSession $talkSession,
		protected ParticipantService $participantService,
		protected Util $util,
		protected ?string $userId,
		protected IL10N $l,
		protected Authenticator $federationAuthenticator,
	) {
	}

	public function setContext(array $context): void {
		$this->room = $context['room'];
	}

	/**
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @param ISearchResult $searchResult
	 * @return bool whether the plugin has more results
	 * @since 13.0.0
	 */
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		if ($this->room->getObjectType() === 'file') {
			$usersWithFileAccess = $this->util->getUsersWithAccessFile($this->room->getObjectId());
			if (!empty($usersWithFileAccess)) {
				$users = [];
				foreach ($usersWithFileAccess as $userId) {
					$users[$userId] = $this->userManager->getDisplayName($userId) ?? $userId;
				}
				$this->searchUsers($search, $users, $searchResult);
			}
		}

		/** @var array<string, string> $userIds */
		$userIds = [];
		/** @var array<string, string> $groupIds */
		$groupIds = [];
		/** @var array<string, string> $cloudIds */
		$cloudIds = [];
		/** @var list<Attendee> $guestAttendees */
		$guestAttendees = [];

		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE) {
			// Add potential leavers of one-to-one rooms again.
			$participants = json_decode($this->room->getName(), true);
			foreach ($participants as $userId) {
				$userIds[$userId] = $this->userManager->getDisplayName($userId) ?? $userId;
			}
		} else {
			$participants = $this->participantService->getParticipantsForRoom($this->room);
			foreach ($participants as $participant) {
				$attendee = $participant->getAttendee();
				if ($attendee->getActorType() === Attendee::ACTOR_GUESTS) {
					$guestAttendees[] = $attendee;
				} elseif ($attendee->getActorType() === Attendee::ACTOR_USERS) {
					$userIds[$attendee->getActorId()] = $attendee->getDisplayName();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
					$cloudIds[$attendee->getActorId()] = $attendee->getDisplayName();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
					$groupIds[$attendee->getActorId()] = $attendee->getDisplayName();
				}
			}
		}

		$this->searchUsers($search, $userIds, $searchResult);
		$this->searchGroups($search, $groupIds, $searchResult);
		$this->searchGuests($search, $guestAttendees, $searchResult);
		$this->searchFederatedUsers($search, $cloudIds, $searchResult);

		return false;
	}

	/**
	 * @param array<string|int, string> $users
	 */
	protected function searchUsers(string $search, array $users, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$type = new SearchResultType('users');

		$matches = $exactMatches = [];
		foreach ($users as $userId => $displayName) {
			$userId = (string) $userId;
			if ($this->userId !== '' && $this->userId === $userId) {
				// Do not suggest the current user
				continue;
			}

			if ($searchResult->hasResult($type, $userId)) {
				continue;
			}

			if ($search === '') {
				$matches[] = $this->createResult('user', $userId, $displayName);
				continue;
			}

			if (strtolower($userId) === $search) {
				$exactMatches[] = $this->createResult('user', $userId, $displayName);
				continue;
			}

			if (stripos($userId, $search) !== false) {
				$matches[] = $this->createResult('user', $userId, $displayName);
				continue;
			}

			if ($displayName === '') {
				continue;
			}

			if (strtolower($displayName) === $search) {
				$exactMatches[] = $this->createResult('user', $userId, $displayName);
				continue;
			}

			if (stripos($displayName, $search) !== false) {
				$matches[] = $this->createResult('user', $userId, $displayName);
				continue;
			}
		}

		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	/**
	 * @param array<string, string> $cloudIds
	 */
	protected function searchFederatedUsers(string $search, array $cloudIds, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$type = new SearchResultType('federated_users');

		$matches = $exactMatches = [];
		foreach ($cloudIds as $cloudId => $displayName) {
			if ($this->federationAuthenticator->getCloudId() === $cloudId) {
				// Do not suggest the current user
				continue;
			}

			if ($searchResult->hasResult($type, $cloudId)) {
				continue;
			}

			if ($search === '') {
				$matches[] = $this->createResult('federated_user', $cloudId, $displayName);
				continue;
			}

			if (strtolower($cloudId) === $search) {
				$exactMatches[] = $this->createResult('federated_user', $cloudId, $displayName);
				continue;
			}

			if (stripos($cloudId, $search) !== false) {
				$matches[] = $this->createResult('federated_user', $cloudId, $displayName);
				continue;
			}

			if ($displayName === '') {
				continue;
			}

			if (strtolower($displayName) === $search) {
				$exactMatches[] = $this->createResult('federated_user', $cloudId, $displayName);
				continue;
			}

			if (stripos($displayName, $search) !== false) {
				$matches[] = $this->createResult('federated_user', $cloudId, $displayName);
				continue;
			}
		}

		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	/**
	 * @param array<string|int, string> $groups
	 */
	protected function searchGroups(string $search, array $groups, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$type = new SearchResultType('groups');

		$matches = $exactMatches = [];
		foreach ($groups as $groupId => $displayName) {
			if ($displayName === '') {
				continue;
			}

			$groupId = (string) $groupId;
			if ($searchResult->hasResult($type, $groupId)) {
				continue;
			}

			if ($search === '') {
				$matches[] = $this->createGroupResult($groupId, $displayName);
				continue;
			}

			if (strtolower($groupId) === $search) {
				$exactMatches[] = $this->createGroupResult($groupId, $displayName);
				continue;
			}

			if (stripos($groupId, $search) !== false) {
				$matches[] = $this->createGroupResult($groupId, $displayName);
				continue;
			}

			if (strtolower($displayName) === $search) {
				$exactMatches[] = $this->createGroupResult($groupId, $displayName);
				continue;
			}

			if (stripos($displayName, $search) !== false) {
				$matches[] = $this->createGroupResult($groupId, $displayName);
				continue;
			}
		}

		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	/**
	 * @param string $search
	 * @param list<Attendee> $attendees
	 * @param ISearchResult $searchResult
	 */
	protected function searchGuests(string $search, array $attendees, ISearchResult $searchResult): void {
		if (empty($attendees)) {
			$type = new SearchResultType('guests');
			$searchResult->addResultSet($type, [], []);
			return;
		}

		$search = strtolower($search);
		$currentSessionHash = null;
		if (!$this->userId) {
			// Best effort: Might not work on guests that reloaded but not worth too much performance impact atm.
			$currentSessionHash = sha1($this->talkSession->getSessionForRoom($this->room->getToken()));
		}

		$matches = $exactMatches = [];
		foreach ($attendees as $attendee) {
			if ($currentSessionHash === $attendee->getActorId()) {
				// Do not suggest the current guest
				continue;
			}

			$name = $attendee->getDisplayName() ?: $this->l->t('Guest');
			if ($search === '') {
				$matches[] = $this->createGuestResult($attendee->getActorId(), $name);
				continue;
			}

			if (strtolower($name) === $search) {
				$exactMatches[] = $this->createGuestResult($attendee->getActorId(), $name);
				continue;
			}

			if (stripos($name, $search) !== false) {
				$matches[] = $this->createGuestResult($attendee->getActorId(), $name);
				continue;
			}
		}

		$type = new SearchResultType('guests');
		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	protected function createResult(string $type, string $uid, string $name): array {
		if ($type === 'user' && $name === '') {
			$name = $this->userManager->getDisplayName($uid) ?? $uid;
		}

		return [
			'label' => $name,
			'value' => [
				'shareType' => $type,
				'shareWith' => $uid,
			],
		];
	}

	protected function createGroupResult(string $groupId, string $name): array {
		return [
			'label' => $name,
			'value' => [
				'shareType' => 'group',
				'shareWith' => 'group/' . $groupId,
			],
		];
	}

	protected function createGuestResult(string $actorId, string $name): array {
		return [
			'label' => $name,
			'value' => [
				'shareType' => 'guest',
				'shareWith' => 'guest/' . $actorId,
			],
		];
	}
}
