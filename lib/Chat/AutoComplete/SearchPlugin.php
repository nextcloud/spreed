<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
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
		/** @var array<string, Attendee> $emailAttendees */
		$emailAttendees = [];
		/** @var list<Attendee> $guestAttendees */
		$guestAttendees = [];
		/** @var array<string, string> $teamIds */
		$teamIds = [];

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
				} elseif ($attendee->getActorType() === Attendee::ACTOR_EMAILS) {
					$emailAttendees[$attendee->getActorId()] = $attendee;
				} elseif ($attendee->getActorType() === Attendee::ACTOR_USERS) {
					$userIds[$attendee->getActorId()] = $attendee->getDisplayName();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
					$cloudIds[$attendee->getActorId()] = $attendee->getDisplayName();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
					$groupIds[$attendee->getActorId()] = $attendee->getDisplayName();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
					$teamIds[$attendee->getActorId()] = $attendee->getDisplayName();
				}
			}
		}

		$this->searchUsers($search, $userIds, $searchResult);
		$this->searchGroups($search, $groupIds, $searchResult);
		$this->searchGuests($search, $guestAttendees, $searchResult);
		$this->searchEmails($search, $emailAttendees, $searchResult);
		$this->searchFederatedUsers($search, $cloudIds, $searchResult);
		$this->searchTeams($search, $teamIds, $searchResult);

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
			$userId = (string)$userId;
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

			$groupId = (string)$groupId;
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
		$matches = $exactMatches = [];
		foreach ($attendees as $attendee) {
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

	/**
	 * @param string $search
	 * @param array<string, Attendee> $attendees
	 * @param ISearchResult $searchResult
	 */
	protected function searchEmails(string $search, array $attendees, ISearchResult $searchResult): void {
		if (empty($attendees)) {
			$type = new SearchResultType('emails');
			$searchResult->addResultSet($type, [], []);
			return;
		}

		$search = strtolower($search);
		$currentSessionHash = null;
		if (!$this->userId) {
			// Best effort: Might not work on guests that reloaded but not worth too much performance impact atm.
			$currentSessionHash = false; // FIXME sha1($this->talkSession->getSessionForRoom($this->room->getToken()));
		}

		$matches = $exactMatches = [];
		foreach ($attendees as $actorId => $attendee) {
			if ($currentSessionHash === $actorId) {
				// Do not suggest the current guest
				continue;
			}

			$displayName = $attendee->getDisplayName() ?: $this->l->t('Guest');
			if ($search === '') {
				$matches[] = $this->createEmailResult($actorId, $displayName, $attendee->getInvitedCloudId());
				continue;
			}

			if (strtolower($displayName) === $search) {
				$exactMatches[] = $this->createEmailResult($actorId, $displayName, $attendee->getInvitedCloudId());
				continue;
			}

			if (stripos($displayName, $search) !== false) {
				$matches[] = $this->createEmailResult($actorId, $displayName, $attendee->getInvitedCloudId());
				continue;
			}
		}

		$type = new SearchResultType('emails');
		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	/**
	 * @param string $search
	 * @param array<string, Attendee> $attendees
	 * @param ISearchResult $searchResult
	 */
	/**
	 * @param array<string|int, string> $teams
	 */
	protected function searchTeams(string $search, array $teams, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$type = new SearchResultType('teams');

		$matches = $exactMatches = [];
		foreach ($teams as $teamId => $displayName) {
			if ($displayName === '') {
				continue;
			}

			$teamId = (string)$teamId;
			if ($searchResult->hasResult($type, $teamId)) {
				continue;
			}

			if ($search === '') {
				$matches[] = $this->createTeamResult($teamId, $displayName);
				continue;
			}

			if (strtolower($teamId) === $search) {
				$exactMatches[] = $this->createTeamResult($teamId, $displayName);
				continue;
			}

			if (stripos($teamId, $search) !== false) {
				$matches[] = $this->createTeamResult($teamId, $displayName);
				continue;
			}

			if (strtolower($displayName) === $search) {
				$exactMatches[] = $this->createTeamResult($teamId, $displayName);
				continue;
			}

			if (stripos($displayName, $search) !== false) {
				$matches[] = $this->createTeamResult($teamId, $displayName);
			}
		}

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

	protected function createEmailResult(string $actorId, string $name, ?string $email): array {
		$data = [
			'label' => $name,
			'value' => [
				'shareType' => 'email',
				'shareWith' => 'email/' . $actorId,
			],
		];

		if ($email) {
			$data['details'] = ['email' => $email];
		}

		return $data;
	}

	protected function createTeamResult(string $actorId, string $name): array {
		return [
			'label' => $name,
			'value' => [
				'shareType' => 'team',
				'shareWith' => 'team/' . $actorId,
			],
		];
	}
}
