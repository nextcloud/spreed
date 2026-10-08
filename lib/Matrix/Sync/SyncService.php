<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\UnknownTokenException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * Runs /sync for one account under a per-account lock and applies the
 * batches to the mirrored conversations
 */
class SyncService {
	/** Seconds between two syncs of an account */
	public const SYNC_INTERVAL = 30;
	/** Seconds a sync lock outlives the sync budget, in case the process dies */
	public const LOCK_SECONDS = 60;

	/**
	 * Members are lazy-loaded and an initial sync only brings the latest
	 * messages of each room
	 */
	public const FILTER = [
		'presence' => ['types' => []],
		'account_data' => ['types' => []],
		'room' => [
			'state' => ['lazy_load_members' => true],
			'timeline' => ['limit' => 10, 'lazy_load_members' => true],
			'ephemeral' => ['types' => []],
			'account_data' => ['types' => []],
		],
	];

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly AccountService $accountService,
		private readonly RoomSyncService $roomSyncService,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/** @return list<Account> */
	public function getDueAccounts(int $limit): array {
		return $this->accountMapper->getDueForSync($this->timeFactory->getTime() - self::SYNC_INTERVAL, $limit);
	}

	/**
	 * Sync until the budget is used up or the homeserver has nothing new
	 *
	 * @param int $budget Seconds to spend at most, a running request is not interrupted
	 * @return array{batches: int, rooms: int, messages: int, failed: int}|null null when the account is not active or another process syncs it
	 */
	public function syncAccount(Account $account, int $budget): ?array {
		if ($account->getStatus() !== Account::STATUS_ACTIVE) {
			return null;
		}
		$start = $this->timeFactory->getTime();
		if (!$this->accountMapper->acquireLock($account, $start, $start + $budget + self::LOCK_SECONDS)) {
			return null;
		}

		$stats = ['batches' => 0, 'rooms' => 0, 'messages' => 0, 'failed' => 0];
		try {
			$client = $this->accountService->getClient($account, 60);
			if ($account->getFilterId() === null) {
				$account->setFilterId($client->createFilter($account->getMxid(), self::FILTER));
				$this->accountMapper->update($account);
			}

			do {
				$since = $account->getNextBatch() ?? '';
				$batch = $client->sync($since, (string)$account->getFilterId());
				$result = $this->roomSyncService->process($account, $batch, $since === '');
				$stats['batches']++;
				$stats['rooms'] += $result['rooms'];
				$stats['messages'] += $result['messages'];
				$stats['failed'] += $result['failed'];

				if ($since === '' && $result['failed'] > 0) {
					// The initial sync contains every room only once, so it is repeated until all rooms worked
					$this->recordError($account, $result['failed'] . ' rooms failed in the initial sync, see the log');
					break;
				}

				$account->setNextBatch($batch->nextBatch);
				$account->setLastSync($this->timeFactory->getTime());
				$account->setLastError($result['failed'] > 0 ? $result['failed'] . ' rooms failed to sync, see the log' : null);
				$this->accountMapper->update($account);
			} while (!$batch->isEmpty() && $this->timeFactory->getTime() - $start < $budget);
		} catch (UnknownTokenException $e) {
			$this->accountService->markTokenInvalid($account, $e->getMessage());
		} catch (MatrixException|DoesNotExistException $e) {
			$this->recordError($account, $e->getMessage());
		} catch (\Throwable $e) {
			$this->logger->error('Matrix sync failed for ' . $account->getMxid(), ['exception' => $e]);
			$this->recordError($account, $e->getMessage());
		} finally {
			$this->accountMapper->releaseLock($account);
		}
		return $stats;
	}

	/**
	 * Forget the sync position and filter, so the next sync is an initial sync
	 */
	public function resetSyncPosition(Account $account): void {
		$account->setNextBatch(null);
		$account->setFilterId(null);
		$this->accountMapper->update($account);
	}

	protected function recordError(Account $account, string $message): void {
		$account->setLastError(mb_substr($message, 0, 1000));
		$account->setLastSync($this->timeFactory->getTime());
		$this->accountMapper->update($account);
	}
}
