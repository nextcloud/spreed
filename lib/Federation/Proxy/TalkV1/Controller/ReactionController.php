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

use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

/**
 * @psalm-import-type TalkReaction from ResponseDefinitions
 */
class ReactionController {
	public function __construct(
		protected ProxyRequest $proxy,
		protected UserConverter $userConverter,
	) {
	}

	/**
	 * Add a reaction to a message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @param string $reaction Emoji to add
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, array<string, TalkReaction[]>|\stdClass, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reaction already existed
	 * 201: Reaction added successfully
	 * 400: Adding reaction is not possible
	 * 404: Message not found
	 */
	public function react(Room $room, Participant $participant, int $messageId, string $reaction, string $format): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/reaction/' . $room->getRemoteToken() . '/' . $messageId,
			[
				'reaction' => $reaction,
			],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK && $statusCode !== Http::STATUS_CREATED) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], $statusCode);
		}

		/** @var array<string, TalkReaction[]> $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_CREATED, Http::STATUS_OK]);
		$data = $this->userConverter->convertReactionsList($room, $data);

		return new DataResponse($this->formatReactions($format, $data), $statusCode);
	}

	/**
	 * Delete a reaction from a message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @param string $reaction Emoji to remove
	 * @return DataResponse<Http::STATUS_OK, array<string, TalkReaction[]>|\stdClass, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reaction deleted successfully
	 * 400: Deleting reaction is not possible
	 * 404: Message not found
	 */
	public function delete(Room $room, Participant $participant, int $messageId, string $reaction, string $format): DataResponse {
		$proxy = $this->proxy->delete(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/reaction/' . $room->getRemoteToken() . '/' . $messageId,
			[
				'reaction' => $reaction,
			],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], $statusCode);
		}

		/** @var array<string, TalkReaction[]> $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertReactionsList($room, $data);

		return new DataResponse($this->formatReactions($format, $data), $statusCode);
	}


	/**
	 * Get a list of reactions for a message
	 *
	 * @param int $messageId ID of the message
	 * @psalm-param non-negative-int $messageId
	 * @param string|null $reaction Emoji to filter
	 * @return DataResponse<Http::STATUS_OK, array<string, TalkReaction[]>|\stdClass, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reactions returned
	 * 404: Message or reaction not found
	 */
	public function getReactions(Room $room, Participant $participant, int $messageId, ?string $reaction, string $format): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/reaction/' . $room->getRemoteToken() . '/' . $messageId,
			$reaction === null ? [] : [
				'reaction' => $reaction,
			],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if ($statusCode !== Http::STATUS_NOT_FOUND) {
				$this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var array<string, TalkReaction[]> $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertReactionsList($room, $data);

		return new DataResponse($this->formatReactions($format, $data), $statusCode);
	}

	/**
	 * @param array<string, TalkReaction[]> $reactions
	 * @return array<string, TalkReaction[]>|\stdClass
	 */
	protected function formatReactions(string $format, array $reactions): array|\stdClass {
		if ($format === 'json' && empty($reactions)) {
			// Cheating here to make sure the reactions array is always a
			// JSON object on the API, even when there is no reaction at all.
			return new \stdClass();
		}

		return $reactions;
	}
}
