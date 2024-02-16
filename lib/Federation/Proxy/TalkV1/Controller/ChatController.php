<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\RemoteClientException;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 */
class ChatController {
	protected ?Room $room = null;

	public function __construct(
		protected ProxyRequest  $proxy,
		protected UserConverter $userConverter,
	) {
	}

	/**
	 * @return TalkChatMentionSuggestion[]
	 * @throws CannotReachRemoteException
	 * @throws RemoteClientException
	 */
	public function mentions(Room $room, Participant $participant, string $search, int $limit, bool $includeStatus): array {
		$this->room = $room;

		$response = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/mentions',
			[
				'search' => $search,
				'limit' => $limit,
				'includeStatus' => $includeStatus,
			],
		);

		try {
			$content = $response->getBody();
			$responseData = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($responseData)) {
				throw new \RuntimeException('JSON response is not an array');
			}
		} catch (\Throwable $e) {
			throw new CannotReachRemoteException('Error parsing JSON response', $e->getCode(), $e);
		}

		/** @var TalkChatMentionSuggestion[] $data */
		$data = $responseData['ocs']['data'] ?? [];

		// FIXME post-load status information
		return $this->userConverter->convertAttendees($room, $data, 'source', 'id', 'label');
	}
}
