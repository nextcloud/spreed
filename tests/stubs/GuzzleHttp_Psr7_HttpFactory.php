<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class HttpFactory implements RequestFactoryInterface, ResponseFactoryInterface, StreamFactoryInterface {
	public function createRequest(string $method, $uri): RequestInterface {
	}

	public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface {
	}

	public function createStream(string $content = ''): StreamInterface {
	}

	public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface {
	}

	public function createStreamFromResource($resource): StreamInterface {
	}
}
