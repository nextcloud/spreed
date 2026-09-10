<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/** One entry of `rooms.join` in a /sync response. */
final class JoinedRoom {
	/**
	 * @param list<Event> $state
	 * @param list<Event> $timeline
	 * @param list<Event> $ephemeral
	 * @param list<Event> $accountData
	 * @param array<string, mixed> $summary
	 */
	public function __construct(
		public readonly string $roomId,
		public readonly array $state,
		public readonly array $timeline,
		public readonly bool $limited,
		public readonly ?string $prevBatch,
		public readonly array $ephemeral,
		public readonly array $accountData,
		public readonly array $summary,
		public readonly int $notificationCount,
		public readonly int $highlightCount,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(string $roomId, array $raw): self {
		$map = static fn (array $list) => array_map(static fn (array $e) => Event::fromArray($e, $roomId), array_values(array_filter($list, 'is_array')));
		$timeline = is_array($raw['timeline'] ?? null) ? $raw['timeline'] : [];
		return new self(
			$roomId,
			$map(is_array($raw['state']['events'] ?? null) ? $raw['state']['events'] : []),
			$map(is_array($timeline['events'] ?? null) ? $timeline['events'] : []),
			(bool)($timeline['limited'] ?? false),
			isset($timeline['prev_batch']) ? (string)$timeline['prev_batch'] : null,
			$map(is_array($raw['ephemeral']['events'] ?? null) ? $raw['ephemeral']['events'] : []),
			$map(is_array($raw['account_data']['events'] ?? null) ? $raw['account_data']['events'] : []),
			is_array($raw['summary'] ?? null) ? $raw['summary'] : [],
			(int)($raw['unread_notifications']['notification_count'] ?? 0),
			(int)($raw['unread_notifications']['highlight_count'] ?? 0),
		);
	}

	/** State events in order: full state block first, then state events in the timeline. */
	public function getStateEvents(): array {
		$events = $this->state;
		foreach ($this->timeline as $event) {
			if ($event->isState()) {
				$events[] = $event;
			}
		}
		return $events;
	}

	/** @return list<string> Heroes from the room summary (m.heroes) */
	public function getHeroes(): array {
		$heroes = $this->summary['m.heroes'] ?? [];
		return is_array($heroes) ? array_values(array_filter($heroes, 'is_string')) : [];
	}

	public function getJoinedMemberCount(): ?int {
		return isset($this->summary['m.joined_member_count']) ? (int)$this->summary['m.joined_member_count'] : null;
	}

	public function getInvitedMemberCount(): ?int {
		return isset($this->summary['m.invited_member_count']) ? (int)$this->summary['m.invited_member_count'] : null;
	}
}
