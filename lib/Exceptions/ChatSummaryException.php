<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Exceptions;

class ChatSummaryException extends \InvalidArgumentException {
	public const REASON_NO_PROVIDER = 'ai-no-provider';
	public const REASON_AI_ERROR = 'ai-error';
	public const REASON_CLASSIFIED = 'classified';

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
