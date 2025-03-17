<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Middleware\Attribute\AllowWithoutParticipantWhenPendingInvitation;
use OCA\Talk\Middleware\Attribute\FederationSupported;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipantOrLoggedInAndListedConversation;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\DashboardService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Federation\ICloudIdManager;
use OCP\IAvatarManager;
use OCP\IL10N;
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
	 *
	 * Get rooms that have events in the next 7 days
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>|array{}, array{}>
	 *
	 * 200: rooms
	 */
	#[PublicPage]
	public function getCalendarRooms(): DataResponse {
		$user = $this->userSession->getUser()?->getUID();
		$rooms = $this->service->getItems($user);
		return new DataResponse($rooms);
	}
}
