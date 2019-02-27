<?php
declare(strict_types=1);

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Collaboration\Collaborators;

use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IUserSession;
use OCP\Share;

class RoomPlugin implements ISearchPlugin {

	/** @var Manager */
	private $manager;

	/** @var IUserSession */
	private $userSession;

	/**
	 * @param Manager manager
	 * @param IUserSession userSession
	 */
	public function __construct(Manager $manager, IUserSession $userSession) {
		$this->manager = $manager;
		$this->userSession = $userSession;
	}

	/**
	 * {@inheritdoc}
	 */
	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		if (empty($search)) {
			return false;
		}

		$result = ['wide' => [], 'exact' => []];

		$rooms = $this->manager->getRoomsForParticipant($this->userSession->getUser()->getUID());
		foreach ($rooms as $room) {
			if (stripos($room->getName(), $search) !== false) {
				$item = $this->roomToSearchResultItem($room);

				if (strtolower($room->getName()) === strtolower($search)) {
					$result['exact'][] = $item;
				} else {
					$result['wide'][] = $item;
				}
			}
		}

		$type = new SearchResultType('rooms');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return false;
	}

	/**
	 * @param Room $room
	 * @return array
	 */
	private function roomToSearchResultItem(Room $room): array {
		return
		[
			'label' => $room->getName(),
			'value' => [
				'shareType' => Share::SHARE_TYPE_ROOM,
				'shareWith' => $room->getToken()
			]
		];
	}

}
