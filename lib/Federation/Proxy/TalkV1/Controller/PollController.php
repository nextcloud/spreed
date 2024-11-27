<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\PollPropertyException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkPoll from ResponseDefinitions
 * @psalm-import-type TalkPollDraft from ResponseDefinitions
 */
class PollController {
	public function __construct(
		protected ProxyRequest $proxy,
		protected UserConverter $userConverter,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, list<TalkPollDraft>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Polls returned
	 * 404: Polls not found
	 *
	 * @see \OCA\Talk\Controller\PollController::showPoll()
	 */
	public function getDraftsForRoom(Room $room, Participant $participant): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken() . '/drafts',
		);

		$status = $proxy->getStatusCode();
		if ($status === Http::STATUS_NOT_FOUND || $status === Http::STATUS_FORBIDDEN) {
			return new DataResponse([], $status);
		}

		/** @var list<TalkPollDraft> $list */
		$list = $this->proxy->getOCSData($proxy);

		$data = [];
		foreach ($list as $poll) {
			$data[] = $this->userConverter->convertPoll($room, $poll);
		}

		return new DataResponse($data);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Poll returned
	 * 404: Poll not found
	 *
	 * @see \OCA\Talk\Controller\PollController::showPoll()
	 */
	public function showPoll(Room $room, Participant $participant, int $pollId): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken() . '/' . $pollId,
		);

		if ($proxy->getStatusCode() === Http::STATUS_NOT_FOUND) {
			/** @var array{error?: string} $data */
			$data = $this->proxy->getOCSData($proxy);
			return new DataResponse(['error' => $data['error'] ?? 'poll'], Http::STATUS_NOT_FOUND);
		}

		/** @var TalkPoll $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertPoll($room, $data);

		return new DataResponse($data);
	}

	/**
	 * @param list<int> $optionIds
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Voted successfully
	 * 400: Voting is not possible
	 * 404: Poll not found
	 *
	 * @see \OCA\Talk\Controller\PollController::votePoll()
	 */
	public function votePoll(Room $room, Participant $participant, int $pollId, array $optionIds): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken() . '/' . $pollId,
			['optionIds' => $optionIds],
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			/** @var array{error?: string} $data */
			$data = $this->proxy->getOCSData($proxy);
			return new DataResponse(['error' => $data['error'] ?? 'poll'], $statusCode);
		}

		/** @var TalkPoll $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertPoll($room, $data);

		return new DataResponse($data);
	}


	/**
	 * @return DataResponse<Http::STATUS_OK, TalkPollDraft, array{}>|DataResponse<Http::STATUS_CREATED, TalkPoll, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'draft'|'options'|'poll'|'question'|'room'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Draft created successfully
	 * 201: Poll created successfully
	 * 400: Creating poll is not possible
	 *
	 * @see \OCA\Talk\Controller\PollController::createPoll()
	 */
	public function createPoll(Room $room, Participant $participant, string $question, array $options, int $resultMode, int $maxVotes, bool $draft): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken(),
			[
				'question' => $question,
				'options' => $options,
				'resultMode' => $resultMode,
				'maxVotes' => $maxVotes,
				'draft' => $draft,
			],
		);

		$status = $proxy->getStatusCode();
		if ($status === Http::STATUS_BAD_REQUEST) {
			$data = $this->proxy->getOCSData($proxy, [Http::STATUS_BAD_REQUEST]);
			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}

		/** @var TalkPoll $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_CREATED]);
		$data = $this->userConverter->convertPoll($room, $data);

		if ($status === Http::STATUS_OK) {
			return new DataResponse($data);
		}
		return new DataResponse($data, Http::STATUS_CREATED);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, TalkPollDraft, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{error: 'draft'|'options'|'poll'|'question'|'room'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Draft created successfully
	 * 201: Poll created successfully
	 * 400: Creating poll is not possible
	 *
	 * @see \OCA\Talk\Controller\PollController::createPoll()
	 */
	public function updateDraftPoll(int $pollId, Room $room, Participant $participant, string $question, array $options, int $resultMode, int $maxVotes): DataResponse {
		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken() . '/draft/' . $pollId,
			[
				'question' => $question,
				'options' => $options,
				'resultMode' => $resultMode,
				'maxVotes' => $maxVotes
			],
		);

		$status = $proxy->getStatusCode();
		if ($status === Http::STATUS_BAD_REQUEST) {
			$data = $this->proxy->getOCSData($proxy, [Http::STATUS_BAD_REQUEST]);
			return new DataResponse($data, Http::STATUS_BAD_REQUEST);
		}

		/** @var TalkPollDraft $data */
		$data = $this->proxy->getOCSData($proxy, [Http::STATUS_OK, Http::STATUS_CREATED]);
		$data = $this->userConverter->convertPoll($room, $data);

		if ($status === Http::STATUS_OK) {
			return new DataResponse($data);
		}
		return new DataResponse($data);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, TalkPoll, array{}>|DataResponse<Http::STATUS_ACCEPTED, null, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, array{error: 'poll'}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Poll closed successfully
	 * 400: Poll already closed
	 * 403: Missing permissions to close poll
	 * 404: Poll not found
	 *
	 * @see \OCA\Talk\Controller\PollController::closePoll()
	 */
	public function closePoll(Room $room, Participant $participant, int $pollId): DataResponse {
		$proxy = $this->proxy->delete(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v1/poll/' . $room->getRemoteToken() . '/' . $pollId,
		);

		$statusCode = $proxy->getStatusCode();
		if ($statusCode !== Http::STATUS_OK) {
			if (!in_array($statusCode, [
				Http::STATUS_BAD_REQUEST,
				Http::STATUS_FORBIDDEN,
				Http::STATUS_NOT_FOUND,
			], true)) {
				$statusCode = $this->proxy->logUnexpectedStatusCode(__METHOD__, $statusCode);
			}
			/** @var array{error?: string} $data */
			$data = $this->proxy->getOCSData($proxy);

			if ($data['error'] !== PollPropertyException::REASON_POLL) {
				$this->logger->error('Unhandled error in ' . __METHOD__ . ': ' . $data['error']);
			}

			return new DataResponse(['error' => PollPropertyException::REASON_POLL], $statusCode);
		}

		/** @var TalkPoll $data */
		$data = $this->proxy->getOCSData($proxy);
		$data = $this->userConverter->convertPoll($room, $data);

		return new DataResponse($data);
	}
}
