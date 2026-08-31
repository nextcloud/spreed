<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/** Result of GET /rooms/{roomId}/messages. */
final class MessagesPage {
	/**
	 * @param list<Event> $chunk
	 * @param list<Event> $state
	 */
	public function __construct(
		public readonly array $chunk,
		public readonly array $state,
		public readonly string $start,
		public readonly ?string $end,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(string $roomId, array $raw): self {
		$map = static fn (array $list) => array_map(static fn (array $e) => Event::fromArray($e, $roomId), array_values(array_filter($list, 'is_array')));
		return new self(
			$map(is_array($raw['chunk'] ?? null) ? $raw['chunk'] : []),
			$map(is_array($raw['state'] ?? null) ? $raw['state'] : []),
			(string)($raw['start'] ?? ''),
			isset($raw['end']) ? (string)$raw['end'] : null,
		);
	}

	/** True when the start (or end) of the room history was reached. */
	public function isExhausted(): bool {
		return $this->end === null;
	}
}
