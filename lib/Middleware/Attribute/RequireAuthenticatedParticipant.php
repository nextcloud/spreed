<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;
use OCA\Talk\Middleware\InjectionMiddleware;

/**
 * Allows logged-in users and federated participants
 * @see InjectionMiddleware::getLoggedIn()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireAuthenticatedParticipant extends RequireParticipant {
}
