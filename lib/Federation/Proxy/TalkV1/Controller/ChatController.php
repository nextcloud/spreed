<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\CachePrefix;
use OCA\Talk\Chat\Notifier;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ICache;
use OCP\ICacheFactory;

/**
 * @psalm-import-type TalkChatMentionSuggestion from ResponseDefinitions
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class ChatController {
	protected ?ICache $proxyCacheMessages;

	public function __construct(
		protected ProxyRequest $proxy,
		protected UserConverter $userConverter,
		protected ParticipantService $participantService,
		protected RoomFormatter $roomFormatter,
		protected Notifier $notifier,
		ICacheFactory $cacheFactory,
	) {
		$this->proxyCacheMessages = $cacheFactory->isAvailable() ? $cacheFactory->createDistributed(CachePrefix::FEDERATED_PCM) : null;
	}

	/**
	 * @see \OCA\Talk\Controller\ChatController::sendMessage()
	 *
	 * @return DataResponse<Http::STATUS_CREATED, ?TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_REQUEST_ENTITY_TOO_LARGE|Http::STATUS_TOO_MANY_REQUESTS, array{error: string}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 201: Message sent successfully
	 * 400: Sending message is not possible
	 * 404: Actor not found
	 * 413: Message too long
	 * 429: Mention rate limit exceeded (guests only)
	 */
	public function sendMessage(Room $room, Participant $participant, string $message, string $referenceId, int $replyTo, bool $silent, string $threadTitle, int $threadId): DataResponse {
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
				'threadTitle' => $threadTitle,
				'threadId' => $threadId,
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
			/** @var array{error: string} $data */
			$data = $this->proxy->getOCSData($proxy, [Http::STATUS_CREATED]);
			return new DataResponse($data, $statusCode);
		}

		/** @var ?TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_CREATED]);
		if (!empty($data)) {
			$data = $this->userConverter->convertMessage($room, $data);
		} else {
			$data = null;
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string)(int)$proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			Http::STATUS_CREATED,
			$headers,
		);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMessageWithParent>, array{'X-Chat-Last-Common-Read'?: numeric-string, X-Chat-Last-Given?: numeric-string}>|DataResponse<Http::STATUS_NOT_MODIFIED, null, array{}>
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
		$cacheKey = sha1(json_encode([$room->getRemoteServer(), $room->getRemoteToken()]));


		if ($lookIntoFuture && $markNotificationsAsRead && $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$this->notifier->markMentionNotificationsRead($room, $participant->getAttendee()->getActorId());
		}

		if ($lookIntoFuture) {
			if ($this->proxyCacheMessages instanceof ICache) {
				for ($i = 0; $i <= $timeout; $i++) {
					$cacheData = (int)$this->proxyCacheMessages->get($cacheKey);
					if ($lastKnownMessageId !== $cacheData) {
						break;
					}
					sleep(1);
				}
			} else {
				// Poor-mans timeout, should later on cancel/trigger earlier,
				// by checking the PCM database table
				sleep(max(0, $timeout - 5));
			}
		}

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

		if ($lookIntoFuture && $setReadMarker) {
			$this->participantService->updateUnreadInfoForProxyParticipant($participant,
				0,
				false,
				false,
				(int)($proxy->getHeader('X-Chat-Last-Given') ?: $lastKnownMessageId),
			);
		}

		if ($proxy->getStatusCode() === Http::STATUS_NOT_MODIFIED) {
			if ($lookIntoFuture && $this->proxyCacheMessages instanceof ICache) {
				$cacheData = $this->proxyCacheMessages->get($cacheKey);
				if ($cacheData === null || $cacheData < $lastKnownMessageId) {
					$this->proxyCacheMessages->set($cacheKey, $lastKnownMessageId, 300);
				}
			}
			return new DataResponse(null, Http::STATUS_NOT_MODIFIED);
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string)(int)$proxy->getHeader('X-Chat-Last-Common-Read');
		}
		if ($proxy->getHeader('X-Chat-Last-Given')) {
			$headers['X-Chat-Last-Given'] = (string)(int)$proxy->getHeader('X-Chat-Last-Given');
			if ($lookIntoFuture && $this->proxyCacheMessages instanceof ICache) {
				$cacheData = $this->proxyCacheMessages->get($cacheKey);
				if ($cacheData === null || $cacheData < $headers['X-Chat-Last-Given']) {
					$this->proxyCacheMessages->set($cacheKey, (int)$headers['X-Chat-Last-Given'], 300);
				}
			}
		}

		/** @var list<TalkChatMessageWithParent> $data */
		$data = $this->proxy->getOCSData($proxy);
		/** @var list<TalkChatMessageWithParent> $data */
		$data = $this->userConverter->convertMessages($room, $data);

		return new DataResponse($data, Http::STATUS_OK, $headers);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMessageWithParent>, array{'X-Chat-Last-Common-Read'?: numeric-string, X-Chat-Last-Given?: numeric-string}>|DataResponse<Http::STATUS_NOT_MODIFIED, null, array{}>
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

		if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			$this->notifier->markMentionNotificationsRead($room, $participant->getAttendee()->getActorId());
		}

		if ($proxy->getStatusCode() === Http::STATUS_NOT_MODIFIED) {
			return new DataResponse(null, Http::STATUS_NOT_MODIFIED);
		}

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string)(int)$proxy->getHeader('X-Chat-Last-Common-Read');
		}
		if ($proxy->getHeader('X-Chat-Last-Given')) {
			$headers['X-Chat-Last-Given'] = (string)(int)$proxy->getHeader('X-Chat-Last-Given');
		}

		/** @var list<TalkChatMessageWithParent> $data */
		$data = $this->proxy->getOCSData($proxy);
		/** @var list<TalkChatMessageWithParent> $data */
		$data = $this->userConverter->convertMessages($room, $data);

		return new DataResponse($data, Http::STATUS_OK, $headers);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array{error: string}, array{}>
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

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK && $statusCode !== Http::STATUS_ACCEPTED) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_FORBIDDEN,
				Http::STATUS_NOT_FOUND,
				Http::STATUS_METHOD_NOT_ALLOWED,
				Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
				$data = ['error' => 'status'];
			} elseif ($statusCode === Http::STATUS_BAD_REQUEST) {
				/** @var array{error: string} $data */
				$data = $this->proxy->getOCSData($proxy, [Http::STATUS_BAD_REQUEST]);
			} else {
				$data = [];
			}
			return new DataResponse($data, $statusCode);
		}

		/** @var TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_ACCEPTED]);
		$data = $this->userConverter->convertMessage($room, $data);

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string)(int)$proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			$statusCode,
			$headers,
		);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, TalkChatMessageWithParent, array{X-Chat-Last-Common-Read?: numeric-string}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_METHOD_NOT_ALLOWED, array{error: string}, array{}>
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

		/** @var TalkChatMessageWithParent $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_ACCEPTED]);
		$data = $this->userConverter->convertMessage($room, $data);

		$headers = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$headers['X-Chat-Last-Common-Read'] = (string)(int)$proxy->getHeader('X-Chat-Last-Common-Read');
		}

		return new DataResponse(
			$data,
			$statusCode,
			$headers,
		);
	}

	/**
	 * @see \OCA\Talk\Controller\ChatController::setReadMarker()
	 *
	 * @param 'json'|'xml' $responseFormat
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{X-Chat-Last-Common-Read?: numeric-string}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: List of mention suggestions returned
	 */
	public function setReadMarker(Room $room, Participant $participant, string $responseFormat, ?int $lastReadMessage): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/read',
			$lastReadMessage !== null ? [
				'lastReadMessage' => $lastReadMessage,
			] : [],
		);

		/** @var TalkRoom $data */
		$data = $this->proxy->getOCSData($proxy);

		$this->participantService->updateUnreadInfoForProxyParticipant(
			$participant,
			$data['unreadMessages'],
			$data['unreadMention'],
			$data['unreadMentionDirect'],
			$data['lastReadMessage'],
		);

		$headers = $lastCommonRead = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$lastCommonRead[$room->getId()] = (int)$proxy->getHeader('X-Chat-Last-Common-Read');
			$headers['X-Chat-Last-Common-Read'] = (string)$lastCommonRead[$room->getId()];
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$responseFormat,
			$lastCommonRead,
			$room,
			$participant,
		), Http::STATUS_OK, $headers);
	}

	/**
	 * @see \OCA\Talk\Controller\ChatController::markUnread()
	 *
	 * @param 'json'|'xml' $responseFormat
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{X-Chat-Last-Common-Read?: numeric-string}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: List of mention suggestions returned
	 */
	public function markUnread(Room $room, Participant $participant, string $responseFormat): DataResponse {
		$proxy = $this->proxy->delete(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/read',
		);

		/** @var TalkRoom $data */
		$data = $this->proxy->getOCSData($proxy);

		$this->participantService->updateUnreadInfoForProxyParticipant(
			$participant,
			$data['unreadMessages'],
			$data['unreadMention'],
			$data['unreadMentionDirect'],
			$data['lastReadMessage'],
		);

		$headers = $lastCommonRead = [];
		if ($proxy->getHeader('X-Chat-Last-Common-Read')) {
			$lastCommonRead[$room->getId()] = (int)$proxy->getHeader('X-Chat-Last-Common-Read');
			$headers['X-Chat-Last-Common-Read'] = (string)$lastCommonRead[$room->getId()];
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$responseFormat,
			$lastCommonRead,
			$room,
			$participant,
		), Http::STATUS_OK, $headers);
	}

	/**
	 * @see \OCA\Talk\Controller\ChatController::mentions()
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkChatMentionSuggestion>, array{}>
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

		/** @var list<TalkChatMentionSuggestion> $data */
		$data = $this->proxy->getOCSData($proxy);
		/** @var list<TalkChatMentionSuggestion> $data */
		$data = $this->userConverter->convertAttendees($room, $data, 'source', 'id', 'label');

		// FIXME post-load status information
		return new DataResponse($data, Http::STATUS_OK);
	}
}
