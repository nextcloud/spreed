<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Signaling\Responses;

/**
 * Success:
 * ```
 * {
 *   "callid": "the-call-id"
 * }
 *  ```
 *
 * Error:
 * ```
 * "error": {
 *   "code": "error-code",
 *   "message": "Human readable error.",
 *   "details": {
 *     ...optional-details-object...
 *   }
 * }
 * ```
 */
final class DialOut {
	public function __construct(
		/** @var non-empty-string|null */
		public ?string $callId = null,
		public ?DialOutError $error = null,
	) {
	}
}
