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

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\RemoteClientException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 */
class ChatController {
	public function __construct(
		protected ProxyRequest  $proxy,
		protected UserConverter $userConverter,
	) {
	}

	/**
	 * @throws CannotReachRemoteException
	 * @throws RemoteClientException
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS, array<empty>, array{}>
	 *
	 * 201: Message sent successfully
	 * 400: Sending message is not possible
	 * 404: Actor not found
	 * 413: Message too long
	 * 429: Mention rate limit exceeded (guests only)
	 */
	public function sendMessage(Room $room, Participant $participant, string $message, string $referenceId, int $replyTo, bool $silent): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken(),
			[
				'message' => $message,
				'actorDisplayName' => $participant->getAttendee()->getDisplayName(),
				'referenceId' => $referenceId,
				'replyTo' => $replyTo,
				'silent' => $silent,
			],
		);

		try {
			$content = $proxy->getBody();
			$responseData = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($responseData)) {
				throw new \RuntimeException('JSON response is not an array');
			}
		} catch (\Throwable $e) {
			throw new CannotReachRemoteException('Error parsing JSON response', $e->getCode(), $e);
		}

		/** @var Http::STATUS_CREATED|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS $statusCode */
		$statusCode = match ($proxy->getStatusCode()) {
			Http::STATUS_CREATED,
			Http::STATUS_BAD_REQUEST,
			Http::STATUS_NOT_FOUND,
			Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
			Http::STATUS_TOO_MANY_REQUESTS => $proxy->getStatusCode(),
			default => $this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode()),
		};

		if ($statusCode !== Http::STATUS_CREATED) {
			return new DataResponse([], $statusCode);
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}

		/** @var ?TalkChatMessageWithParent $data */
		$data = $responseData['ocs']['data'] ?? null;

		if ($data !== null) {
			/** @var ?TalkChatMessageWithParent $data */
			$data = $this->userConverter->convertAttendee($room, $data, 'actorType', 'actorId', 'actorDisplayName');
		}

		return new DataResponse(
			$data,
			$statusCode,
			$headers
		);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, TalkChatMentionSuggestion[], array{}>
	 * @throws CannotReachRemoteException
	 * @throws RemoteClientException
	 *
	 *  200: List of mention suggestions returned
	 */
	public function mentions(Room $room, Participant $participant, string $search, int $limit, bool $includeStatus): DataResponse {
		$proxy = $this->proxy->get(
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
			$content = $proxy->getBody();
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
		return new DataResponse($this->userConverter->convertAttendees($room, $data, 'source', 'id', 'label'));
	}
}
