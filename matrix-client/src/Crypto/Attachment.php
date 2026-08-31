<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/**
 * Encrypted attachments (spec §11.12.1.2.1 EncryptedFile): AES-256-CTR with a
 * JWK key, 8 random IV bytes + 8 zero bytes, SHA-256 hash of the ciphertext.
 */
final class Attachment {
	/**
	 * @param array<string, mixed> $file the `file` object of the event content
	 */
	public static function decrypt(array $file, string $ciphertext): string {
		if (($file['v'] ?? '') !== 'v2') {
			throw new CryptoException('Unsupported encrypted attachment version');
		}
		$key = $file['key'] ?? [];
		if (($key['alg'] ?? '') !== 'A256CTR' || !isset($key['k'], $file['iv'], $file['hashes']['sha256'])) {
			throw new CryptoException('Malformed encrypted attachment');
		}
		if (!hash_equals(Base64::decode((string)$file['hashes']['sha256']), hash('sha256', $ciphertext, true))) {
			throw new CryptoException('Encrypted attachment hash mismatch');
		}
		$rawKey = Base64::decode((string)$key['k']);
		$iv = Base64::decode((string)$file['iv']);
		if (strlen($rawKey) !== 32 || strlen($iv) !== 16) {
			throw new CryptoException('Bad attachment key or IV length');
		}
		return Kdf::aesCtr($rawKey, $iv, $ciphertext);
	}

	/**
	 * @return array{ciphertext: string, file: array<string, mixed>} file = EncryptedFile object without `url`
	 */
	public static function encrypt(string $plaintext): array {
		$key = random_bytes(32);
		$iv = random_bytes(8) . str_repeat("\0", 8);
		$ciphertext = Kdf::aesCtr($key, $iv, $plaintext);
		return [
			'ciphertext' => $ciphertext,
			'file' => [
				'v' => 'v2',
				'key' => ['alg' => 'A256CTR', 'ext' => true, 'k' => Base64::encodeUrl($key), 'key_ops' => ['encrypt', 'decrypt'], 'kty' => 'oct'],
				'iv' => Base64::encode($iv),
				'hashes' => ['sha256' => Base64::encode(hash('sha256', $ciphertext, true))],
			],
		];
	}
}
