<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix;

use OCP\AppFramework\Services\IAppConfig;

/**
 * Admin-configurable settings of the Matrix integration (app config keys `matrix_*`).
 */
class MatrixConfig {
	public const ENABLED = 'matrix_enabled';
	public const ALLOWED_GROUPS = 'matrix_allowed_groups';

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getAppValueBool(self::ENABLED, false);
	}

	public function setEnabled(bool $enabled): void {
		$this->appConfig->setAppValueBool(self::ENABLED, $enabled);
	}

	/** @return list<string> */
	public function getAllowedGroupIds(): array {
		return array_values(array_filter($this->appConfig->getAppValueArray(self::ALLOWED_GROUPS), 'is_string'));
	}

	/** @param list<string> $groupIds */
	public function setAllowedGroupIds(array $groupIds): void {
		$this->appConfig->setAppValueArray(self::ALLOWED_GROUPS, $groupIds);
	}
}
