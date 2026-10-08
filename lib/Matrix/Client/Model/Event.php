<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * A room event (timeline or state)
 */
final class Event {
	/**
	 * @param array<string, mixed> $content
	 */
	public function __construct(
		public readonly string $eventId,
		public readonly string $type,
		public readonly string $sender,
		public readonly array $content,
		public readonly ?string $stateKey = null,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		return new self(
			(string)($raw['event_id'] ?? ''),
			(string)($raw['type'] ?? ''),
			(string)($raw['sender'] ?? ''),
			is_array($raw['content'] ?? null) ? $raw['content'] : [],
			array_key_exists('state_key', $raw) ? (string)$raw['state_key'] : null,
		);
	}

	public function isState(): bool {
		return $this->stateKey !== null;
	}
}
