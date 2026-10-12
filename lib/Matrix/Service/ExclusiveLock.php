<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * Runs short changes exclusively across processes, waiting a moment when
 * another process holds the lock
 */
class ExclusiveLock {
	/** Attempts to get the lock, 100 milliseconds apart */
	public const ATTEMPTS = 20;

	public function __construct(
		private readonly ILockingProvider $lockingProvider,
	) {
	}

	/**
	 * @template T
	 * @param \Closure(): T $callback
	 * @return T
	 * @throws LockedException when the lock is still held after all attempts
	 */
	public function run(string $key, \Closure $callback): mixed {
		$path = 'talk/matrix/' . $key;
		for ($attempt = 1; ; $attempt++) {
			try {
				$this->lockingProvider->acquireLock($path, ILockingProvider::LOCK_EXCLUSIVE);
				break;
			} catch (LockedException $e) {
				if ($attempt >= self::ATTEMPTS) {
					throw $e;
				}
				usleep(100000);
			}
		}

		try {
			return $callback();
		} finally {
			$this->lockingProvider->releaseLock($path, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
