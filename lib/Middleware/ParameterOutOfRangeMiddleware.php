<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Middleware;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ParameterOutOfRangeException;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCSController;

class ParameterOutOfRangeMiddleware extends Middleware {
	/**
	 * @throws \Exception
	 */
	#[\Override]
	public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
		if ($exception instanceof ParameterOutOfRangeException
			&& $controller instanceof OCSController) {
			return new DataResponse([
				'error' => $exception->getParameterName(),
			], Http::STATUS_BAD_REQUEST);
		}

		throw $exception;
	}
}
