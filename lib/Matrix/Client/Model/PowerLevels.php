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

	public function canInvite(string $userId): bool {
		return $this->getUserLevel($userId) >= (int)($this->content['invite'] ?? 0);
	}

	/**
	 * Whether the user may remove the target, which needs a lower level
	 */
	public function canKick(string $userId, string $targetId): bool {
		$level = $this->getUserLevel($userId);
		return $level >= (int)($this->content['kick'] ?? 50) && $level > $this->getUserLevel($targetId);
	}

	/**
	 * Whether the user may give the target the level, which needs a lower
	 * level for the target and at most the own level for the new one
	 */
	public function canChangeUserLevel(string $userId, string $targetId, int $newLevel): bool {
		$level = $this->getUserLevel($userId);
		return $this->canSendEvent($userId, 'm.room.power_levels', true)
			&& $level > $this->getUserLevel($targetId)
			&& $newLevel <= $level;
	}

	/**
	 * Content with the level of the user changed
	 *
	 * @return array<string, mixed>
	 */
	public function withUserLevel(string $userId, int $level): array {
		$content = $this->content;
		$users = is_array($content['users'] ?? null) ? $content['users'] : [];
		if ($content === [] && $this->creator !== '') {
			$users[$this->creator] = 100;
		}
		$users[$userId] = $level;
		$content['users'] = $users;
		return $content;
	}
}
