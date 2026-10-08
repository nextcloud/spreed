<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Adapter;

use GuzzleHttp\Psr7\Response;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client on top of Nextcloud's IClientService so the Matrix library
 * inherits the instance's proxy, certificate bundle and local-address policy.
 * Only used with admin-configured homeserver base URLs.
 */
class HttpClient implements ClientInterface {
	public function __construct(
		private readonly IClientService $clientService,
		private readonly int $timeout = 30,
	) {
	}

	#[\Override]
	public function sendRequest(RequestInterface $request): ResponseInterface {
		$client = $this->clientService->newClient();
		$headers = [];
		foreach ($request->getHeaders() as $name => $values) {
			$headers[$name] = implode(', ', $values);
		}
		$options = [
			'headers' => $headers,
			'timeout' => $this->timeout,
			'http_errors' => false,
			'verify' => true,
		];
		$body = (string)$request->getBody();
		if ($body !== '' || in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
			$options['body'] = $body;
		}

		try {
			$response = match ($request->getMethod()) {
				'GET' => $client->get((string)$request->getUri(), $options),
				'POST' => $client->post((string)$request->getUri(), $options),
				'PUT' => $client->put((string)$request->getUri(), $options),
				'DELETE' => $client->delete((string)$request->getUri(), $options),
				'PATCH' => $client->patch((string)$request->getUri(), $options),
				'HEAD' => $client->head((string)$request->getUri(), $options),
				default => throw new NetworkException($request, 'Unsupported method ' . $request->getMethod()),
			};
		} catch (LocalServerException $e) {
			throw new NetworkException($request, 'Homeserver resolves to a local address which is not allowed: ' . $e->getMessage(), $e);
		} catch (\Throwable $e) {
			throw new NetworkException($request, $e->getMessage(), $e);
		}

		return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
	}
}

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
