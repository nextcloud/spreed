<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/** One entry of `rooms.leave`. */
final class LeftRoom {
	/**
	 * @param list<Event> $state
	 * @param list<Event> $timeline
	 */
	public function __construct(
		public readonly string $roomId,
		public readonly array $state,
		public readonly array $timeline,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(string $roomId, array $raw): self {
		$map = static fn (array $list) => array_map(static fn (array $e) => Event::fromArray($e, $roomId), array_values(array_filter($list, 'is_array')));
		return new self(
			$roomId,
			$map(is_array($raw['state']['events'] ?? null) ? $raw['state']['events'] : []),
			$map(is_array($raw['timeline']['events'] ?? null) ? $raw['timeline']['events'] : []),
		);
	}

	/** Our own final membership event (leave/ban), if present. */
	public function getOwnMembershipEvent(string $userId): ?Event {
		$found = null;
		foreach ([...$this->state, ...$this->timeline] as $event) {
			if ($event->type === 'm.room.member' && $event->stateKey === $userId) {
				$found = $event;
			}
		}
		return $found;
	}
}
