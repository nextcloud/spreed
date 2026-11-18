<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions\RoomProperty;

class UploadsWithoutStorageException extends \InvalidArgumentException {
	public const REASON_BREAKOUT_ROOM = 'breakout-room';
	public const REASON_FEDERATED = 'federated';
	public const REASON_MATRIX = 'matrix';
	public const REASON_STORAGE = 'storage';
	public const REASON_TYPE = 'type';
	public const REASON_UNAVAILABLE = 'unavailable';

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
