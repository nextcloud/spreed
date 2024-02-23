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
	 * @see \OCA\Talk\Controller\ChatController::sendMessage()
	 *
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS, array<empty>, array{}>
	 * @throws CannotReachRemoteException
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

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_CREATED) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
				Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
				Http::STATUS_TOO_MANY_REQUESTS,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], $statusCode);
		}

		/** @var ?TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_CREATED]);
		if (!empty($data)) {
			$data = $this->userConverter->convertAttendee($room, $data, 'actorType', 'actorId', 'actorDisplayName');
		} else {
			$data = null;
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			Http::STATUS_CREATED,
			$headers
		);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_MODIFIED, TalkChatMessageWithParent[], array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: numeric-string}>
	 * @throws CannotReachRemoteException
	 *
	 *  200: Messages returned
	 *  304: No messages
	 *
	 * @see \OCA\Talk\Controller\ChatController::getMessageContext()
	 */
	public function receiveMessages(
		Room $room,
		Participant $participant,
		int $lookIntoFuture,
		int $limit,
		int $lastKnownMessageId,
		int $lastCommonReadId,
		int $timeout,
		int $setReadMarker,
		int $includeLastKnown,
		int $noStatusUpdate,
		int $markNotificationsAsRead): DataResponse {

		// FIXME
		// Poor-mans timeout, should later on cancel/trigger earlier,
		// when we received a OCM message notifying us about a chat message
		sleep($timeout);

		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken(),
			[
				'lookIntoFuture' => $lookIntoFuture,
				'limit' => $limit,
				'lastKnownMessageId' => $lastKnownMessageId,
				'lastCommonReadId' => $lastCommonReadId,
				'timeout' => 0,
				'setReadMarker' => $setReadMarker,
				'includeLastKnown' => $includeLastKnown,
				'noStatusUpdate' => $noStatusUpdate,
				'markNotificationsAsRead' => $markNotificationsAsRead,
			],
		);

		if ($proxy->getStatusCode() === Http::STATUS_NOT_MODIFIED) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}
		if ($proxy->getHeader('X-Chat-Last-Given')) {
			$headers['X-Chat-Last-Given'] = (string) (int) $proxy->getHeader('X-Chat-Last-Given');
		}

		/** @var TalkChatMessageWithParent[] $data */
		$data = $this->proxy->getOCSData($proxy);
		/** @var TalkChatMessageWithParent[] $data */
		$data = $this->userConverter->convertAttendees($room, $data, 'actorType', 'actorId', 'actorDisplayName');
		$data = $this->userConverter->convertAttendees($room, $data, 'lastEditActorType', 'lastEditActorId', 'lastEditActorDisplayName');

		return new DataResponse($data, Http::STATUS_OK, $headers);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_MODIFIED, TalkChatMessageWithParent[], array{X-Chat-Last-Common-Read?: numeric-string, X-Chat-Last-Given?: numeric-string}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Message context returned
	 * 304: No messages
	 *
	 * @see \OCA\Talk\Controller\ChatController::getMessageContext()
	 */
	public function getMessageContext(Room $room, Participant $participant, int $messageId, int $limit): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/' . $messageId . '/context',
			[
				'limit' => $limit,
			],
		);

		if ($proxy->getStatusCode() === Http::STATUS_NOT_MODIFIED) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}
		if ($proxy->getHeader('X-Chat-Last-Given')) {
			$headers['X-Chat-Last-Given'] = (string) (int) $proxy->getHeader('X-Chat-Last-Given');
		}

		/** @var TalkChatMessageWithParent[] $data */
		$data = $this->proxy->getOCSData($proxy);
		/** @var TalkChatMessageWithParent[] $data */
		$data = $this->userConverter->convertAttendees($room, $data, 'actorType', 'actorId', 'actorDisplayName');
		$data = $this->userConverter->convertAttendees($room, $data, 'lastEditActorType', 'lastEditActorId', 'lastEditActorDisplayName');

		return new DataResponse($data, Http::STATUS_OK, $headers);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Message edited successfully
	 * 202: Message edited successfully, but a bot or Matterbridge is configured, so the information can be replicated to other services
	 * 400: Editing message is not possible, e.g. when the new message is empty or the message is too old
	 * 403: Missing permissions to edit message
	 * 404: Message not found
	 * 405: Editing this message type is not allowed
	 *
	 * @see \OCA\Talk\Controller\ChatController::editMessage()
	 */
	public function editMessage(Room $room, Participant $participant, int $messageId, string $message): DataResponse {
		$proxy = $this->proxy->put(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/' . $messageId,
			[
				'message' => $message,
			],
		);

		/** @var Http::STATUS_OK|Http::STATUS_ACCEPTED|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE $statusCode */
		$statusCode = $proxy->getStatusCode();

		if ($statusCode !== Http::STATUS_OK && $statusCode !== Http::STATUS_ACCEPTED) {
			if (in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_FORBIDDEN,
				Http::STATUS_NOT_FOUND,
				Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], $statusCode);
		}

		/** @var ?TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_ACCEPTED]);
		if (!empty($data)) {
			$data = $this->userConverter->convertAttendee($room, $data, 'actorType', 'actorId', 'actorDisplayName');
			$data = $this->userConverter->convertAttendee($room, $data, 'lastEditActorType', 'lastEditActorId', 'lastEditActorDisplayName');
		} else {
			$data = null;
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			$statusCode,
			$headers
		);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Message deleted successfully
	 * 202: Message deleted successfully, but a bot or Matterbridge is configured, so the information can be replicated elsewhere
	 * 400: Deleting message is not possible
	 * 403: Missing permissions to delete message
	 * 404: Message not found
	 * 405: Deleting this message type is not allowed
	 *
	 * @see \OCA\Talk\Controller\ChatController::deleteMessage()
	 */
	public function deleteMessage(Room $room, Participant $participant, int $messageId): DataResponse {
		$proxy = $this->proxy->delete(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/' . $messageId,
		);

		/** @var Http::STATUS_OK|Http::STATUS_ACCEPTED|Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE $statusCode */
		$statusCode = $proxy->getStatusCode();

		if ($statusCode !== Http::STATUS_OK && $statusCode !== Http::STATUS_ACCEPTED) {
			if (in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_FORBIDDEN,
				Http::STATUS_NOT_FOUND,
				Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			return new DataResponse([], $statusCode);
		}

		/** @var ?TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_ACCEPTED]);
		if (!empty($data)) {
			$data = $this->userConverter->convertAttendee($room, $data, 'actorType', 'actorId', 'actorDisplayName');
			$data = $this->userConverter->convertAttendee($room, $data, 'lastEditActorType', 'lastEditActorId', 'lastEditActorDisplayName');
		} else {
			$data = null;
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string) (int) $proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			$statusCode,
			$headers
		);
	}

	/**
	 * @see \OCA\Talk\Controller\ChatController::mentions()
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkChatMentionSuggestion[], array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: List of mention suggestions returned
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

		/** @var TalkChatMentionSuggestion[] $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertAttendees($room, $data, 'source', 'id', 'label');

		// FIXME post-load status information
		return new DataResponse($data, Http::STATUS_OK);
	}
}
