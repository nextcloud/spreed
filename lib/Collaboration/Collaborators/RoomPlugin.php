<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	#[\Override]
	public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
		if (!is_string($search) || $search === '') {
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

			if ($room->isFederatedConversation()) {
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
