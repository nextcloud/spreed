<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Exceptions;

use OCP\AppFramework\Http;

class FederationUnsupportedFeatureException extends \Exception {
	public function __construct(
	) {
		parent::__construct('Feature is unsupported for federation', Http::STATUS_UPGRADE_REQUIRED);
	}
}
