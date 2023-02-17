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

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class ConversationSearch implements IProvider {
	protected Manager $manager;
	protected IURLGenerator $url;
	protected IL10N $l;

	public function __construct(
		Manager $manager,
		IURLGenerator $url,
		IL10N $l
	) {
		$this->manager = $manager;
		$this->url = $url;
		$this->l = $l;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'talk-conversations';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Conversations');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Talk results
			return -1;
		}

		return 25;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$rooms = $this->manager->getRoomsForUser($user->getUID());

		$result = [];
		foreach ($rooms as $room) {
			if ($room->getType() === Room::TYPE_CHANGELOG) {
				continue;
			}

			$parameters = $query->getRouteParameters();
			if (isset($parameters['token']) &&
				$parameters['token'] === $room->getToken() &&
				strpos($query->getRoute(), Application::APP_ID . '.') === 0) {
				// Don't search the current conversation.
				//User most likely looks for other things with the same name
				continue;
			}

			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$otherUserId = str_replace(
					json_encode($user->getUID()),
					'',
					$room->getName()
				);
				if (stripos($otherUserId, $query->getTerm()) === false
					&& stripos($room->getDisplayName($user->getUID()), $query->getTerm()) === false) {
					// Neither name nor displayname (one-to-one) match, skip
					continue;
				}
			} else {
				if (stripos($room->getName(), $query->getTerm()) === false) {
					continue;
				}
			}

			$icon = '';
			$iconClass = '';
			if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$users = json_decode($room->getName(), true);
				foreach ($users as $participantId) {
					if ($participantId !== $user->getUID()) {
						$icon = $this->url->linkToRouteAbsolute('core.avatar.getAvatar', [
							'userId' => $participantId,
							'size' => 512,
						]);
					}
				}
			} elseif ($room->getObjectType() === 'file') {
				$iconClass = 'conversation-icon icon-file-white';
			} elseif ($room->getObjectType() === 'share:password') {
				$iconClass = 'conversation-icon icon-password-white';
			} elseif ($room->getObjectType() === 'emails') {
				$iconClass = 'conversation-icon icon-mail-white';
			} elseif ($room->getType() === Room::TYPE_PUBLIC) {
				$iconClass = 'conversation-icon icon-public-white';
			} else {
				$iconClass = 'conversation-icon icon-contacts-white';
			}

			$entry = new SearchResultEntry(
				$icon,
				$room->getDisplayName($user->getUID()),
				'',
				$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
				$iconClass,
				true
			);

			$entry->addAttribute('conversation', $room->getToken());

			$result[] = $entry;
		}

		return SearchResult::complete(
			$this->l->t('Conversations'),
			$result
		);
	}
}
