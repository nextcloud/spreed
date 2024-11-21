<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions\ParticipantProperty;

class PermissionsException extends \InvalidArgumentException {
	public const REASON_METHOD = 'method';
	public const REASON_MODERATOR = 'moderator';
	public const REASON_ROOM_TYPE = 'room-type';
	public const REASON_TYPE = 'type';
	public const REASON_VALUE = 'value';

	/**
	 * @param self::REASON_* $reason
	 */
	public function __construct(
		protected string $reason,
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
