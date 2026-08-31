<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use Nextcloud\Matrix\Client;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\RateLimitedException;
use Nextcloud\Matrix\Exception\TransportException;
use Nextcloud\Matrix\Exception\UnknownTokenException;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * Runs /sync for one account under a per-account lock and feeds the batches
 * into the {@see IngestionPipeline}. Used by the background job and by the
 * opportunistic foreground trigger.
 */
class SyncService {
	public const LOCK_SECONDS = 60;

	public function __construct(
		private readonly AccountMapper $accountMapper,
		private readonly AccountService $accountService,
		private readonly ClientFactory $clientFactory,
		private readonly IngestionPipeline $pipeline,
		private readonly MatrixConfig $config,
		private readonly \OCA\Talk\Matrix\Service\CryptoService $cryptoService,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Sync one account until the budget is used up or the server has nothing new.
	 *
	 * @param int $budgetSeconds wall-clock budget for this call
	 * @param int $longPollMs how long the homeserver may hold a request (0 = return immediately)
	 * @return array{batches: int, messages: int, rooms: int}|null null when the lock was not available
	 */
	public function syncAccount(Account $account, int $budgetSeconds = 25, int $longPollMs = 0): ?array {
		if (!$account->isActive()) {
			return null;
		}
		$now = $this->timeFactory->getDateTime();
		$until = (clone $now)->modify('+' . (self::LOCK_SECONDS + $budgetSeconds) . ' seconds');
		if (!$this->accountMapper->acquireLock($account, $now, $until)) {
			return null;
		}

		$start = microtime(true);
		$stats = ['batches' => 0, 'messages' => 0, 'rooms' => 0];
		try {
			$homeserver = $this->clientFactory->getHomeserver($account->getHomeserverId());
			// Initial syncs of large accounts are big responses: give the HTTP call its own generous timeout
			$client = $this->clientFactory->forAccount($account, 60);
			$client->getTransport()->setMaxRetries(0);
			$filterId = $this->accountService->ensureFilter($account);
			if ($homeserver->getAllowE2ee() && ($account->getOlmAccount() === null || $account->getOlmAccount() === '')) {
				// Linked before E2EE support (or bootstrap failed at link time): create + publish the device keys now
				$this->cryptoService->bootstrap($account);
				$account = $this->accountMapper->getById($account->getId());
			}

			do {
				$since = $account->getNextBatch();
				$initial = $since === null || $since === '';
				$remaining = $budgetSeconds - (microtime(true) - $start);
				$timeout = $initial ? 0 : (int)min($longPollMs, max(0, ($remaining - 2) * 1000));
				$batch = $client->sync($since, $filterId !== '' ? $filterId : Client::defaultFilter(), $timeout);
				$stats['batches']++;

				$result = $this->pipeline->process($account, $homeserver, $batch, $initial);
				$stats['messages'] += $result['messages'];
				$stats['rooms'] += $result['rooms'];

				if ($result['failed'] > 0 && $initial) {
					// The initial batch carries every room exactly once: do not skip past it while rooms fail
					$this->recordError($account, $result['failed'] . ' room(s) failed during the initial sync, see the log; the sync will be retried');
					break;
				}
				$account->setNextBatch($batch->nextBatch);
				$account->setLastSync($this->timeFactory->getDateTime());
				$account->setLastError($result['failed'] > 0 ? $result['failed'] . ' room(s) failed to sync, see the log' : null);
				if ($result['messages'] > 0 || $result['invites'] > 0) {
					$account->setLastActivity($this->timeFactory->getDateTime());
				}
				$this->accountMapper->update($account);

				$progressed = $batch->nextBatch !== $since && !$batch->isEmpty();
			} while ($progressed && (microtime(true) - $start) < $budgetSeconds);
		} catch (UnknownTokenException $e) {
			$this->logger->warning('Matrix token of ' . $account->getMxid() . ' rejected: ' . $e->getMessage());
			$this->accountService->markTokenInvalid($account, $e->getMessage());
		} catch (RateLimitedException $e) {
			$this->recordError($account, 'Rate limited by homeserver, retry in ' . $e->getRetryAfterMs() . 'ms');
		} catch (TransportException|MatrixException|DoesNotExistException $e) {
			$this->recordError($account, $e->getMessage());
		} catch (\Throwable $e) {
			$this->logger->error('Matrix sync crashed for ' . $account->getMxid() . ': ' . $e->getMessage(), ['exception' => $e]);
			$this->recordError($account, $e->getMessage());
		} finally {
			$this->accountMapper->releaseLock($account);
		}
		return $stats;
	}

	/**
	 * Called from request handling when a client looks at a Matrix
	 * conversation: sync inline if the account's data is older than the
	 * configured age. Cheap when the lock is taken or data is fresh.
	 */
	public function foregroundSync(Room $room, ?string $userId, int $maxSeconds = 3): void {
		if ($userId === null || !$room->isMatrixConversation()) {
			return;
		}
		$account = $this->accountService->getForUser($userId);
		if ($account === null || !$account->isActive()) {
			return;
		}
		$lastSync = $account->getLastSync();
		if ($lastSync !== null && ($this->timeFactory->getTime() - $lastSync->getTimestamp()) < $this->config->getForegroundSyncAge()) {
			return;
		}
		$this->syncAccount($account, $maxSeconds, 0);
	}

	/**
	 * Accounts that are due: active accounts whose last sync is older than the
	 * (idle) interval.
	 * @return list<Account>
	 */
	public function getDueAccounts(int $limit): array {
		$now = $this->timeFactory->getDateTime();
		$active = (clone $now)->modify('-' . $this->config->getSyncInterval() . ' seconds');
		$idle = (clone $now)->modify('-' . $this->config->getIdleSyncInterval() . ' seconds');
		$idleThreshold = (clone $now)->modify('-10 minutes');
		$due = [];
		foreach ($this->accountMapper->getDueForSync($active, $limit * 2) as $account) {
			$lastActivity = $account->getLastActivity();
			$isIdle = $lastActivity === null || $lastActivity < $idleThreshold;
			$lastSync = $account->getLastSync();
			if ($isIdle && $lastSync !== null && $lastSync > $idle) {
				continue; // idle account, not yet due for the slower cadence
			}
			$due[] = $account;
			if (count($due) >= $limit) {
				break;
			}
		}
		return $due;
	}

	/** Forget the sync position so the next sync is a full initial sync again. */
	public function resetSyncToken(Account $account): void {
		$account->setNextBatch(null);
		$account->setLastError(null);
		$this->accountMapper->update($account);
	}

	private function recordError(Account $account, string $message): void {
		$account->setLastError(mb_substr($message, 0, 1000));
		$account->setLastSync($this->timeFactory->getDateTime());
		$this->accountMapper->update($account);
	}
}
