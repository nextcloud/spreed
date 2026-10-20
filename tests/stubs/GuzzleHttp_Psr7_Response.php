<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface {
	/**
	 * @param array<string, string|string[]> $headers
	 * @param string|resource|StreamInterface|null $body
	 */
	public function __construct(
		int $status = 200,
		array $headers = [],
		$body = null,
		string $version = '1.1',
		?string $reason = null,
	) {
	}
}
