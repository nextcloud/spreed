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
use OCP\IUser;
use OCP\IUserManager;

class SearchPlugin implements ISearchPlugin {

	/** @var IUserManager */
	protected $userManager;
	/** @var GuestManager */
	protected $guestManager;
	/** @var TalkSession */
	protected $talkSession;
	/** @var ParticipantService */
	protected $participantService;
	/** @var Util */
	protected $util;
	/** @var string|null */
	protected $userId;
	/** @var IL10N */
	protected $l;

	/** @var Room */
	protected $room;

	public function __construct(IUserManager $userManager,
								GuestManager $guestManager,
								TalkSession $talkSession,
								ParticipantService $participantService,
								Util $util,
								?string $userId,
								IL10N $l) {
		$this->userManager = $userManager;
		$this->guestManager = $guestManager;
		$this->talkSession = $talkSession;
		$this->participantService = $participantService;
		$this->util = $util;
		$this->userId = $userId;
		$this->l = $l;
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
	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		if ($this->room->getObjectType() === 'file') {
			$usersWithFileAccess = $this->util->getUsersWithAccessFile($this->room->getObjectId());
			if (!empty($usersWithFileAccess)) {
				$this->searchUsers($search, $usersWithFileAccess, $searchResult);
			}
		}

		$userIds = $guestSessionHashes = [];
		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			// Add potential leavers of one-to-one rooms again.
			$participants = json_decode($this->room->getName(), true);
			foreach ($participants as $userId) {
				$userIds[] = $userId;
			}
		} else {
			$participants = $this->participantService->getParticipantsForRoom($this->room);
			foreach ($participants as $participant) {
				$attendee = $participant->getAttendee();
				if ($attendee->getActorType() === Attendee::ACTOR_GUESTS) {
					$guestSessionHashes[] = $attendee->getActorId();
				} elseif ($attendee->getActorType() === Attendee::ACTOR_USERS) {
					$userIds[] = $attendee->getActorId();
				}
			}
		}

		$this->searchUsers($search, $userIds, $searchResult);
		$this->searchGuests($search, $guestSessionHashes, $searchResult);

		return false;
	}

	protected function searchUsers(string $search, array $userIds, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$type = new SearchResultType('users');

		$matches = $exactMatches = [];
		foreach ($userIds as $userId) {
			if ($this->userId !== '' && $this->userId === $userId) {
				// Do not suggest the current user
				continue;
			}

			if ($searchResult->hasResult($type, $userId)) {
				continue;
			}

			if ($search === '') {
				$matches[] = $this->createResult('user', $userId, '');
				continue;
			}

			if (strtolower($userId) === $search) {
				$exactMatches[] = $this->createResult('user', $userId, '');
				continue;
			}

			if (stripos($userId, $search) !== false) {
				$matches[] = $this->createResult('user', $userId, '');
				continue;
			}

			$user = $this->userManager->get($userId);
			if (!$user instanceof IUser) {
				continue;
			}

			if (strtolower($user->getDisplayName()) === $search) {
				$exactMatches[] = $this->createResult('user', $user->getUID(), $user->getDisplayName());
				continue;
			}

			if (stripos($user->getDisplayName(), $search) !== false) {
				$matches[] = $this->createResult('user', $user->getUID(), $user->getDisplayName());
				continue;
			}
		}

		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	protected function searchGuests(string $search, array $guestSessionHashes, ISearchResult $searchResult): void {
		if (empty($guestSessionHashes)) {
			$type = new SearchResultType('guests');
			$searchResult->addResultSet($type, [], []);
			return;
		}

		$search = strtolower($search);
		$displayNames = $this->guestManager->getNamesBySessionHashes($guestSessionHashes);
		$currentSessionHash = null;
		if (!$this->userId) {
			$currentSessionHash = sha1($this->talkSession->getSessionForRoom($this->room->getToken()));
		}

		$matches = $exactMatches = [];
		foreach ($guestSessionHashes as $guestSessionHash) {
			if ($currentSessionHash === $guestSessionHash) {
				// Do not suggest the current guest
				continue;
			}

			$name = $displayNames[$guestSessionHash] ?? $this->l->t('Guest');
			if ($search === '') {
				$matches[] = $this->createGuestResult($guestSessionHash, $name);
				continue;
			}

			if (strtolower($name) === $search) {
				$exactMatches[] = $this->createGuestResult($guestSessionHash, $name);
				continue;
			}

			if (stripos($name, $search) !== false) {
				$matches[] = $this->createGuestResult($guestSessionHash, $name);
				continue;
			}
		}

		$type = new SearchResultType('guests');
		$searchResult->addResultSet($type, $matches, $exactMatches);
	}

	protected function createResult(string $type, string $uid, string $name): array {
		if ($type === 'user' && $name === '') {
			$user = $this->userManager->get($uid);
			if ($user instanceof IUser) {
				$name = $user->getDisplayName();
			} else {
				$name = $uid;
			}
		}

		return [
			'label' => $name,
			'value' => [
				'shareType' => $type,
				'shareWith' => $uid,
			],
		];
	}

	protected function createGuestResult(string $uid, string $name): array {
		return [
			'label' => $name,
			'value' => [
				'shareType' => 'guest',
				'shareWith' => 'guest/' . $uid,
			],
		];
	}
}
