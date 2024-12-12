<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions;

class PollPropertyException extends \InvalidArgumentException {
	public const REASON_DRAFT = 'draft';
	public const REASON_POLL = 'poll';
	public const REASON_QUESTION = 'question';
	public const REASON_OPTIONS = 'options';
	public const REASON_ROOM = 'room';

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
