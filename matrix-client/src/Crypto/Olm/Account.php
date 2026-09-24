<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Olm;

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Keys;

/**
 * An Olm account: the device's long-term Curve25519 identity key, Ed25519
 * signing key, and its one-time / fallback keys. Serialises to JSON (our own
 * "pickle", not libolm's).
 */
final class Account {
	public const MAX_ONE_TIME_KEYS = 100;

	/** @var array{public: string, secret: string} */
	private array $identity;
	/** @var array{public: string, secret: string} */
	private array $signing;
	/** @var array<string, array{public: string, secret: string, published: bool}> key id → pair */
	private array $oneTimeKeys = [];
	/** @var array{id: string, public: string, secret: string, published: bool}|null */
	private ?array $fallbackKey = null;
	/** @var array{id: string, public: string, secret: string}|null previous fallback key, kept until rotated twice */
	private ?array $previousFallbackKey = null;
	private int $nextKeyId = 1;

	private function __construct() {
	}

	public static function create(): self {
		$account = new self();
		$account->identity = Keys::curve25519KeyPair();
		$account->signing = Keys::ed25519KeyPair();
		return $account;
	}

	public function getIdentityKey(): string {
		return $this->identity['public'];
	}

	public function getIdentityKeyBase64(): string {
		return Base64::encode($this->identity['public']);
	}

	public function getSigningKey(): string {
		return $this->signing['public'];
	}

	public function getSigningKeyBase64(): string {
		return Base64::encode($this->signing['public']);
	}

	public function sign(string $message): string {
		return Keys::sign($this->signing['secret'], $message);
	}

	/** @param array<string, mixed> $object */
	public function signJson(array $object, string $userId, string $deviceId): array {
		return Keys::signJson($object, $userId, 'ed25519:' . $deviceId, $this->signing['secret']);
	}

	/**
	 * Device keys object for POST /keys/upload.
	 * @param list<string> $algorithms
	 * @return array<string, mixed>
	 */
	public function deviceKeys(string $userId, string $deviceId, array $algorithms = ['m.olm.v1.curve25519-aes-sha2', 'm.megolm.v1.aes-sha2']): array {
		return $this->signJson([
			'user_id' => $userId,
			'device_id' => $deviceId,
			'algorithms' => $algorithms,
			'keys' => [
				'curve25519:' . $deviceId => $this->getIdentityKeyBase64(),
				'ed25519:' . $deviceId => $this->getSigningKeyBase64(),
			],
		], $userId, $deviceId);
	}

	public function generateOneTimeKeys(int $count): void {
		$count = min($count, self::MAX_ONE_TIME_KEYS - count($this->oneTimeKeys));
		for ($i = 0; $i < $count; $i++) {
			$id = $this->newKeyId();
			$this->oneTimeKeys[$id] = Keys::curve25519KeyPair() + ['published' => false];
		}
	}

	/** @return array<string, string> key id → base64 public key of not-yet-published one-time keys */
	public function getUnpublishedOneTimeKeys(): array {
		$out = [];
		foreach ($this->oneTimeKeys as $id => $key) {
			if (!$key['published']) {
				$out[$id] = Base64::encode($key['public']);
			}
		}
		return $out;
	}

	public function markKeysAsPublished(): void {
		foreach ($this->oneTimeKeys as $id => $key) {
			$this->oneTimeKeys[$id]['published'] = true;
		}
		if ($this->fallbackKey !== null) {
			$this->fallbackKey['published'] = true;
		}
	}

	public function countOneTimeKeys(): int {
		return count($this->oneTimeKeys);
	}

	public function generateFallbackKey(): void {
		$this->previousFallbackKey = $this->fallbackKey === null ? null : ['id' => $this->fallbackKey['id'], 'public' => $this->fallbackKey['public'], 'secret' => $this->fallbackKey['secret']];
		$this->fallbackKey = ['id' => $this->newKeyId()] + Keys::curve25519KeyPair() + ['published' => false];
	}

	/** @return array{id: string, public: string}|null unpublished fallback key */
	public function getUnpublishedFallbackKey(): ?array {
		if ($this->fallbackKey === null || $this->fallbackKey['published']) {
			return null;
		}
		return ['id' => $this->fallbackKey['id'], 'public' => Base64::encode($this->fallbackKey['public'])];
	}

	/**
	 * `one_time_keys` + `fallback_keys` body parts for /keys/upload (signed).
	 * @return array{one_time_keys: array<string, mixed>, fallback_keys: array<string, mixed>}
	 */
	public function keysForUpload(string $userId, string $deviceId): array {
		$oneTime = [];
		foreach ($this->getUnpublishedOneTimeKeys() as $id => $public) {
			$oneTime['signed_curve25519:' . $id] = $this->signJson(['key' => $public], $userId, $deviceId);
		}
		$fallback = [];
		$fb = $this->getUnpublishedFallbackKey();
		if ($fb !== null) {
			$fallback['signed_curve25519:' . $fb['id']] = $this->signJson(['key' => $fb['public'], 'fallback' => true], $userId, $deviceId);
		}
		return ['one_time_keys' => $oneTime, 'fallback_keys' => $fallback];
	}

	/**
	 * Find the private half of a one-time or fallback key by its public key
	 * (pre-key messages reference the key by value).
	 * @return array{secret: string, oneTimeId: ?string}|null
	 */
	public function findKeyByPublic(string $public): ?array {
		foreach ($this->oneTimeKeys as $id => $key) {
			if (hash_equals($key['public'], $public)) {
				return ['secret' => $key['secret'], 'oneTimeId' => $id];
			}
		}
		if ($this->fallbackKey !== null && hash_equals($this->fallbackKey['public'], $public)) {
			return ['secret' => $this->fallbackKey['secret'], 'oneTimeId' => null];
		}
		if ($this->previousFallbackKey !== null && hash_equals($this->previousFallbackKey['public'], $public)) {
			return ['secret' => $this->previousFallbackKey['secret'], 'oneTimeId' => null];
		}
		return null;
	}

	/** One-time keys are single use: remove after a session was created from it. */
	public function removeOneTimeKey(string $id): void {
		unset($this->oneTimeKeys[$id]);
	}

	public function getIdentitySecret(): string {
		return $this->identity['secret'];
	}

	private function newKeyId(): string {
		// libolm-style: base64 of a big-endian 32-bit counter
		return Base64::encode(pack('N', $this->nextKeyId++));
	}

	public function pickle(): string {
		$otk = [];
		foreach ($this->oneTimeKeys as $id => $key) {
			$otk[$id] = ['p' => Base64::encode($key['public']), 's' => Base64::encode($key['secret']), 'u' => $key['published']];
		}
		return json_encode([
			'v' => 1,
			'identity' => ['p' => Base64::encode($this->identity['public']), 's' => Base64::encode($this->identity['secret'])],
			'signing' => ['p' => Base64::encode($this->signing['public']), 's' => Base64::encode($this->signing['secret'])],
			'otk' => $otk,
			'fallback' => $this->fallbackKey === null ? null : ['id' => $this->fallbackKey['id'], 'p' => Base64::encode($this->fallbackKey['public']), 's' => Base64::encode($this->fallbackKey['secret']), 'u' => $this->fallbackKey['published']],
			'prevFallback' => $this->previousFallbackKey === null ? null : ['id' => $this->previousFallbackKey['id'], 'p' => Base64::encode($this->previousFallbackKey['public']), 's' => Base64::encode($this->previousFallbackKey['secret'])],
			'next' => $this->nextKeyId,
		], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$data = json_decode($pickle, true);
		if (!is_array($data) || ($data['v'] ?? 0) !== 1) {
			throw new CryptoException('Invalid account pickle');
		}
		$account = new self();
		$account->identity = ['public' => Base64::decode($data['identity']['p']), 'secret' => Base64::decode($data['identity']['s'])];
		$account->signing = ['public' => Base64::decode($data['signing']['p']), 'secret' => Base64::decode($data['signing']['s'])];
		foreach ($data['otk'] ?? [] as $id => $key) {
			$account->oneTimeKeys[(string)$id] = ['public' => Base64::decode($key['p']), 'secret' => Base64::decode($key['s']), 'published' => (bool)$key['u']];
		}
		if (isset($data['fallback'])) {
			$account->fallbackKey = ['id' => (string)$data['fallback']['id'], 'public' => Base64::decode($data['fallback']['p']), 'secret' => Base64::decode($data['fallback']['s']), 'published' => (bool)$data['fallback']['u']];
		}
		if (isset($data['prevFallback'])) {
			$account->previousFallbackKey = ['id' => (string)$data['prevFallback']['id'], 'public' => Base64::decode($data['prevFallback']['p']), 'secret' => Base64::decode($data['prevFallback']['s'])];
		}
		$account->nextKeyId = (int)($data['next'] ?? 1);
		return $account;
	}
}
