<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
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
 * @psalm-import-type TalkRoom from ResponseDefinitions
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
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>, array{}>
	 *
	 * 200: A list of rooms or an empty array
	 */
	#[NoAdminRequired]
	public function getEventRooms(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		$participants = $this->service->getEvents($userId);
		$rooms = [];
		foreach ($participants as $participant) {
			try {
				$rooms[] = $this->formatter->formatRoom($this->getResponseFormat(),
					[],
					$participant->getRoom(),
					$participant,
				);
			} catch (ParticipantNotFoundException) {
				// for example in case the room was deleted concurrently,
				// the user is not a participant anymore
			}
		}
		return new DataResponse($rooms);
	}
}
