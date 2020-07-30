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

use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class ConversationSearch implements IProvider {

	/** @var Manager */
	protected $manager;
	/** @var IURLGenerator */
	protected $url;
	/** @var IL10N */
	protected $l;

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
		return 'talk_conversations';
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
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$rooms = $this->manager->getRoomsForParticipant($user->getUID());

		$result = [];
		foreach ($rooms as $room) {
			if (
				$room->getType() === Room::CHANGELOG_CONVERSATION || (
					stripos($room->getName(), $query->getTerm()) === false &&
					stripos($room->getDisplayName($user->getUID()), $query->getTerm()) === false
				)
			) {
				continue;
			}

			$icon = '';
			$iconClass = '';
			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				$users = $room->getParticipantUserIds();
				foreach ($users as $participantId) {
					if ($participantId !== $user->getUID()) {
						$icon = $this->url->linkToRouteAbsolute('core.avatar.getAvatar', [
							'userId' => $participantId,
							'size' => 128,
						]);
					}
				}
			} elseif ($room->getObjectType() === 'file') {
				$iconClass = 'conversation-icon icon-file';
			} elseif ($room->getObjectType() === 'share:password') {
				$iconClass = 'conversation-icon icon-password';
			} elseif ($room->getObjectType() === 'emails') {
				$iconClass = 'conversation-icon icon-mail';
			} elseif ($room->getType() === Room::PUBLIC_CALL) {
				$iconClass = 'conversation-icon icon-public';
			} else {
				$iconClass = 'conversation-icon icon-contacts';
			}

			$result[] = new ConversationSearchResult(
				$icon,
				$room->getDisplayName($user->getUID()),
				'',
				$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
				$iconClass,
				true
			);
		}

		return SearchResult::complete(
			$this->l->t('Conversations'),
			$result
		);
	}
}
