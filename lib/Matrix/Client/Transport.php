<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client;

use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Client\Exception\UnknownTokenException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Thin JSON transport over PSR-18. Knows the homeserver base URL, the access
 * token and how to turn Matrix error responses into exceptions. Only ever
 * contacts the configured base URL.
 */
final class Transport {
	/** Retries of PUT requests, which are idempotent through their transaction id */
	public const MAX_RETRIES = 2;
	/** Milliseconds to wait at most before a retry */
	public const MAX_RETRY_DELAY = 2000;

	private string $baseUrl;
	private ?string $accessToken = null;
	/** @var \Closure(int): void */
	private \Closure $sleep;

	public function __construct(
		string $baseUrl,
		private readonly ClientInterface $http,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory,
	) {
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->sleep = static fn (int $milliseconds) => usleep($milliseconds * 1000);
	}

	/** @param \Closure(int): void $sleep Called with the milliseconds to wait before a retry */
	public function setSleep(\Closure $sleep): void {
		$this->sleep = $sleep;
	}

	public function withAccessToken(#[\SensitiveParameter] ?string $token): self {
		$clone = clone $this;
		$clone->accessToken = $token;
		return $clone;
	}

	/**
	 * @param array<string, string|int|null> $query Parameters with null values are skipped
	 * @return array<string, mixed>
	 * @throws MatrixException
	 */
	public function get(string $path, array $query = []): array {
		$query = array_filter($query, static fn (string|int|null $value): bool => $value !== null);
		if ($query !== []) {
			$path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}
		return $this->request('GET', $path, null);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 * @throws MatrixException
	 */
	public function post(string $path, array $body = []): array {
		return $this->request('POST', $path, $body);
	}

	/**
	 * Retried when rate limited or on server errors
	 *
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 * @throws MatrixException
	 */
	public function put(string $path, array $body = []): array {
		for ($attempt = 0; ; $attempt++) {
			try {
				return $this->request('PUT', $path, $body);
			} catch (MatrixException $e) {
				$retryable = $e->getHttpStatus() === 429 || $e->getHttpStatus() >= 500;
				if (!$retryable || $attempt >= self::MAX_RETRIES) {
					throw $e;
				}
				$delay = is_int($e->getBody()['retry_after_ms'] ?? null) ? $e->getBody()['retry_after_ms'] : 500;
				($this->sleep)(min(self::MAX_RETRY_DELAY, $delay));
			}
		}
	}

	/**
	 * @param array<string, mixed>|null $body
	 * @return array<string, mixed>
	 * @throws MatrixException
	 */
	private function request(string $method, string $path, ?array $body): array {
		$request = $this->requestFactory->createRequest($method, $this->baseUrl . $path)
			->withHeader('Accept', 'application/json');
		if ($this->accessToken !== null) {
			$request = $request->withHeader('Authorization', 'Bearer ' . $this->accessToken);
		}
		if ($body !== null) {
			$json = json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			$request = $request->withHeader('Content-Type', 'application/json')
				->withBody($this->streamFactory->createStream($json));
		}

		try {
			$response = $this->http->sendRequest($request);
		} catch (ClientExceptionInterface $e) {
			throw new TransportException('Homeserver unreachable: ' . $e->getMessage(), 0, '', [], $e);
		}

		if ($response->getStatusCode() >= 400) {
			throw $this->toException($response);
		}
		return $this->decode($response);
	}

	/**
	 * @return array<string, mixed>
	 * @throws TransportException
	 */
	private function decode(ResponseInterface $response): array {
		$content = (string)$response->getBody();
		if ($content === '') {
			return [];
		}
		try {
			$decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new TransportException('Homeserver returned invalid JSON', $response->getStatusCode(), '', [], $e);
		}
		return is_array($decoded) ? $decoded : [];
	}

	private function toException(ResponseInterface $response): MatrixException {
		$status = $response->getStatusCode();
		try {
			$body = $this->decode($response);
		} catch (TransportException) {
			$body = [];
		}
		if (!isset($body['errcode']) && !isset($body['error'])) {
			return new TransportException('Unexpected response from homeserver: HTTP ' . $status, $status, '', $body);
		}

		$errcode = (string)($body['errcode'] ?? '');
		$message = (string)($body['error'] ?? ('HTTP ' . $status));
		return match ($errcode) {
			'M_FORBIDDEN' => new ForbiddenException($message, $status, $errcode, $body),
			'M_UNKNOWN_TOKEN', 'M_MISSING_TOKEN' => new UnknownTokenException($message, $status, $errcode, $body),
			default => new MatrixException($message, $status, $errcode, $body),
		};
	}
}
