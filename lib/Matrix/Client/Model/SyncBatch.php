<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * A parsed /sync response
 */
final class SyncBatch {
	/**
	 * @param array<string, JoinedRoom> $joined
	 * @param list<string> $left Ids of rooms the user left or was removed from
	 */
	public function __construct(
		public readonly string $nextBatch,
		public readonly array $joined = [],
		public readonly array $left = [],
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		$joined = [];
		foreach ((is_array($raw['rooms']['join'] ?? null) ? $raw['rooms']['join'] : []) as $roomId => $room) {
			if (is_array($room)) {
				$joined[(string)$roomId] = JoinedRoom::fromArray((string)$roomId, $room);
			}
		}
		$left = is_array($raw['rooms']['leave'] ?? null) ? array_map('strval', array_keys($raw['rooms']['leave'])) : [];

		return new self((string)($raw['next_batch'] ?? ''), $joined, $left);
	}

	public function isEmpty(): bool {
		return $this->joined === [] && $this->left === [];
	}
}
