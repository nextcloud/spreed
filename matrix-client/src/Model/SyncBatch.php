<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/**
 * A parsed /sync response. Deliberately decoupled from the transport so that
 * other sources (Application Service transactions, a sync worker) can feed the
 * same consumers.
 */
final class SyncBatch {
	/**
	 * @param array<string, JoinedRoom> $joined
	 * @param array<string, InvitedRoom> $invited
	 * @param array<string, LeftRoom> $left
	 * @param list<array<string, mixed>> $toDevice
	 * @param list<Event> $accountData
	 * @param list<string> $deviceListsChanged
	 * @param list<string> $deviceListsLeft
	 * @param array<string, int> $oneTimeKeysCount
	 */
	public function __construct(
		public readonly string $nextBatch,
		public readonly array $joined = [],
		public readonly array $invited = [],
		public readonly array $left = [],
		public readonly array $toDevice = [],
		public readonly array $accountData = [],
		public readonly array $deviceListsChanged = [],
		public readonly array $deviceListsLeft = [],
		public readonly array $oneTimeKeysCount = [],
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		$joined = $invited = $left = [];
		foreach ((is_array($raw['rooms']['join'] ?? null) ? $raw['rooms']['join'] : []) as $roomId => $room) {
			if (is_array($room)) {
				$joined[(string)$roomId] = JoinedRoom::fromArray((string)$roomId, $room);
			}
		}
		foreach ((is_array($raw['rooms']['invite'] ?? null) ? $raw['rooms']['invite'] : []) as $roomId => $room) {
			if (is_array($room)) {
				$invited[(string)$roomId] = InvitedRoom::fromArray((string)$roomId, $room);
			}
		}
		foreach ((is_array($raw['rooms']['leave'] ?? null) ? $raw['rooms']['leave'] : []) as $roomId => $room) {
			if (is_array($room)) {
				$left[(string)$roomId] = LeftRoom::fromArray((string)$roomId, $room);
			}
		}
		$accountData = [];
		foreach ((is_array($raw['account_data']['events'] ?? null) ? $raw['account_data']['events'] : []) as $event) {
			if (is_array($event)) {
				$accountData[] = Event::fromArray($event);
			}
		}
		return new self(
			(string)($raw['next_batch'] ?? ''),
			$joined,
			$invited,
			$left,
			array_values(array_filter(is_array($raw['to_device']['events'] ?? null) ? $raw['to_device']['events'] : [], 'is_array')),
			$accountData,
			array_values(array_filter(is_array($raw['device_lists']['changed'] ?? null) ? $raw['device_lists']['changed'] : [], 'is_string')),
			array_values(array_filter(is_array($raw['device_lists']['left'] ?? null) ? $raw['device_lists']['left'] : [], 'is_string')),
			array_map('intval', is_array($raw['device_one_time_keys_count'] ?? null) ? $raw['device_one_time_keys_count'] : []),
		);
	}

	public function isEmpty(): bool {
		return $this->joined === [] && $this->invited === [] && $this->left === [] && $this->toDevice === [] && $this->accountData === [];
	}

	/** Content of a global account data event (e.g. m.direct, m.push_rules), or null. */
	public function getAccountData(string $type): ?array {
		foreach ($this->accountData as $event) {
			if ($event->type === $type) {
				return $event->content;
			}
		}
		return null;
	}

	/**
	 * `m.direct` inverted: room id → list of user ids it is a DM with.
	 * @return array<string, list<string>>
	 */
	public function getDirectRooms(): array {
		$direct = $this->getAccountData('m.direct');
		if ($direct === null) {
			return [];
		}
		$result = [];
		foreach ($direct as $userId => $rooms) {
			if (!is_array($rooms)) {
				continue;
			}
			foreach ($rooms as $roomId) {
				if (is_string($roomId)) {
					$result[$roomId][] = (string)$userId;
				}
			}
		}
		return $result;
	}
}
