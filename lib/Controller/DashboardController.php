<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\DashboardService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class DashboardController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
		protected DashboardService $service,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get rooms that have events in the next 7 days
	 * sorted by their start timestamp ascending
	 *
	 * Required capability: `dashboard`
	 *
	 * @return DataResponse<Http::STATUS_OK, list<?TalkRoom>, array{}>
	 *
	 * 200: A list of rooms or an empty array
	 */
	#[PublicPage]
	#[BruteForceProtection(action: 'dashboard#getEventRooms')]
	public function getEventRooms(): DataResponse {
		$user = $this->userSession->getUser()?->getUID();
		$rooms = $this->service->getItems($user);
		return new DataResponse($rooms);
	}
}
