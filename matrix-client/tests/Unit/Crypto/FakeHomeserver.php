<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit\Crypto;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Just enough of a homeserver for the key-management endpoints: stores
 * uploaded keys, answers /keys/query and /keys/claim, queues to-device messages.
 */
final class FakeHomeserver implements ClientInterface {
	/** @var array<string, array<string, array<string, mixed>>> user → device → device_keys */
	public array $deviceKeys = [];
	/** @var array<string, array<string, array<string, array<string, mixed>>>> user → device → key id → otk object */
	public array $oneTimeKeys = [];
	/** @var array<string, array<string, array<string, mixed>>> user → device → fallback */
	public array $fallbackKeys = [];
	/** @var list<array{type: string, messages: array<string, array<string, array<string, mixed>>>}> */
	public array $toDevice = [];

	public function __construct(
		private readonly string $userId,
		private readonly string $deviceId,
	) {
	}

	public function sendRequest(RequestInterface $request): ResponseInterface {
		$path = $request->getUri()->getPath();
		$body = json_decode((string)$request->getBody(), true) ?? [];
		if (str_ends_with($path, '/keys/upload')) {
			if (isset($body['device_keys'])) {
				$this->deviceKeys[$this->userId][$this->deviceId] = $body['device_keys'];
			}
			foreach ($body['one_time_keys'] ?? [] as $id => $key) {
				$this->oneTimeKeys[$this->userId][$this->deviceId][$id] = $key;
			}
			foreach ($body['fallback_keys'] ?? [] as $id => $key) {
				$this->fallbackKeys[$this->userId][$this->deviceId] = [$id => $key];
			}
			return $this->json(['one_time_key_counts' => ['signed_curve25519' => count($this->oneTimeKeys[$this->userId][$this->deviceId] ?? [])]]);
		}
		if (str_ends_with($path, '/keys/query')) {
			$result = ['device_keys' => []];
			foreach (array_keys($body['device_keys'] ?? []) as $userId) {
				$result['device_keys'][$userId] = $this->deviceKeys[$userId] ?? [];
			}
			return $this->json($result);
		}
		if (str_ends_with($path, '/keys/claim')) {
			$result = ['one_time_keys' => []];
			foreach ($body['one_time_keys'] ?? [] as $userId => $devices) {
				foreach ($devices as $deviceId => $algorithm) {
					$keys = $this->oneTimeKeys[$userId][$deviceId] ?? [];
					if ($keys !== []) {
						$id = array_key_first($keys);
						$result['one_time_keys'][$userId][$deviceId] = [$id => $keys[$id]];
						unset($this->oneTimeKeys[$userId][$deviceId][$id]);
					} elseif (isset($this->fallbackKeys[$userId][$deviceId])) {
						$result['one_time_keys'][$userId][$deviceId] = $this->fallbackKeys[$userId][$deviceId];
					}
				}
			}
			return $this->json($result);
		}
		if (preg_match('~/sendToDevice/([^/]+)/~', $path, $m)) {
			$this->toDevice[] = ['type' => rawurldecode($m[1]), 'messages' => $body['messages'] ?? []];
			return $this->json([]);
		}
		return new Response(404, ['Content-Type' => 'application/json'], json_encode(['errcode' => 'M_UNRECOGNIZED', 'error' => 'fake: ' . $path]));
	}

	private function json(array $data): ResponseInterface {
		return new Response(200, ['Content-Type' => 'application/json'], json_encode($data === [] ? new \stdClass() : $data));
	}

	/** Share this homeserver's key directory between two fake clients. */
	public function linkWith(FakeHomeserver $other): void {
		$other->deviceKeys = &$this->deviceKeys;
		$other->oneTimeKeys = &$this->oneTimeKeys;
		$other->fallbackKeys = &$this->fallbackKeys;
		$other->toDevice = &$this->toDevice;
	}
}
