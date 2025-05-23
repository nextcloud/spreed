<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Attribute;

use Attribute;
use OCA\Talk\Middleware\InjectionMiddleware;
use OCP\AppFramework\Http\Attribute\RequestHeader;

/**
 * @see InjectionMiddleware::getRoom()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class FederationSupported extends RequestHeader {
	public function __construct() {
		parent::__construct(
			'x-nextcloud-federation',
			'Set to 1 when the request is performed by another Nextcloud Server to indicate a federation request'
		);
	}
}
