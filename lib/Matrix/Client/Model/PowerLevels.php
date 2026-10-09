<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * Content of m.room.power_levels with the defaults of the spec applied
 */
final class PowerLevels {
	/**
	 * @param array<string, mixed> $content
	 * @param string $creator Has level 100 when the room has no power levels yet
	 */
	public function __construct(
		private readonly array $content,
		private readonly string $creator = '',
	) {
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return $this->content;
	}

	public function getUserLevel(string $userId): int {
		$users = is_array($this->content['users'] ?? null) ? $this->content['users'] : [];
		if (isset($users[$userId])) {
			return (int)$users[$userId];
		}
		if ($this->content === [] && $userId === $this->creator) {
			return 100;
		}
		return (int)($this->content['users_default'] ?? 0);
	}

	public function canSendEvent(string $userId, string $eventType, bool $isState = false): bool {
		$events = is_array($this->content['events'] ?? null) ? $this->content['events'] : [];
		if (isset($events[$eventType])) {
			$required = (int)$events[$eventType];
		} elseif ($isState) {
			$required = (int)($this->content['state_default'] ?? ($this->content === [] ? 0 : 50));
		} else {
			$required = (int)($this->content['events_default'] ?? 0);
		}
		return $this->getUserLevel($userId) >= $required;
	}

	/**
	 * Whether the user may redact events of other users
	 */
	public function canRedact(string $userId): bool {
		return $this->getUserLevel($userId) >= (int)($this->content['redact'] ?? 50);
	}
}
