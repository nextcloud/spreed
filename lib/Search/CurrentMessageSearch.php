<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
	public function getId(): string {
		return 'talk-message-current';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getName(): string {
		return $this->l->t('Messages in current conversation');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
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

	#[\Override]
	protected function getSublineTemplate(): string {
		return $this->l->t('{user}');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
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

		if ($room->isFederatedConversation()) {
			return SearchResult::complete($title, []);
		}

		return $this->performSearch($user, $query, $this->l->t('Messages'), [$room], true);
	}
}
