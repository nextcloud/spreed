<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class NetworkException extends \RuntimeException implements NetworkExceptionInterface {
	public function __construct(private readonly RequestInterface $request) {
		parent::__construct('connection refused');
	}

	public function getRequest(): RequestInterface {
		return $this->request;
	}
}
