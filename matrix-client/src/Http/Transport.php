<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Http;

use Nextcloud\Matrix\Exception\ForbiddenException;
use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Exception\NotFoundException;
use Nextcloud\Matrix\Exception\RateLimitedException;
use Nextcloud\Matrix\Exception\TransportException;
use Nextcloud\Matrix\Exception\UnknownTokenException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Thin JSON transport over PSR-18. Knows the homeserver base URL, the access
 * token and how to turn Matrix error responses into exceptions. Only ever
 * contacts the configured base URL (SSRF guard – media is fetched through the
 * homeserver as well).
 */
final class Transport {
	public const MAX_RETRIES = 3;

	private string $baseUrl;
	private ?string $accessToken = null;
	private int $maxRetries = self::MAX_RETRIES;
	/** @var callable(int $ms): void */
	private $sleeper;

	public function __construct(
		string $baseUrl,
		private readonly ClientInterface $http,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory,
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->sleeper = static fn (int $ms) => usleep($ms * 1000);
	}

	public function withAccessToken(?string $token): self {
		$clone = clone $this;
		$clone->accessToken = $token;
		return $clone;
	}

	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	public function getAccessToken(): ?string {
		return $this->accessToken;
	}

	/** Retries on 429 / 5xx. 0 disables retrying (useful for the sync long-poll). */
	public function setMaxRetries(int $retries): void {
		$this->maxRetries = max(0, $retries);
	}

	/** @param callable(int $ms): void $sleeper */
	public function setSleeper(callable $sleeper): void {
		$this->sleeper = $sleeper;
	}

	/**
	 * @param array<string, scalar|null|list<string>> $query
	 * @return array<string, mixed>
	 */
	public function get(string $path, array $query = []): array {
		return $this->json('GET', $path, $query, null);
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, scalar|null|list<string>> $query
	 * @return array<string, mixed>
	 */
	public function post(string $path, array $body = [], array $query = []): array {
		return $this->json('POST', $path, $query, $body);
	}

	/**
	 * @param array<string, mixed> $body
	 * @param array<string, scalar|null|list<string>> $query
	 * @return array<string, mixed>
	 */
	public function put(string $path, array $body = [], array $query = []): array {
		return $this->json('PUT', $path, $query, $body);
	}

	/**
	 * @param array<string, scalar|null|list<string>> $query
	 * @return array<string, mixed>
	 */
	public function delete(string $path, array $query = []): array {
		return $this->json('DELETE', $path, $query, null);
	}

	/**
	 * Raw request returning the PSR response, used for media downloads.
	 * @param array<string, scalar|null|list<string>> $query
	 */
	public function raw(string $method, string $path, array $query = [], ?StreamInterface $body = null, string $contentType = ''): ResponseInterface {
		$request = $this->requestFactory->createRequest($method, $this->url($path, $query));
		if ($this->accessToken !== null) {
			$request = $request->withHeader('Authorization', 'Bearer ' . $this->accessToken);
		}
		$request = $request->withHeader('Accept', 'application/json, */*');
		if ($body !== null) {
			$request = $request->withBody($body);
			if ($contentType !== '') {
				$request = $request->withHeader('Content-Type', $contentType);
			}
		}

		$attempt = 0;
		while (true) {
			try {
				$response = $this->http->sendRequest($request);
			} catch (ClientExceptionInterface $e) {
				if ($attempt < $this->maxRetries) {
					$attempt++;
					($this->sleeper)(250 * (2 ** $attempt));
					continue;
				}
				throw new TransportException('Homeserver unreachable: ' . $e->getMessage(), 0, '', [], $e);
			}

			$status = $response->getStatusCode();
			if (($status === 429 || $status >= 500) && $attempt < $this->maxRetries) {
				$attempt++;
				$wait = 250 * (2 ** $attempt);
				if ($status === 429) {
					$decoded = $this->decode($response);
					$wait = min(60000, (int)($decoded['retry_after_ms'] ?? $wait));
				}
				$this->logger->debug('Matrix request {method} {path} got {status}, retrying in {wait}ms', ['method' => $method, 'path' => $path, 'status' => $status, 'wait' => $wait]);
				($this->sleeper)($wait);
				continue;
			}

			if ($status >= 400) {
				throw $this->toException($response);
			}
			return $response;
		}
	}

	/**
	 * @param array<string, scalar|null|list<string>> $query
	 * @param array<string, mixed>|null $body
	 * @return array<string, mixed>
	 */
	private function json(string $method, string $path, array $query, ?array $body): array {
		$stream = null;
		if ($body !== null) {
			$stream = $this->streamFactory->createStream(json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		} elseif ($method === 'POST' || $method === 'PUT') {
			$stream = $this->streamFactory->createStream('{}');
		}
		$response = $this->raw($method, $path, $query, $stream, 'application/json');
		return $this->decode($response);
	}

	/** @return array<string, mixed> */
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
		$errcode = (string)($body['errcode'] ?? '');
		$message = (string)($body['error'] ?? ('HTTP ' . $status));
		if ($errcode === '' && !isset($body['error'])) {
			return new TransportException('Unexpected response from homeserver: HTTP ' . $status, $status, '', $body);
		}

		return match ($errcode) {
			'M_UNKNOWN_TOKEN', 'M_MISSING_TOKEN' => new UnknownTokenException($message, $status, $errcode, $body),
			'M_LIMIT_EXCEEDED' => new RateLimitedException($message, $status, $errcode, $body),
			'M_FORBIDDEN' => new ForbiddenException($message, $status, $errcode, $body),
			'M_NOT_FOUND' => new NotFoundException($message, $status, $errcode, $body),
			default => new MatrixException($message, $status, $errcode, $body),
		};
	}

	/** @param array<string, scalar|null|list<string>> $query */
	private function url(string $path, array $query): string {
		$parts = [];
		foreach ($query as $key => $value) {
			if ($value === null) {
				continue;
			}
			if (is_array($value)) {
				foreach ($value as $v) {
					$parts[] = rawurlencode($key) . '=' . rawurlencode((string)$v);
				}
				continue;
			}
			if (is_bool($value)) {
				$value = $value ? 'true' : 'false';
			}
			$parts[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
		}
		return $this->baseUrl . $path . ($parts === [] ? '' : '?' . implode('&', $parts));
	}
}
