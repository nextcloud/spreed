<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Exception;

/** M_FORBIDDEN – missing power level, not a member, wrong password, … */
class ForbiddenException extends MatrixException {
}
