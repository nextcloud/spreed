<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\BackgroundJob;

use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\BackfillService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;

/**
 * Fetches the initial history window of a newly discovered room, or a gap
 * after a limited sync. Re-queues itself while the window is not complete.
 */
class MatrixBackfill extends QueuedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly BackfillService $backfillService,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly IJobList $jobList,
	) {
		parent::__construct($timeFactory);
	}

	/**
	 * @param array{accountId: int, matrixRoomId: string, from?: string, gap?: bool} $argument
	 */
	#[\Override]
	protected function run($argument): void {
		try {
			$account = $this->accountMapper->getById((int)$argument['accountId']);
			$matrixRoom = $this->roomMapper->getByMatrixRoomId((string)$argument['matrixRoomId']);
		} catch (DoesNotExistException) {
			return;
		}
		if (!$account->isActive()) {
			return;
		}
		if (!empty($argument['gap'])) {
			// Gap fill: one bounded pass from the given token
			$this->backfillService->backfill($account, $matrixRoom, (string)($argument['from'] ?? ''), 300, 20);
			return;
		}
		$this->backfillService->backfill($account, $matrixRoom, null, 0, 20);
		$matrixRoom = $this->roomMapper->getByMatrixRoomId($matrixRoom->getMatrixRoomId());
		if (!$matrixRoom->getBackfillDone() && $matrixRoom->getPrevBatch() !== null && $this->backfillService->wantsMoreInitialHistory($matrixRoom)) {
			$this->jobList->add(self::class, ['accountId' => $account->getId(), 'matrixRoomId' => $matrixRoom->getMatrixRoomId()]);
		}
	}
}
