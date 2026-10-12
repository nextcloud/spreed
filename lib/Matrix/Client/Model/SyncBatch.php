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
	 * @param array<string, list<Event>> $invited Stripped state of the rooms the user is invited to, indexed by room id
	 * @param array<string, array<string, mixed>> $accountData Content of the changed global account data, indexed by type
	 * @param list<Event> $toDevice Events sent directly to the device
	 * @param list<string> $changedDevices Users whose devices changed
	 * @param ?int $oneTimeKeyCount Unused one-time keys of the device on the homeserver
	 * @param ?list<string> $unusedFallbackKeyTypes Algorithms with an unused fallback key, null when the homeserver does not tell
	 */
	public function __construct(
		public readonly string $nextBatch,
		public readonly array $joined = [],
		public readonly array $left = [],
		public readonly array $invited = [],
		public readonly array $accountData = [],
		public readonly array $toDevice = [],
		public readonly array $changedDevices = [],
		public readonly ?int $oneTimeKeyCount = null,
		public readonly ?array $unusedFallbackKeyTypes = null,
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
		$invited = [];
		foreach ((is_array($raw['rooms']['invite'] ?? null) ? $raw['rooms']['invite'] : []) as $roomId => $room) {
			$invited[(string)$roomId] = [];
			foreach (is_array($room['invite_state']['events'] ?? null) ? $room['invite_state']['events'] : [] as $event) {
				if (is_array($event)) {
					$invited[(string)$roomId][] = Event::fromArray($event);
				}
			}
		}

		$accountData = [];
		foreach (is_array($raw['account_data']['events'] ?? null) ? $raw['account_data']['events'] : [] as $event) {
			if (is_string($event['type'] ?? null) && is_array($event['content'] ?? null)) {
				$accountData[$event['type']] = $event['content'];
			}
		}

		$toDevice = [];
		foreach (is_array($raw['to_device']['events'] ?? null) ? $raw['to_device']['events'] : [] as $event) {
			if (is_array($event)) {
				$toDevice[] = Event::fromArray($event);
			}
		}
		$changedDevices = self::strings($raw['device_lists']['changed'] ?? []);
		$fallbackKeyTypes = is_array($raw['device_unused_fallback_key_types'] ?? null) ? self::strings($raw['device_unused_fallback_key_types']) : null;

		return new self(
			(string)($raw['next_batch'] ?? ''),
			$joined,
			$left,
			$invited,
			$accountData,
			$toDevice,
			$changedDevices,
			isset($raw['device_one_time_keys_count']['signed_curve25519']) ? (int)$raw['device_one_time_keys_count']['signed_curve25519'] : null,
			$fallbackKeyTypes,
		);
	}

	/** @return list<string> */
	private static function strings(mixed $values): array {
		$strings = [];
		foreach (is_array($values) ? $values : [] as $value) {
			if (is_string($value)) {
				$strings[] = $value;
			}
		}
		return $strings;
	}

	public function isEmpty(): bool {
		return $this->joined === [] && $this->left === [] && $this->invited === [] && $this->accountData === []
			&& $this->toDevice === [] && $this->changedDevices === [];
	}
}
