<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\CalendarIntegrationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkDashboardEvent from ResponseDefinitions
 */
class CalendarIntegrationController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
		protected CalendarIntegrationService $service,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get up to 10 rooms that have events in the next 7 days
	 * sorted by their start timestamp ascending
	 *
	 * Required capability: `dashboard-event-rooms`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkDashboardEvent>, array{}>
	 *
	 * 200: A list of dashboard entries or an empty array
	 */
	#[NoAdminRequired]
	public function getDashboardEvents(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		$entries = $this->service->getDashboardEvents($userId);
		return new DataResponse($entries);
	}

	/**
	 * Get up to 3 events in the next 7 days
	 * sorted by their start timestamp ascending
	 *
	 * Required capability: `mutual-calendar-events`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkDashboardEvent>, array{}>|DataResponse<Http::STATUS_FORBIDDEN, null, array{}>
	 *
	 * 200: A list of dashboard entries or an empty array
	 * 403: Room is not a 1 to 1 room, room is invalid, or user is not participant
	 */
	#[NoAdminRequired]
	#[RequireParticipant]
	public function getMutualEvents(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		try {
			$entries = $this->service->getMutualEvents($userId, $this->room);
		} catch (InvalidRoomException|ParticipantNotFoundException) {
			return new DataResponse(null, Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($entries);
	}
}
