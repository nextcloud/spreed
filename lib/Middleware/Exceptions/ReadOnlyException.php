<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Exceptions;

use OCP\AppFramework\Http;

class ReadOnlyException extends \Exception {
	public function __construct() {
		parent::__construct('The conversation is read-only', Http::STATUS_FORBIDDEN);
	}
}
