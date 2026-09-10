<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * All admin-configurable knobs of the Matrix integration (app config keys `matrix_*`).
 */
class MatrixConfig {
	public const ENABLED = 'matrix_enabled';
	public const ALLOWED_GROUPS = 'matrix_allowed_groups';
	public const SYNC_INTERVAL = 'matrix_sync_interval';
	public const IDLE_SYNC_INTERVAL = 'matrix_idle_sync_interval';
	public const MAX_PARALLEL_SYNCS = 'matrix_max_parallel_syncs';
	public const FOREGROUND_SYNC_AGE = 'matrix_foreground_sync_age';
	public const HISTORY_EVENTS = 'matrix_history_events';
	public const HISTORY_DAYS = 'matrix_history_days';
	public const MAX_UPLOAD = 'matrix_max_upload';
	public const TYPING_IN = 'matrix_typing_in';
	public const TYPING_OUT = 'matrix_typing_out';
	public const E2EE_SHARED_LOOKUP = 'matrix_e2ee_shared_lookup';
	public const E2EE_VERIFIED_ONLY = 'matrix_e2ee_verified_only';

	public const DEVICE_NAME_PREFIX = 'Nextcloud Talk';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getAppValueBool(self::ENABLED, false);
	}

	public function setEnabled(bool $enabled): void {
		$this->appConfig->setAppValueBool(self::ENABLED, $enabled);
	}

	/** @return list<string> */
	public function getAllowedGroupIds(): array {
		return array_values(array_filter($this->appConfig->getAppValueArray(self::ALLOWED_GROUPS), 'is_string'));
	}

	/** @param list<string> $groupIds */
	public function setAllowedGroupIds(array $groupIds): void {
		$this->appConfig->setAppValueArray(self::ALLOWED_GROUPS, array_values($groupIds));
	}

	/** Whether the user may link a Matrix account (feature on + group restriction). */
	public function canUserLink(?IUser $user): bool {
		if (!$user instanceof IUser || !$this->isEnabled()) {
			return false;
		}
		$allowed = $this->getAllowedGroupIds();
		if ($allowed === []) {
			return true;
		}
		return array_intersect($allowed, $this->groupManager->getUserGroupIds($user)) !== [];
	}

	/** Seconds between two syncs of an active account (10–300, default 30). */
	public function getSyncInterval(): int {
		return min(300, max(10, $this->appConfig->getAppValueInt(self::SYNC_INTERVAL, 30)));
	}

	/** Seconds between syncs of an account without recent activity (default 120). */
	public function getIdleSyncInterval(): int {
		return max($this->getSyncInterval(), $this->appConfig->getAppValueInt(self::IDLE_SYNC_INTERVAL, 120));
	}

	public function getMaxParallelSyncs(): int {
		return max(1, $this->appConfig->getAppValueInt(self::MAX_PARALLEL_SYNCS, 4));
	}

	/** Seconds after which a client request triggers an inline sync (default 5). */
	public function getForegroundSyncAge(): int {
		return max(1, $this->appConfig->getAppValueInt(self::FOREGROUND_SYNC_AGE, 5));
	}

	public function getHistoryEvents(): int {
		return max(0, $this->appConfig->getAppValueInt(self::HISTORY_EVENTS, 200));
	}

	public function getHistoryDays(): int {
		return max(0, $this->appConfig->getAppValueInt(self::HISTORY_DAYS, 30));
	}

	/** Bytes */
	public function getMaxUpload(): int {
		return max(0, $this->appConfig->getAppValueInt(self::MAX_UPLOAD, 100 * 1024 * 1024));
	}

	public function isTypingIncomingEnabled(): bool {
		return $this->appConfig->getAppValueBool(self::TYPING_IN, true);
	}

	public function isTypingOutgoingEnabled(): bool {
		return $this->appConfig->getAppValueBool(self::TYPING_OUT, false);
	}

	public function isE2eeSharedLookupEnabled(): bool {
		return $this->appConfig->getAppValueBool(self::E2EE_SHARED_LOOKUP, true);
	}

	public function isE2eeVerifiedOnly(): bool {
		return $this->appConfig->getAppValueBool(self::E2EE_VERIFIED_ONLY, false);
	}

	/** @return array<string, int|bool> all operational settings, for the admin UI */
	public function getOperationalSettings(): array {
		return [
			'syncInterval' => $this->getSyncInterval(),
			'idleSyncInterval' => $this->getIdleSyncInterval(),
			'maxParallelSyncs' => $this->getMaxParallelSyncs(),
			'foregroundSyncAge' => $this->getForegroundSyncAge(),
			'historyEvents' => $this->getHistoryEvents(),
			'historyDays' => $this->getHistoryDays(),
			'maxUpload' => $this->getMaxUpload(),
			'typingIn' => $this->isTypingIncomingEnabled(),
			'typingOut' => $this->isTypingOutgoingEnabled(),
			'e2eeSharedLookup' => $this->isE2eeSharedLookupEnabled(),
			'e2eeVerifiedOnly' => $this->isE2eeVerifiedOnly(),
		];
	}

	/**
	 * @param array<string, mixed> $settings subset of getOperationalSettings() keys
	 */
	public function setOperationalSettings(array $settings): void {
		$intKeys = [
			'syncInterval' => self::SYNC_INTERVAL,
			'idleSyncInterval' => self::IDLE_SYNC_INTERVAL,
			'maxParallelSyncs' => self::MAX_PARALLEL_SYNCS,
			'foregroundSyncAge' => self::FOREGROUND_SYNC_AGE,
			'historyEvents' => self::HISTORY_EVENTS,
			'historyDays' => self::HISTORY_DAYS,
			'maxUpload' => self::MAX_UPLOAD,
		];
		$boolKeys = [
			'typingIn' => self::TYPING_IN,
			'typingOut' => self::TYPING_OUT,
			'e2eeSharedLookup' => self::E2EE_SHARED_LOOKUP,
			'e2eeVerifiedOnly' => self::E2EE_VERIFIED_ONLY,
		];
		foreach ($intKeys as $name => $key) {
			if (array_key_exists($name, $settings)) {
				$this->appConfig->setAppValueInt($key, (int)$settings[$name]);
			}
		}
		foreach ($boolKeys as $name => $key) {
			if (array_key_exists($name, $settings)) {
				$this->appConfig->setAppValueBool($key, (bool)$settings[$name]);
			}
		}
	}
}
