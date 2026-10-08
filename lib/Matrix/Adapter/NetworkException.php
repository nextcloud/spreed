<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Adapter;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

class NetworkException extends \RuntimeException implements NetworkExceptionInterface {
	public function __construct(
		private readonly RequestInterface $request,
		string $message,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	#[\Override]
	public function getRequest(): RequestInterface {
		return $this->request;
	}
}
