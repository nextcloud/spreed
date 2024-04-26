<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware\Exceptions;

use OCP\AppFramework\Http;

class UnsupportedClientVersionException extends \Exception {
	public function __construct(
		protected string $version,
	) {
		parent::__construct('Unsupported client version', Http::STATUS_UPGRADE_REQUIRED);
	}

	public function getMinVersion(): string {
		return $this->version;
	}
}
