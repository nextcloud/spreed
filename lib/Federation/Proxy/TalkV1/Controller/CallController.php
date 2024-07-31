<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

/**
 * @psalm-import-type TalkCapabilities from ResponseDefinitions
 * @psalm-import-type TalkParticipant from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class CallController {
	public function __construct(
		protected ProxyRequest  $proxy,
		protected UserConverter $userConverter,
	) {
	}

	/**
	 * @see \OCA\Talk\Controller\RoomController::joinFederatedCall()
	 *
	 * @param Room $room the federated room to join the call in
	 * @param Participant $participant the federated user that will join the
	 *        call; the participant must have a session
	 * @param int<0, 15> $flags In-Call flags
	 * @psalm-param int-mask-of<Participant::FLAG_*> $flags
	 * @param bool $silent Join the call silently
	 * @param bool $recordingConsent Agreement to be recorded
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error?: string}, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Federated user is now in the call
	 * 400: Conditions to join not met
	 * 404: Room not found
	 */
	public function joinFederatedCall(Room $room, Participant $participant, int $flags, bool $silent, bool $recordingConsent): DataResponse {
		$options = [
			'sessionId' => $participant->getSession()->getSessionId(),
			'flags' => $flags,
			'silent' => $silent,
			'recordingConsent' => $recordingConsent,
		];

		$proxy = $this->proxy->post(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v4/call/' . $room->getRemoteToken() . '/federation',
			$options,
		);

		$statusCode = $proxy->getStatusCode();
		if (!in_array($statusCode, [Http::STATUS_OK, Http::STATUS_BAD_REQUEST, Http::STATUS_NOT_FOUND], true)) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode());
			throw new CannotReachRemoteException();
		}

		return new DataResponse([], $statusCode);
	}

	/**
	 * @see \OCA\Talk\Controller\RoomController::updateFederatedCallFlags()
	 *
	 * @param Room $room the federated room to update the call flags in
	 * @param Participant $participant the federated user to update the call
	 *        flags; the participant must have a session
	 * @param int<0, 15> $flags New flags
	 * @psalm-param int-mask-of<Participant::FLAG_*> $flags New flags
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: In-call flags updated successfully for federated user
	 * 400: Updating in-call flags is not possible
	 * 404: Room not found
	 */
	public function updateFederatedCallFlags(Room $room, Participant $participant, int $flags): DataResponse {
		$options = [
			'sessionId' => $participant->getSession()->getSessionId(),
			'flags' => $flags,
		];

		$proxy = $this->proxy->put(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v4/call/' . $room->getRemoteToken() . '/federation',
			$options,
		);

		$statusCode = $proxy->getStatusCode();
		if (!in_array($statusCode, [Http::STATUS_OK, Http::STATUS_BAD_REQUEST, Http::STATUS_NOT_FOUND], true)) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode());
			throw new CannotReachRemoteException();
		}

		return new DataResponse([], $statusCode);
	}

	/**
	 * @see \OCA\Talk\Controller\RoomController::leaveFederatedCall()
	 *
	 * @param Room $room the federated room to leave the call in
	 * @param Participant $participant the federated user that will leave the
	 *        call; the participant must have a session
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Federated user left the call
	 * 404: Room not found
	 */
	public function leaveFederatedCall(Room $room, Participant $participant): DataResponse {
		$options = [
			'sessionId' => $participant->getSession()->getSessionId(),
		];

		$proxy = $this->proxy->delete(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v4/call/' . $room->getRemoteToken() . '/federation',
			$options,
		);

		$statusCode = $proxy->getStatusCode();
		if (!in_array($statusCode, [Http::STATUS_OK, Http::STATUS_NOT_FOUND], true)) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode());
			throw new CannotReachRemoteException();
		}

		return new DataResponse([], $statusCode);
	}
}
