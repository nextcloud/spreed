<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;
use OCA\Talk\Middleware\InjectionMiddleware;

/**
 * @see InjectionMiddleware::getLoggedInOrGuest()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireParticipant extends RequireRoom {
}
