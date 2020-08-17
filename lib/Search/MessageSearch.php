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
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Manager as RoomManager;
use OCA\Talk\Room;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class MessageSearch implements IProvider {

	/** @var RoomManager */
	protected $roomManager;
	/** @var ChatManager */
	protected $chatManager;
	/** @var MessageParser */
	protected $messageParser;
	/** @var IURLGenerator */
	protected $url;
	/** @var IL10N */
	protected $l;

	public function __construct(
		RoomManager $roomManager,
		ChatManager $chatManager,
		MessageParser $messageParser,
		IURLGenerator $url,
		IL10N $l
	) {
		$this->roomManager = $roomManager;
		$this->chatManager = $chatManager;
		$this->messageParser = $messageParser;
		$this->url = $url;
		$this->l = $l;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'talk_message';
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
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Talk results
			return -2;
		}

		return 15;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$rooms = $this->roomManager->getRoomsForParticipant($user->getUID());
		$subline = $this->l->t('{user} in {conversation}');

		$roomMap = [];
		foreach ($rooms as $room) {
			if ($room->getType() === Room::CHANGELOG_CONVERSATION) {
				continue;
			}

			$roomMap[(string) $room->getId()] = $room;
		}

		if (empty($roomMap)) {
			return SearchResult::complete(
				$this->l->t('Messages'),
				[]
			);
		}

		$comments = $this->chatManager->searchForObjects(
			$query->getTerm(),
			array_keys($roomMap),
			'comment',
			0,
			$query->getLimit()
		);

		$result = [];
		foreach ($comments as $comment) {
			$room = $roomMap[$comment->getObjectId()];
			$participant = $room->getParticipant($user->getUID());

			$id = (int) $comment->getId();
			$message = $this->messageParser->createMessage($room, $participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

			$messageStr = $message->getMessage();
			$search = $replace = [];
			foreach ($message->getMessageParameters() as $key => $parameter) {
				$search = '{' . $key . '}';
				if ($parameter['type'] === 'user') {
					$replace = '@' . $parameter['name'];
				} else {
					$replace = $parameter['name'];
				}
			}
			$messageStr = str_replace($search, $replace, $messageStr);

			if (!$message->getVisibility()) {
				$commentIdToIndex[$id] = null;
				continue;
			}

			$iconUrl = '';
			if ($message->getActorType() === 'users') {
				$iconUrl = $this->url->linkToRouteAbsolute('core.avatar.getAvatar', [
					'userId' => $message->getActorId(),
					'size' => 64,
				]);
			}

			$result[] = new SearchResultEntry(
				$iconUrl,
				$messageStr,
				str_replace(
					['{user}', '{conversation}'],
					[$message->getActorDisplayName(), $room->getDisplayName($user->getUID())],
					$subline
				),
				$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getId()]) . '#m' . $id,
				'icon-talk', // $iconClass,
				true
			);
		}

		return SearchResult::complete(
			$this->l->t('Messages'),
			$result
		);
	}
}
