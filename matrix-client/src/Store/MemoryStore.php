<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Store;

/** In-memory store for tests, scripts and the echo bot example. */
final class MemoryStore implements StoreInterface {
	/** @var array<string, array<string, string>> */
	private array $data = [];

	public function get(string $accountKey, string $name): ?string {
		return $this->data[$accountKey][$name] ?? null;
	}

	public function set(string $accountKey, string $name, ?string $value): void {
		if ($value === null) {
			unset($this->data[$accountKey][$name]);
			return;
		}
		$this->data[$accountKey][$name] = $value;
	}
}
