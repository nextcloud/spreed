<?php
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Http\Client;

use OCP\Http\Client\IResponse;
use Psr\Http\Message\ResponseInterface;

class Response implements IResponse {
	public function __construct(ResponseInterface $response, $stream = false) {
	}

	public function getBody() {
	}

	public function getStatusCode(): int {
	}

	public function getHeader(string $key): string {
	}

	public function getHeaders(): array {
	}
}
