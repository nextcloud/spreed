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
	/** Level of users without special rights, the spec default for users and messages */
	public const LEVEL_USER = 0;
	/** Level of moderators, the spec default for state events, kicking, banning and redacting */
	public const LEVEL_MODERATOR = 50;
	/** Level of administrators and of the room creator while the room has no power levels */
	public const LEVEL_ADMIN = 100;
	/** Level of the room creators since room version 12, above every other level */
	public const LEVEL_CREATOR = PHP_INT_MAX;

	/**
	 * @param array<string, mixed> $content
	 * @param string $creator Has the admin level while the room has no power levels
	 * @param list<string> $creators Creators since room version 12, which have the creator level and are not part of the content
	 */
	public function __construct(
		private readonly array $content,
		private readonly string $creator = '',
		private readonly array $creators = [],
	) {
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		return $this->content;
	}

	public function getUserLevel(string $userId): int {
		if (in_array($userId, $this->creators, true)) {
			return self::LEVEL_CREATOR;
		}
		$users = is_array($this->content['users'] ?? null) ? $this->content['users'] : [];
		if (isset($users[$userId])) {
			return (int)$users[$userId];
		}
		if ($this->content === [] && $userId === $this->creator) {
			return self::LEVEL_ADMIN;
		}
		return $this->getDefaultUserLevel();
	}

	public function getDefaultUserLevel(): int {
		return (int)($this->content['users_default'] ?? self::LEVEL_USER);
	}

	public function canSendEvent(string $userId, string $eventType, bool $isState = false): bool {
		$events = is_array($this->content['events'] ?? null) ? $this->content['events'] : [];
		if (isset($events[$eventType])) {
			$required = (int)$events[$eventType];
		} elseif ($isState) {
			$required = (int)($this->content['state_default'] ?? ($this->content === [] ? self::LEVEL_USER : self::LEVEL_MODERATOR));
		} else {
			$required = (int)($this->content['events_default'] ?? self::LEVEL_USER);
		}
		return $this->getUserLevel($userId) >= $required;
	}

	/**
	 * Whether the user may redact events of other users
	 */
	public function canRedact(string $userId): bool {
		return $this->getUserLevel($userId) >= (int)($this->content['redact'] ?? self::LEVEL_MODERATOR);
	}

	public function canInvite(string $userId): bool {
		return $this->getUserLevel($userId) >= (int)($this->content['invite'] ?? self::LEVEL_USER);
	}

	/**
	 * Whether the user may remove the target, which needs a lower level
	 */
	public function canKick(string $userId, string $targetId): bool {
		$level = $this->getUserLevel($userId);
		return $level >= (int)($this->content['kick'] ?? self::LEVEL_MODERATOR) && $level > $this->getUserLevel($targetId);
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
		if ($content === [] && $this->creator !== '' && $this->creators === []) {
			$users[$this->creator] = self::LEVEL_ADMIN;
		}
		$users[$userId] = $level;
		$content['users'] = $users;
		return $content;
	}
}
