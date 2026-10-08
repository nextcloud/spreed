<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * One entry of `rooms.join` in a /sync response
 */
final class JoinedRoom {
	/**
	 * @param list<Event> $stateEvents State block followed by the state events of the timeline
	 * @param list<Event> $timeline Events of the timeline that are not state events
	 * @param list<string> $heroes
	 */
	public function __construct(
		public readonly string $roomId,
		public readonly array $stateEvents,
		public readonly array $timeline = [],
		public readonly array $heroes = [],
		public readonly ?int $joinedMemberCount = null,
		public readonly ?int $invitedMemberCount = null,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(string $roomId, array $raw): self {
		$stateEvents = $timeline = [];
		foreach ([$raw['state']['events'] ?? null, $raw['timeline']['events'] ?? null] as $events) {
			foreach (is_array($events) ? $events : [] as $event) {
				if (is_array($event)) {
					$event = Event::fromArray($event);
					if ($event->isState()) {
						$stateEvents[] = $event;
					} else {
						$timeline[] = $event;
					}
				}
			}
		}

		$summary = is_array($raw['summary'] ?? null) ? $raw['summary'] : [];
		$heroes = [];
		foreach (is_array($summary['m.heroes'] ?? null) ? $summary['m.heroes'] : [] as $hero) {
			if (is_string($hero)) {
				$heroes[] = $hero;
			}
		}

		return new self(
			$roomId,
			$stateEvents,
			$timeline,
			$heroes,
			isset($summary['m.joined_member_count']) ? (int)$summary['m.joined_member_count'] : null,
			isset($summary['m.invited_member_count']) ? (int)$summary['m.invited_member_count'] : null,
		);
	}
}
