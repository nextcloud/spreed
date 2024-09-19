<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling\Responses;

/**
 * Error:
 * ```
 * {
 *   "code": "error-code",
 *   "message": "Human readable error.",
 *   "details": {
 *     ...optional-details-object...
 *   }
 * }
 * ```
 */
final class DialOutError {
	public function __construct(
		/** @var non-empty-string */
		public ?string $code,
		public ?string $message = null,
		/** @var ?array{attendeeId: int} */
		public ?array $details = null,
	) {
	}
}
