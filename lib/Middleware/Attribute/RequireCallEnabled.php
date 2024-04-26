<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;
use OCA\Talk\Middleware\CanUseTalkMiddleware;

/**
 * Attribute to check limit endpoint access when the app config start_calls is not enabled
 * @see CanUseTalkMiddleware::beforeController()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireCallEnabled {
}
