<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

/**
 * @psalm-import-type TalkSignalingSettings from ResponseDefinitions
 */
class SignalingController {
	public function __construct(
		protected ProxyRequest $proxy,
	) {
	}

	/**
	 * @see \OCA\Talk\Controller\SignalingController::getSettings()
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkSignalingSettings, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 * @throws CannotReachRemoteException
	 *
	 * 200: Signaling settings returned
	 * 404: Room not found
	 */
	public function getSettings(Room $room, Participant $participant): DataResponse {
		$proxy = $this->proxy->get(
			$participant->getAttendee()->getInvitedCloudId(),
			$participant->getAttendee()->getAccessToken(),
			$room->getRemoteServer() . '/ocs/v2.php/apps/spreed/api/v3/signaling/settings',
			[
				'token' => $room->getRemoteToken(),
			],
		);

		$statusCode = $proxy->getStatusCode();
		if (!in_array($statusCode, [Http::STATUS_OK, Http::STATUS_NOT_FOUND], true)) {
			$this->proxy->logUnexpectedStatusCode(__METHOD__, $proxy->getStatusCode());
			throw new CannotReachRemoteException();
		}

		/** @var TalkSignalingSettings|array<empty> $data */
		$data = $this->proxy->getOCSData($proxy);

		return new DataResponse($data, $statusCode);
	}
}
