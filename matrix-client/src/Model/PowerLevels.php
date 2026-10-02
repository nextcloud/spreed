<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/**
 * Wrapper around `m.room.power_levels` content with the spec defaults applied.
 */
final class PowerLevels {
	public const DEFAULT_MODERATOR = 50;
	public const DEFAULT_ADMIN = 100;

	/** @param array<string, mixed> $content */
	public function __construct(
		private readonly array $content = [],
		private readonly string $creator = '',
	) {
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return $this->content;
	}

	public function getUserLevel(string $userId): int {
		$users = $this->content['users'] ?? [];
		if (is_array($users) && isset($users[$userId])) {
			return (int)$users[$userId];
		}
		if ($this->content === [] && $userId !== '' && $userId === $this->creator) {
			// No power levels event: the creator has level 100, everyone else 0
			return self::DEFAULT_ADMIN;
		}
		return (int)($this->content['users_default'] ?? 0);
	}

	public function getEventLevel(string $eventType, bool $isState): int {
		$events = $this->content['events'] ?? [];
		if (is_array($events) && isset($events[$eventType])) {
			return (int)$events[$eventType];
		}
		if ($isState) {
			return (int)($this->content['state_default'] ?? 50);
		}
		return (int)($this->content['events_default'] ?? 0);
	}

	public function getActionLevel(string $action): int {
		$defaults = ['invite' => 0, 'kick' => 50, 'ban' => 50, 'redact' => 50];
		return (int)($this->content[$action] ?? $defaults[$action] ?? 50);
	}

	public function getNotificationLevel(string $key = 'room'): int {
		$n = $this->content['notifications'] ?? [];
		return (int)(is_array($n) ? ($n[$key] ?? 50) : 50);
	}

	public function canSendEvent(string $userId, string $eventType, bool $isState = false): bool {
		return $this->getUserLevel($userId) >= $this->getEventLevel($eventType, $isState);
	}

	public function canSendMessage(string $userId): bool {
		return $this->canSendEvent($userId, 'm.room.message');
	}

	public function canDo(string $userId, string $action): bool {
		return $this->getUserLevel($userId) >= $this->getActionLevel($action);
	}

	/** Whether $userId may kick/ban $targetId (must also outrank the target). */
	public function canActOn(string $userId, string $targetId, string $action): bool {
		return $this->canDo($userId, $action) && $this->getUserLevel($userId) > $this->getUserLevel($targetId);
	}

	public function canChangeUserLevel(string $userId, string $targetId, int $newLevel): bool {
		$own = $this->getUserLevel($userId);
		if ($own < $this->getEventLevel('m.room.power_levels', true)) {
			return false;
		}
		if ($newLevel > $own) {
			return false;
		}
		$target = $this->getUserLevel($targetId);
		return $userId === $targetId || $target < $own;
	}

	public function isModerator(string $userId): bool {
		return $this->getUserLevel($userId) >= self::DEFAULT_MODERATOR;
	}

	public function isAdmin(string $userId): bool {
		return $this->getUserLevel($userId) >= self::DEFAULT_ADMIN;
	}

	/** @return array<string, mixed> New content granting $targetId the given level. */
	public function withUserLevel(string $targetId, ?int $level): array {
		$content = $this->content;
		if (!isset($content['users']) || !is_array($content['users'])) {
			$content['users'] = [];
		}
		if ($level === null || $level === (int)($content['users_default'] ?? 0)) {
			unset($content['users'][$targetId]);
		} else {
			$content['users'][$targetId] = $level;
		}
		if ($content['users'] === []) {
			$content['users'] = new \stdClass();
		}
		return $content;
	}
}
