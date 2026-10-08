<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

final class Member {
	public const JOIN = 'join';
	public const LEAVE = 'leave';
	public const INVITE = 'invite';
	public const BAN = 'ban';
	public const KNOCK = 'knock';

	public function __construct(
		public readonly string $userId,
		public readonly string $membership,
		public readonly ?string $displayName = null,
	) {
	}

	public static function fromEvent(Event $event): self {
		return new self(
			(string)$event->stateKey,
			is_string($event->content['membership'] ?? null) ? $event->content['membership'] : self::LEAVE,
			is_string($event->content['displayname'] ?? null) ? $event->content['displayname'] : null,
		);
	}

	public function isJoined(): bool {
		return $this->membership === self::JOIN;
	}

	public function isInvited(): bool {
		return $this->membership === self::INVITE;
	}

	/** Display name with the user id as fallback, never empty */
	public function getName(): string {
		$name = trim((string)$this->displayName);
		return $name !== '' ? $name : $this->userId;
	}
}
