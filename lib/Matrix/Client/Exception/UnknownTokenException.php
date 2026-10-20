<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Exception;

/** M_UNKNOWN_TOKEN – the access token was invalidated or the device was logged out */
class UnknownTokenException extends MatrixException {
}
