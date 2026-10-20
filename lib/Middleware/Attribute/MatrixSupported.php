<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;

/**
 * Moderator endpoint whose change is also applied to the Matrix room of
 * Matrix conversations, all other moderator endpoints are refused there
 */
#[Attribute(Attribute::TARGET_METHOD)]
class MatrixSupported {
}
