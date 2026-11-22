<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Resync members and roles of one team room after a change in Circles.
 *
 * Queued by TeamRoomListener instead of syncing inside the Circles event:
 * Circles is still in the middle of its change (promoteToOwner() even holds
 * a row lock), and some changes fire no event at all (new owner after the
 * owner left), so the job reads the final state from Circles afterwards.
 *
 * @psalm-type ResyncTeamRoomArgument = array{teamId: string}
 */
class ResyncTeamRoom extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		protected readonly Manager $manager,
		protected readonly TeamRoomSyncService $syncService,
		protected readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param ResyncTeamRoomArgument $argument
	 */
	#[\Override]
	protected function run($argument): void {
		$room = $this->manager->getTeamRoom($argument['teamId']);
		if (is_null($room)) {
			return;
		}

		try {
			$this->syncService->resyncRoom($room, true);
		} catch (\Exception $e) {
			// The hourly ResyncTeamRooms catches up
			$this->logger->info('Circles App unavailable, skipped syncing team room ' . $room->getToken(), ['exception' => $e]);
		}
	}
}
