<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CallNotificationController extends OCSController {
	public const CASE_STILL_CURRENT = 0;
	public const CASE_ROOM_NOT_FOUND = 1;
	public const CASE_MISSED_CALL = 2;
	public const CASE_PARTICIPANT_JOINED = 3;


	public function __construct(
		string $appName,
		IRequest $request,
		protected ParticipantService $participantService,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check the expected state of a call notification
	 *
	 * Required capability: `call-notification-state-api`
	 *
	 * @param string $token Conversation token to check
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, null, array{}>
	 *
	 * 200: Notification should be kept alive
	 * 201: Dismiss call notification and show "Missed call"-notification instead
	 * 403: Not logged in, try again with auth data sent
	 * 404: Dismiss call notification
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['call'])]
	public function state(string $token): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(null, Http::STATUS_FORBIDDEN);
		}

		$status = match($this->participantService->checkIfUserIsMissingCall($token, $this->userId)) {
			self::CASE_PARTICIPANT_JOINED,
			self::CASE_ROOM_NOT_FOUND => Http::STATUS_NOT_FOUND,
			self::CASE_MISSED_CALL => Http::STATUS_CREATED,
			self::CASE_STILL_CURRENT => Http::STATUS_OK,
		};

		return new DataResponse(null, $status);
	}
}
