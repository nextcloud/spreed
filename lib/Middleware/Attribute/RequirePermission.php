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
 * @see InjectionMiddleware::checkPermission()
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RequirePermission {

	public const CHAT = 'chat';
	public const START_CALL = 'call-start';

	public function __construct(
		protected string $permission,
	) {
	}

	public function getPermission(): string {
		return $this->permission;
	}
}
