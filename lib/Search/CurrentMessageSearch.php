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
use OCA\Talk\Exceptions\UnauthorizedException;
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
		return $this->l->t('Messages');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'spreed.Page.showCall') {
			// In conversation, prefer this search results
			return -3;
		}

		// We are not returning something anyway.
		return 999;
	}

	protected function getSublineTemplate(): string {
		return $this->l->t('{user}');
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$currentToken = $this->getCurrentConversationToken($query);
		if ($currentToken === '') {
			return SearchResult::complete(
				$this->l->t('Messages'),
				[]
			);
		}

		try {
			$room = $this->roomManager->getRoomForUserByToken(
				$currentToken,
				$user->getUID()
			);
		} catch (RoomNotFoundException $e) {
			return SearchResult::complete(
				$this->l->t('Messages'),
				[]
			);
		}

		$offset = (int) $query->getCursor();
		$comments = $this->chatManager->searchForObjects(
			$query->getTerm(),
			[(string) $room->getId()],
			'comment',
			$offset,
			$query->getLimit()
		);

		$result = [];
		foreach ($comments as $comment) {
			try {
				$result[] = $this->commentToSearchResultEntry($room, $user, $comment, $query);
			} catch (UnauthorizedException $e) {
			} catch (ParticipantNotFoundException $e) {
			}
		}

		return SearchResult::paginated(
			str_replace(
				'{conversation}',
				$room->getDisplayName($user->getUID()),
				$this->l->t('Messages in {conversation}')
			),
			$result,
			$offset + $query->getLimit()
		);
	}
}
