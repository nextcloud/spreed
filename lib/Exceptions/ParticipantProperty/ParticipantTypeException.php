<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions\ParticipantProperty;

class ParticipantTypeException extends \InvalidArgumentException {
	public const REASON_ACTOR_TYPE = 'actor-type';
	public const REASON_LAST_MODERATOR = 'last-moderator';
	public const REASON_MODERATOR = 'moderator';
	public const REASON_PARTICIPANT_TYPE = 'participant-type';
	public const REASON_ROOM_TYPE = 'room-type';
	public const REASON_SELF = 'self';
	public const REASON_TYPE = 'type';

	/**
	 * @param self::REASON_* $reason
	 */
	public function __construct(
		private readonly string $reason,
	) {
		parent::__construct($reason);
	}

	/**
	 * @return self::REASON_*
	 */
	public function getReason(): string {
		return $this->reason;
	}
}
