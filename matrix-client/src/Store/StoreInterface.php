<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Store;

/**
 * Persistence the library needs from its host. Phase 1 only needs per-account
 * key/value storage (sync token, filter id); the E2EE phase adds typed
 * accessors for device keys and Olm/Megolm sessions.
 */
interface StoreInterface {
	public function get(string $accountKey, string $name): ?string;

	public function set(string $accountKey, string $name, ?string $value): void;
}
