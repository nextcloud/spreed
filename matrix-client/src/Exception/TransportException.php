<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Exception;

/** The homeserver could not be reached or returned something that is not a Matrix response. */
class TransportException extends MatrixException {
}
