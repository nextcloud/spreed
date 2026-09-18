<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Model\Event;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Mapping\EventMapper;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * Fetches older history via /messages: the initial window after a room is
 * discovered, gaps after a `limited` sync, and on demand when a client
 * scrolls past the oldest mirrored message.
 */
class BackfillService {
	public function __construct(
		private readonly ClientFactory $clientFactory,
		private readonly RoomStateApplier $stateApplier,
		private readonly EventMapper $eventMapper,
		private readonly EventMapMapper $eventMapMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixConfig $config,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Backfill the initial history window (or until $maxEvents / the start of the room).
	 *
	 * @param string|null $from pagination token; null = stored prev_batch
	 * @return int number of messages mirrored
	 */
	public function backfill(Account $account, MatrixRoom $matrixRoom, ?string $from = null, int $maxEvents = 0, int $budgetSeconds = 20): int {
		$room = $this->stateApplier->findTalkRoom($matrixRoom);
		if ($room === null) {
			return 0;
		}
		$from ??= $matrixRoom->getPrevBatch();
		if ($from === null) {
			return 0;
		}
		$isInitialWindow = $maxEvents === 0;
		$maxEvents = $maxEvents > 0 ? $maxEvents : $this->config->getHistoryEvents();
		$minTs = $this->config->getHistoryDays() > 0 ? ($this->timeFactory->getTime() - $this->config->getHistoryDays() * 86400) * 1000 : 0;

		$client = $this->clientFactory->forAccount($account, 20);
		$start = microtime(true);
		$mirrored = 0;
		$seen = $this->eventMapMapper->countForRoom($matrixRoom->getMatrixRoomId());
		$token = $from;
		$reachedLimit = false;

		try {
			while ($token !== null && (microtime(true) - $start) < $budgetSeconds) {
				$page = $client->getMessages($matrixRoom->getMatrixRoomId(), $token, 'b', 100, null, ['lazy_load_members' => true]);
				if ($page->chunk === []) {
					$token = null;
					break;
				}
				$senders = array_unique(array_map(static fn (Event $e) => $e->sender, $page->chunk));
				$accounts = $this->accountMapper->getByMxids($senders);
				foreach ($page->chunk as $event) {
					if ($event->isState()) {
						continue;
					}
					if ($isInitialWindow && ($event->originServerTs < $minTs || $seen >= $maxEvents)) {
						$reachedLimit = true;
						break;
					}
					$new = $this->eventMapper->ingest($room, $matrixRoom, $event, $accounts, true, $account);
					$mirrored += $new;
					$seen += $new;
				}
				if ($reachedLimit) {
					$token = null;
					break;
				}
				$token = $page->end;
			}
		} catch (MatrixException $e) {
			$this->logger->warning('Matrix backfill failed for ' . $matrixRoom->getMatrixRoomId() . ': ' . $e->getMessage());
			return $mirrored;
		}

		if ($isInitialWindow) {
			// null token = start of room or window limit reached; otherwise remember where to continue
			$matrixRoom->setPrevBatch($token);
			$matrixRoom->setBackfillDone($token === null && !$reachedLimit);
			$this->roomMapper->update($matrixRoom);
		}
		return $mirrored;
	}

	/** Whether the initial window (events × days) has not been reached yet. */
	public function wantsMoreInitialHistory(MatrixRoom $matrixRoom): bool {
		return $this->eventMapMapper->countForRoom($matrixRoom->getMatrixRoomId()) < $this->config->getHistoryEvents();
	}

	/**
	 * Client scrolled to the oldest mirrored message: fetch one more page inline.
	 */
	public function loadOlder(Account $account, MatrixRoom $matrixRoom, int $budgetSeconds = 3): int {
		if ($matrixRoom->getBackfillDone() || $matrixRoom->getPrevBatch() === null) {
			return 0;
		}
		return $this->backfill($account, $matrixRoom, null, 100, $budgetSeconds);
	}
}
