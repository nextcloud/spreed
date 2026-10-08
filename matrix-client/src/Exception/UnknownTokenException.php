<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Exception;

/** The access token is invalid or was logged out (M_UNKNOWN_TOKEN) – the account needs a re-login. */
class UnknownTokenException extends MatrixException {
	public function isSoftLogout(): bool {
		return (bool)($this->getBody()['soft_logout'] ?? false);
	}
}
