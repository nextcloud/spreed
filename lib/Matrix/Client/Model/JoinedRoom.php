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
	 * @param bool $limited Whether events before the timeline were left out
	 * @param ?string $prevBatch Pagination token for the events before the timeline
	 * @param array<string, list<string>> $readReceipts Matrix user ids that read up to an event, indexed by event id
	 */
	public function __construct(
		public readonly string $roomId,
		public readonly array $stateEvents,
		public readonly array $timeline = [],
		public readonly array $heroes = [],
		public readonly ?int $joinedMemberCount = null,
		public readonly ?int $invitedMemberCount = null,
		public readonly bool $limited = false,
		public readonly ?string $prevBatch = null,
		public readonly array $readReceipts = [],
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

		$readReceipts = [];
		foreach (is_array($raw['ephemeral']['events'] ?? null) ? $raw['ephemeral']['events'] : [] as $event) {
			if (!is_array($event) || ($event['type'] ?? null) !== 'm.receipt' || !is_array($event['content'] ?? null)) {
				continue;
			}
			foreach ($event['content'] as $eventId => $receipts) {
				foreach (is_array($receipts['m.read'] ?? null) ? $receipts['m.read'] : [] as $userId => $receipt) {
					// Receipts inside of threads do not mark the main timeline as read
					if (($receipt['thread_id'] ?? 'main') === 'main') {
						$readReceipts[(string)$eventId][] = (string)$userId;
					}
				}
			}
		}

		return new self(
			$roomId,
			$stateEvents,
			$timeline,
			$heroes,
			isset($summary['m.joined_member_count']) ? (int)$summary['m.joined_member_count'] : null,
			isset($summary['m.invited_member_count']) ? (int)$summary['m.invited_member_count'] : null,
			($raw['timeline']['limited'] ?? false) === true,
			is_string($raw['timeline']['prev_batch'] ?? null) ? $raw['timeline']['prev_batch'] : null,
			$readReceipts,
		);
	}
}
