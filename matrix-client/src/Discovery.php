<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix;

use Nextcloud\Matrix\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Server discovery (spec §3.4): resolve a server name like `example.org` to the
 * client API base URL via /.well-known/matrix/client, then validate it with
 * /_matrix/client/versions. Only run by administrators when configuring a
 * homeserver – never on behalf of end users.
 */
final class Discovery {
	public function __construct(
		private readonly ClientInterface $http,
		private readonly RequestFactoryInterface $requestFactory,
	) {
	}

	/**
	 * @return array{base_url: string, versions: array<string, mixed>}
	 */
	public function discover(string $serverName): array {
		$serverName = trim($serverName);
		if ($serverName === '' || preg_match('~[\s/]~', $serverName)) {
			throw new \InvalidArgumentException('Invalid server name');
		}
		$baseUrl = 'https://' . $serverName;

		try {
			$response = $this->http->sendRequest($this->requestFactory->createRequest('GET', $baseUrl . '/.well-known/matrix/client'));
			if ($response->getStatusCode() === 200) {
				$body = json_decode((string)$response->getBody(), true);
				$wellKnown = is_array($body) ? ($body['m.homeserver']['base_url'] ?? null) : null;
				if (is_string($wellKnown) && $wellKnown !== '') {
					$baseUrl = rtrim($wellKnown, '/');
				}
			}
		} catch (ClientExceptionInterface) {
			// FAIL_PROMPT: fall back to the server name as base URL
		}

		return ['base_url' => $baseUrl, 'versions' => $this->validate($baseUrl)];
	}

	/** @return array<string, mixed> */
	public function validate(string $baseUrl): array {
		$baseUrl = rtrim($baseUrl, '/');
		if (!preg_match('~^https?://~i', $baseUrl)) {
			throw new \InvalidArgumentException('Base URL must start with http:// or https://');
		}
		try {
			$response = $this->http->sendRequest($this->requestFactory->createRequest('GET', $baseUrl . '/_matrix/client/versions'));
		} catch (ClientExceptionInterface $e) {
			throw new TransportException('Homeserver unreachable: ' . $e->getMessage(), 0, '', [], $e);
		}
		if ($response->getStatusCode() !== 200) {
			throw new TransportException('Not a Matrix homeserver (HTTP ' . $response->getStatusCode() . ' on /_matrix/client/versions)', $response->getStatusCode());
		}
		$body = json_decode((string)$response->getBody(), true);
		if (!is_array($body) || !isset($body['versions']) || !is_array($body['versions'])) {
			throw new TransportException('Not a Matrix homeserver (invalid /versions response)');
		}
		return $body;
	}
}
