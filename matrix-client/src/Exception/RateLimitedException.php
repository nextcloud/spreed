<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Exception;

/** M_LIMIT_EXCEEDED – retry after `getRetryAfterMs()`. */
class RateLimitedException extends MatrixException {
	public function getRetryAfterMs(): int {
		return (int)($this->getBody()['retry_after_ms'] ?? 5000);
	}
}
