<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Exception;

/** M_LIMIT_EXCEEDED – the homeserver rate limits the requests, e.g. logins per IP address */
class RateLimitedException extends MatrixException {
	/**
	 * Milliseconds to wait before trying again, null when the homeserver does not say
	 */
	public function getRetryAfterMs(): ?int {
		$retryAfter = $this->getBody()['retry_after_ms'] ?? null;
		return is_int($retryAfter) && $retryAfter >= 0 ? $retryAfter : null;
	}

	/**
	 * Seconds to wait before trying again, rounded up, null when the homeserver does not say
	 */
	public function getRetryAfterSeconds(): ?int {
		$retryAfterMs = $this->getRetryAfterMs();
		return $retryAfterMs === null ? null : (int)ceil($retryAfterMs / 1000);
	}
}
