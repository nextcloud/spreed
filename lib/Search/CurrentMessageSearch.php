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

namespace OCA\Talk\Search;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class CurrentMessageSearch extends MessageSearch {
	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'talk-message-current';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Messages in current conversation');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): ?int {
		$currentUser = $this->userSession->getUser();
		if ($currentUser && $this->talkConfig->isDisabledForUser($currentUser)) {
			return null;
		}

		if ($route === 'spreed.Page.showCall') {
			// In conversation, prefer this search results
			return -3;
		}

		// We are not returning something anyway.
		return null;
	}

	protected function getSublineTemplate(): string {
		return $this->l->t('{user}');
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$title = $this->l->t('Messages');
		$currentToken = $this->getCurrentConversationToken($query);
		if ($currentToken === '') {
			return SearchResult::complete($title, []);
		}

		$filter = $query->getFilter(self::CONVERSATION_FILTER);
		if ($filter && $filter->get() !== $currentToken) {
			return SearchResult::complete($title, []);
		}

		try {
			$room = $this->roomManager->getRoomForUserByToken(
				$currentToken,
				$user->getUID()
			);
		} catch (RoomNotFoundException) {
			return SearchResult::complete($title, []);
		}

		try {
			$this->participantService->getParticipant($room, $user->getUID(), false);
		} catch (ParticipantNotFoundException) {
			return SearchResult::complete($title, []);
		}

		if ($room->getRemoteServer() !== '') {
			return SearchResult::complete($title, []);
		}

		return $this->performSearch($user, $query, $this->l->t('Messages'), [$room], true);
	}
}
