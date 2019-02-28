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

namespace OCA\Spreed\Chat\AutoComplete;


use OCA\Spreed\Files\Util;
use OCA\Spreed\Room;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IUser;
use OCP\IUserManager;

class SearchPlugin implements ISearchPlugin {

	/** @var IUserManager */
	protected $userManager;
	/** @var Util */
	protected $util;

	/** @var string|null */
	protected $userId;

	/** @var Room */
	protected $room;

	public function __construct(IUserManager $userManager,
								Util $util,
								?string $userId) {
		$this->userManager = $userManager;
		$this->util = $util;
		$this->userId = $userId;
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

		$userIds = $this->room->getParticipantUserIds();
		if ($this->room->getType() === Room::ONE_TO_ONE_CALL
			&& $this->room->getName() !== '') {
			// Add potential leavers of one-to-one rooms again.
			$userIds[] = $this->room->getName();
		}

		// FIXME Handle guests
		$this->searchUsers($search, $userIds, $searchResult);

		if ($this->room->getObjectType() === 'file') {
			$usersWithFileAccess = $this->util->getUsersWithAccessFile($this->room->getObjectId());
			if (!empty($usersWithFileAccess)) {
				$this->searchUsers($search, $usersWithFileAccess, $searchResult);
			}
		}

		return false;
	}

	protected function searchUsers(string $search, array $userIds, ISearchResult $searchResult): void {
		$search = strtolower($search);

		$matches = $exactMatches = [];
		foreach ($userIds as $userId) {
			if ($this->userId !== '' && $this->userId === $userId) {
				// Do not suggest the current user
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

		$type = new SearchResultType('users');
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
}
