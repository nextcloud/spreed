<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** The handful of primitives Olm and Megolm are built from. */
final class Kdf {
	public static function hkdf(string $ikm, int $length, string $info, string $salt = ''): string {
		return hash_hkdf('sha256', $ikm, $length, $info, $salt);
	}

	public static function hmac(string $key, string $data): string {
		return hash_hmac('sha256', $data, $key, true);
	}

	/**
	 * AES-256-CBC with PKCS#7 padding, as both ratchets use it.
	 */
	public static function aesCbcEncrypt(string $key, string $iv, string $plaintext): string {
		$out = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
		if ($out === false) {
			throw new CryptoException('AES encryption failed');
		}
		return $out;
	}

	public static function aesCbcDecrypt(string $key, string $iv, string $ciphertext): string {
		$out = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
		if ($out === false) {
			throw new CryptoException('AES decryption failed (bad padding or key)');
		}
		return $out;
	}

	/** AES-256-CTR for encrypted attachments. */
	public static function aesCtr(string $key, string $iv, string $data): string {
		$out = openssl_encrypt($data, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
		if ($out === false) {
			throw new CryptoException('AES-CTR failed');
		}
		return $out;
	}

	/**
	 * Derive AES key (32), HMAC key (32) and IV (16) from a message key.
	 * @return array{0: string, 1: string, 2: string}
	 */
	public static function messageKeys(string $messageKey, string $info): array {
		$derived = self::hkdf($messageKey, 80, $info);
		return [substr($derived, 0, 32), substr($derived, 32, 32), substr($derived, 64, 16)];
	}

	public static function constantTimeEquals(string $a, string $b): bool {
		return hash_equals($a, $b);
	}
}
