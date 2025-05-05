<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\DashboardService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkDashboardEvent from ResponseDefinitions
 */
class DashboardController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
		protected DashboardService $service,
		protected ParticipantService $participantService,
		protected RoomFormatter $formatter,
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
	public function getEventRooms(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		$entries = $this->service->getEvents($userId);
		return new DataResponse($entries);
	}
}
