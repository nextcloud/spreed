<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;

/**
 * Endpoint that the external call service can use on its own conversations by
 * signing the request, acting as the owner given in the `owner` parameter
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AllowExternalCallService {
}
