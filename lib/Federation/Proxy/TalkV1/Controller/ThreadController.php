<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Chat\Notifier;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ICacheFactory;

/**
 * @psalm-import-type TalkThreadInfo from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class ThreadController {

	public function __construct(
		protected ProxyRequest $proxy,
		protected UserConverter $userConverter,
		protected ParticipantService $participantService,
		protected RoomFormatter $roomFormatter,
		protected Notifier $notifier,
		ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * @see \OCA\Talk\Controller\ThreadController::getRecentActiveThreads()
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkThreadInfo>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: List of threads returned
	 */
	public function getRecentActiveThreads(Room $room, Participant $participant, int $limit): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/threads/recent',
			[
				'limit' => $limit,
			],
		);

		/** @var list<TalkThreadInfo> $data */
		$data = $this->proxy->getOCSData($proxy);
		if (!empty($data)) {
			$data = $this->userConverter->convertThreadInfos($room, $data);
		}

		return new DataResponse($data);
	}

	/**
	 * @see \OCA\Talk\Controller\ThreadController::getThread()
	 *
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'thread'|'status'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Thread info returned
	 * 404: Thread not found
	 */
	public function getThread(Room $room, Participant $participant, int $threadId): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/threads/' . $threadId,
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if ($statusCode !== Http::STATUS_NOT_FOUND) {
				$this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
				$data = ['error' => 'status'];
			} else {
				/** @var array{error: 'thread'} $data */
				$data = $this->proxy->getOCSData($proxy, [Http::STATUS_NOT_FOUND]);
			}
			return new DataResponse($data, Http::STATUS_NOT_FOUND);
		}

		/** @var TalkThreadInfo $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertThreadInfo($room, $data);

		return new DataResponse($data, Http::STATUS_OK);
	}

	/**
	 * @see \OCA\Talk\Controller\ThreadController::setNotificationLevel()
	 *
	 * @psalm-param non-negative-int $threadId
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'title'}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'thread'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Thread renamed successfully
	 * 400: When the provided title is empty
	 * 404: Thread not found
	 */
	public function renameThread(Room $room, Participant $participant, int $threadId, string $threadTitle): DataResponse {
		$proxy = $this->proxy->put(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/threads/' . $threadId,
			['threadTitle' => $threadTitle],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
				$data = ['error' => 'thread'];
			} elseif ($statusCode === Http::STATUS_BAD_REQUEST) {
				/** @var array{error: 'title'} $data */
				$data = $this->proxy->getOCSData($proxy, [
					Http::STATUS_BAD_REQUEST,
				]);
			} else {
				/** @var array{error: 'thread'} $data */
				$data = $this->proxy->getOCSData($proxy, [
					Http::STATUS_NOT_FOUND,
				]);
			}
			return new DataResponse($data, $statusCode);
		}

		/** @var TalkThreadInfo $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertThreadInfo($room, $data);

		return new DataResponse($data, Http::STATUS_OK);
	}


	/**
	 * @see \OCA\Talk\Controller\ThreadController::setNotificationLevel()
	 *
	 * @psalm-param non-negative-int $messageId
	 * @psalm-param Participant::NOTIFY_* $level
	 * @return DataResponse<Http::STATUS_OK, TalkThreadInfo, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: 'level'|'message'|'status'|'top-most'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Successfully set notification level for thread
	 * 400: Notification level was invalid
	 * 404: Message or top most message not found
	 */
	public function setNotificationLevel(Room $room, Participant $participant, int $messageId, int $level): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/chat/' . $room->getRemoteToken() . '/threads/' . $messageId . '/notify',
			['level' => $level],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
				$data = ['error' => 'status'];
			} else {
				/** @var array{error: 'level'|'message'|'top-most'} $data */
				$data = $this->proxy->getOCSData($proxy, [
					Http::STATUS_BAD_REQUEST,
					Http::STATUS_NOT_FOUND,
				]);
			}
			return new DataResponse($data, $statusCode);
		}

		/** @var TalkThreadInfo $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertThreadInfo($room, $data);

		return new DataResponse($data, Http::STATUS_OK);
	}
}
