<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Crypto;

use SensitiveParameter;

/**
 * Restoring room keys from the key backup (`m.megolm_backup.v1.curve25519-aes-sha2`)
 * with the recovery key of the user, which unlocks the backup key in the
 * secret storage (`m.secret_storage.v1.aes-hmac-sha2`)
 */
final class Backup {
	public const ALGORITHM = 'm.megolm_backup.v1.curve25519-aes-sha2';
	public const SECRET_STORAGE_ALGORITHM = 'm.secret_storage.v1.aes-hmac-sha2';
	public const SECRET_NAME = 'm.megolm_backup.v1';
	private const RECOVERY_KEY_PREFIX = "\x8B\x01";
	private const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * The secret storage key of a recovery key as shown to users, in groups of four characters
	 *
	 * @throws CryptoException when it is not a valid recovery key
	 */
	public static function decodeRecoveryKey(#[SensitiveParameter] string $recoveryKey): string {
		$bytes = self::base58Decode(preg_replace('/\s+/', '', $recoveryKey) ?? '');
		if (strlen($bytes) !== 2 + 32 + 1 || !str_starts_with($bytes, self::RECOVERY_KEY_PREFIX)) {
			throw new CryptoException('Not a recovery key');
		}
		$parity = 0;
		for ($i = 0; $i < strlen($bytes); $i++) {
			$parity ^= ord($bytes[$i]);
		}
		if ($parity !== 0) {
			throw new CryptoException('Recovery key with a typo');
		}
		return substr($bytes, 2, 32);
	}

	/**
	 * Whether the key is the one of the key description (`m.secret_storage.key.*`
	 * account data), which has the MAC of 32 zero bytes encrypted with it
	 *
	 * @param array<string, mixed> $description
	 */
	public static function matchesKeyDescription(#[SensitiveParameter] string $key, array $description): bool {
		if (($description['algorithm'] ?? null) !== self::SECRET_STORAGE_ALGORITHM || !is_string($description['iv'] ?? null) || !is_string($description['mac'] ?? null)) {
			return false;
		}
		[$aesKey, $macKey] = self::secretKeys($key, '');
		$ciphertext = Kdf::aesCtr($aesKey, Base64::decode($description['iv']), str_repeat("\0", 32));
		return hash_equals(Base64::encode(Kdf::hmac($macKey, $ciphertext)), rtrim($description['mac'], '='));
	}

	/**
	 * Decrypt a secret of the secret storage
	 *
	 * @param array<string, mixed> $encrypted The entry of the key in the `encrypted` object of the secret
	 * @throws CryptoException
	 */
	public static function decryptSecret(#[SensitiveParameter] string $key, string $name, array $encrypted): string {
		if (!is_string($encrypted['iv'] ?? null) || !is_string($encrypted['ciphertext'] ?? null) || !is_string($encrypted['mac'] ?? null)) {
			throw new CryptoException('Incomplete secret');
		}
		[$aesKey, $macKey] = self::secretKeys($key, $name);
		$ciphertext = Base64::decode($encrypted['ciphertext']);
		if (!hash_equals(Kdf::hmac($macKey, $ciphertext), Base64::decode($encrypted['mac']))) {
			throw new CryptoException('Secret does not match its MAC');
		}
		return Kdf::aesCtr($aesKey, Base64::decode($encrypted['iv']), $ciphertext);
	}

	/**
	 * Decrypt one session of the key backup. The MAC of the format is
	 * computed over an empty string by most clients, so the session data is
	 * not authenticated.
	 *
	 * @param array<string, mixed> $sessionData
	 * @return array<string, mixed> Content like a forwarded room key, without room and session id
	 * @throws CryptoException
	 */
	public static function decryptSession(#[SensitiveParameter] string $backupKey, #[SensitiveParameter] array $sessionData): array {
		if (!is_string($sessionData['ephemeral'] ?? null) || !is_string($sessionData['ciphertext'] ?? null) || !is_string($sessionData['mac'] ?? null)) {
			throw new CryptoException('Incomplete backup session');
		}
		$ciphertext = Base64::decode($sessionData['ciphertext']);
		$derived = Kdf::hkdf(Keys::ecdh($backupKey, Base64::decode($sessionData['ephemeral'])), 80, '');
		$mac = Base64::decode($sessionData['mac']);
		if (!hash_equals(substr(Kdf::hmac(substr($derived, 32, 32), ''), 0, 8), $mac)
			&& !hash_equals(substr(Kdf::hmac(substr($derived, 32, 32), $ciphertext), 0, 8), $mac)) {
			throw new CryptoException('Backup session does not match its MAC');
		}
		$session = json_decode(Kdf::aesCbcDecrypt(substr($derived, 0, 32), substr($derived, 64, 16), $ciphertext), true);
		if (!is_array($session) || !is_string($session['session_key'] ?? null)) {
			throw new CryptoException('Invalid backup session');
		}
		return $session;
	}

	/**
	 * @return array{0: string, 1: string} AES and MAC key of a secret
	 */
	private static function secretKeys(#[SensitiveParameter] string $key, string $name): array {
		$derived = Kdf::hkdf($key, 64, $name);
		return [substr($derived, 0, 32), substr($derived, 32, 32)];
	}

	/**
	 * @throws CryptoException
	 */
	private static function base58Decode(string $input): string {
		// Little-endian base 256 digits, multiplied by 58 for every character
		$digits = [];
		foreach (str_split($input) as $char) {
			$carry = strpos(self::BASE58, $char);
			if ($carry === false) {
				throw new CryptoException('Invalid character in recovery key');
			}
			foreach ($digits as $i => $digit) {
				$carry += $digit * 58;
				$digits[$i] = $carry & 0xFF;
				$carry >>= 8;
			}
			for (; $carry > 0; $carry >>= 8) {
				$digits[] = $carry & 0xFF;
			}
		}
		// Leading ones are leading zero bytes
		$zeros = strlen($input) - strlen(ltrim($input, '1'));
		return str_repeat("\0", $zeros) . implode('', array_map('chr', array_reverse($digits)));
	}
}
