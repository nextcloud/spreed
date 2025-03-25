<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Manager;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class MaximumCallDuration extends TimedJob {
	public function __construct(
		private IAppConfig $appConfig,
		private Manager $manager,
		private RoomService $roomService,
		private ParticipantService $participantService,
		ITimeFactory $time,
	) {
		parent::__construct($time);

		// Every time the jobs run
		$this->setInterval(1);
	}

	#[\Override]
	protected function run($argument): void {
		$maxCallDuration = $this->appConfig->getAppValueInt('max_call_duration');
		if ($maxCallDuration <= 0) {
			return;
		}

		$now = $this->time->getDateTime();
		$maxActiveSince = $now->sub(new \DateInterval('PT' . $maxCallDuration . 'S'));
		$rooms = $this->manager->getRoomsLongerActiveSince($maxActiveSince);

		foreach ($rooms as $room) {
			if ($room->isFederatedConversation()) {
				continue;
			}

			$result = $this->roomService->resetActiveSinceInDatabaseOnly($room);
			if (!$result) {
				// Someone else won the race condition, make sure this user disconnects directly and then return
				continue;
			}

			$this->participantService->endCallForEveryone($room, null);
			$this->roomService->resetActiveSinceInModelOnly($room);
		}
	}
}
