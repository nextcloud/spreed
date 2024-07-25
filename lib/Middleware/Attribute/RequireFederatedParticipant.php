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
 * @see InjectionMiddleware::getLoggedInOrGuest()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequireFederatedParticipant extends RequireParticipant {
	public function __construct(
		protected ?string $sessionIdParameter = 'sessionId',
	) {
	}

	/**
	 * On some federated requests the sessionId of the federated_user is included
	 * and should be used to inject a participant with the correct session object
	 */
	public function getSessionIdParameter(): ?string {
		return $this->sessionIdParameter;
	}
}
