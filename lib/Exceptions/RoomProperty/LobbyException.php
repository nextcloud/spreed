<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions\RoomProperty;

class LobbyException extends \InvalidArgumentException {
	public const REASON_BREAKOUT_ROOM = 'breakout-room';
	public const REASON_OBJECT = 'object';
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
