<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Talk\Exceptions;

use OCP\AppFramework\QueryException;
use Psr\Container\ContainerExceptionInterface;

/**
 * An exception thrown to bail out of registering Talk modules such as the dashboard widget
 */
class NotAllowedToUseTalkException extends QueryException implements ContainerExceptionInterface {
	public function __construct(int $code = 0, \Throwable $parent = null) {
		parent::__construct('Can not use talk', $code, $parent);
	}
}
