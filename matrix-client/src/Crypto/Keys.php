<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

use Nextcloud\Matrix\Util\Canonical;

/** Curve25519 / Ed25519 key pairs and Matrix JSON signing. */
final class Keys {
	/** @return array{public: string, secret: string} raw 32-byte Curve25519 keys */
	public static function curve25519KeyPair(): array {
		$secret = random_bytes(32);
		return ['public' => sodium_crypto_scalarmult_base($secret), 'secret' => $secret];
	}

	/** @return array{public: string, secret: string} raw Ed25519 keys (secret = 64-byte libsodium format) */
	public static function ed25519KeyPair(): array {
		$pair = sodium_crypto_sign_keypair();
		return ['public' => sodium_crypto_sign_publickey($pair), 'secret' => sodium_crypto_sign_secretkey($pair)];
	}

	public static function ecdh(string $ourSecret, string $theirPublic): string {
		return sodium_crypto_scalarmult($ourSecret, $theirPublic);
	}

	public static function sign(string $ed25519Secret, string $message): string {
		return sodium_crypto_sign_detached($message, $ed25519Secret);
	}

	public static function verify(string $ed25519Public, string $message, string $signature): bool {
		try {
			return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES && sodium_crypto_sign_verify_detached($signature, $message, $ed25519Public);
		} catch (\SodiumException) {
			return false;
		}
	}

	/**
	 * Sign a JSON object the Matrix way: canonical JSON without `signatures`/`unsigned`.
	 * @param array<string, mixed> $object
	 * @return array<string, mixed> object with the signature added
	 */
	public static function signJson(array $object, string $userId, string $keyId, string $ed25519Secret): array {
		$copy = $object;
		unset($copy['signatures'], $copy['unsigned']);
		$signature = self::sign($ed25519Secret, Canonical::encode($copy));
		$object['signatures'][$userId][$keyId] = Base64::encode($signature);
		return $object;
	}

	/** @param array<string, mixed> $object */
	public static function verifyJson(array $object, string $userId, string $keyId, string $ed25519Public): bool {
		$encoded = $object['signatures'][$userId][$keyId] ?? null;
		if (!is_string($encoded)) {
			return false;
		}
		$copy = $object;
		unset($copy['signatures'], $copy['unsigned']);
		try {
			return self::verify($ed25519Public, Canonical::encode($copy), Base64::decode($encoded));
		} catch (CryptoException) {
			return false;
		}
	}
}
