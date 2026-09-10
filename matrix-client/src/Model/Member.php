<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

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
		public readonly ?string $avatarUrl = null,
		public readonly ?string $reason = null,
		public readonly string $sender = '',
		public readonly int $originServerTs = 0,
	) {
	}

	public static function fromEvent(Event $event): self {
		$content = $event->content;
		return new self(
			(string)$event->stateKey,
			(string)($content['membership'] ?? self::LEAVE),
			is_string($content['displayname'] ?? null) ? $content['displayname'] : null,
			is_string($content['avatar_url'] ?? null) ? $content['avatar_url'] : null,
			is_string($content['reason'] ?? null) ? $content['reason'] : null,
			$event->sender,
			$event->originServerTs,
		);
	}

	public function isJoined(): bool {
		return $this->membership === self::JOIN;
	}

	public function isInvited(): bool {
		return $this->membership === self::INVITE;
	}

	/** Display name with the user id as fallback, never empty. */
	public function getName(): string {
		$name = trim((string)$this->displayName);
		return $name !== '' ? $name : $this->userId;
	}
}
