<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Scripted PSR-18 client: queue responses, inspect the requests made. */
final class FakeHttpClient implements ClientInterface {
	/** @var list<ResponseInterface|\Throwable> */
	private array $queue = [];
	/** @var list<RequestInterface> */
	public array $requests = [];

	public function queueJson(int $status, array $body, array $headers = []): void {
		$this->queue[] = new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
	}

	public function queueRaw(int $status, string $body, array $headers = []): void {
		$this->queue[] = new Response($status, $headers, $body);
	}

	public function queueException(\Throwable $e): void {
		$this->queue[] = $e;
	}

	public function sendRequest(RequestInterface $request): ResponseInterface {
		$this->requests[] = $request;
		if ($this->queue === []) {
			throw new \LogicException('No response queued for ' . $request->getMethod() . ' ' . $request->getUri());
		}
		$next = array_shift($this->queue);
		if ($next instanceof \Throwable) {
			throw $next;
		}
		return $next;
	}

	public function lastRequest(): RequestInterface {
		return $this->requests[count($this->requests) - 1];
	}

	public function lastBody(): array {
		$body = (string)$this->lastRequest()->getBody();
		return $body === '' ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
	}
}
