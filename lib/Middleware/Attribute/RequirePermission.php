<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
		protected string $permission
	) {
	}

	public function getPermission(): string {
		return $this->permission;
	}
}
