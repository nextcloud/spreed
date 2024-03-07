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

namespace OCA\Talk\Collaboration\Collaborators;

use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IUserSession;
use OCP\Share\IShare;

class RoomPlugin implements ISearchPlugin {

	public function __construct(
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected IUserSession $userSession,
	) {
	}

	/**
	 * {@inheritdoc}
	 */
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		if (empty($search)) {
			return false;
		}

		$userId = $this->userSession->getUser()->getUID();

		$result = ['wide' => [], 'exact' => []];

		$rooms = $this->manager->getRoomsForUser($userId);
		foreach ($rooms as $room) {
			if ($room->getReadOnly() === Room::READ_ONLY) {
				// Can not add new shares to read-only rooms
				continue;
			}

			if ($room->getRemoteServer() !== '') {
				continue;
			}

			$participant = $this->participantService->getParticipant($room, $userId, false);
			if (!($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
				// No chat permissions is like read-only
				continue;
			}

			if (stripos($room->getDisplayName($userId), $search) !== false) {
				$item = $this->roomToSearchResultItem($room, $userId);

				if (strtolower($item['label']) === strtolower($search)) {
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

	private function roomToSearchResultItem(Room $room, string $userId): array {
		return
		[
			'label' => $room->getDisplayName($userId),
			'value' => [
				'shareType' => IShare::TYPE_ROOM,
				'shareWith' => $room->getToken()
			]
		];
	}
}
