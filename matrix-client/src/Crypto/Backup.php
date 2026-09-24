<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/**
 * Server-side key backup (`m.megolm_backup.v1.curve25519-aes-sha2`) and the
 * secret storage pieces needed to get at the backup key: recovery keys
 * (base58) and SSSS-encrypted account data.
 */
final class Backup {
	public const ALGORITHM = 'm.megolm_backup.v1.curve25519-aes-sha2';
	public const SECRET_NAME = 'm.megolm_backup.v1';
	private const RECOVERY_PREFIX = "\x8B\x01";
	private const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * Decrypt one backed-up session (`session_data`) with the backup's private key.
	 *
	 * @param array{ephemeral: string, ciphertext: string, mac: string} $sessionData
	 * @return array<string, mixed> decrypted JSON: algorithm, sender_key, session_key, sender_claimed_keys, forwarding_curve25519_key_chain
	 */
	public static function decryptSessionData(string $backupPrivateKey, array $sessionData): array {
		$ephemeral = Base64::decode((string)$sessionData['ephemeral']);
		$ciphertext = Base64::decode((string)$sessionData['ciphertext']);
		$mac = Base64::decode((string)$sessionData['mac']);
		if (strlen($backupPrivateKey) !== 32 || strlen($ephemeral) !== 32) {
			throw new CryptoException('Bad backup key material');
		}
		$shared = Keys::ecdh($backupPrivateKey, $ephemeral);
		$derived = Kdf::hkdf($shared, 80, '', str_repeat("\0", 32));
		$aesKey = substr($derived, 0, 32);
		$macKey = substr($derived, 32, 32);
		$iv = substr($derived, 64, 16);
		// libolm computed the MAC over the empty string (a long-standing bug that became the de-facto format);
		// accept that as well as a MAC over the ciphertext
		$macEmpty = substr(Kdf::hmac($macKey, ''), 0, 8);
		$macCiphertext = substr(Kdf::hmac($macKey, $ciphertext), 0, 8);
		if (!hash_equals($macEmpty, $mac) && !hash_equals($macCiphertext, $mac)) {
			throw new CryptoException('Backup session MAC mismatch');
		}
		$plaintext = Kdf::aesCbcDecrypt($aesKey, $iv, $ciphertext);
		$decoded = json_decode($plaintext, true);
		if (!is_array($decoded) || !is_string($decoded['session_key'] ?? null)) {
			throw new CryptoException('Backup session data malformed');
		}
		return $decoded;
	}

	/**
	 * Encrypt session data for the backup (used when uploading our own sessions).
	 * @param array<string, mixed> $sessionData
	 * @return array{ephemeral: string, ciphertext: string, mac: string}
	 */
	public static function encryptSessionData(string $backupPublicKey, array $sessionData): array {
		$ephemeral = Keys::curve25519KeyPair();
		$shared = Keys::ecdh($ephemeral['secret'], $backupPublicKey);
		$derived = Kdf::hkdf($shared, 80, '', str_repeat("\0", 32));
		$ciphertext = Kdf::aesCbcEncrypt(substr($derived, 0, 32), substr($derived, 64, 16), json_encode($sessionData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		// The de-facto format (libolm, and vodozemac for compatibility) MACs the *empty string*;
		// decryptSessionData() accepts both variants
		return [
			'ephemeral' => Base64::encode($ephemeral['public']),
			'ciphertext' => Base64::encode($ciphertext),
			'mac' => Base64::encode(substr(Kdf::hmac(substr($derived, 32, 32), ''), 0, 8)),
		];
	}

	/**
	 * Decode a user-facing recovery key ("EsTc … " groups of 4) to the 32-byte SSSS key.
	 */
	public static function decodeRecoveryKey(string $recoveryKey): string {
		$compact = preg_replace('/\s+/', '', $recoveryKey) ?? '';
		$bytes = self::base58Decode($compact);
		if (strlen($bytes) !== 2 + 32 + 1 || substr($bytes, 0, 2) !== self::RECOVERY_PREFIX) {
			throw new CryptoException('Not a recovery key');
		}
		$parity = 0;
		for ($i = 0; $i < strlen($bytes); $i++) {
			$parity ^= ord($bytes[$i]);
		}
		if ($parity !== 0) {
			throw new CryptoException('Recovery key parity check failed – typo?');
		}
		return substr($bytes, 2, 32);
	}

	public static function encodeRecoveryKey(string $key): string {
		$bytes = self::RECOVERY_PREFIX . $key;
		$parity = 0;
		for ($i = 0; $i < strlen($bytes); $i++) {
			$parity ^= ord($bytes[$i]);
		}
		$encoded = self::base58Encode($bytes . chr($parity));
		return implode(' ', str_split($encoded, 4));
	}

	/**
	 * Decrypt an SSSS-encrypted secret (account data `encrypted[keyId]`) with the SSSS key
	 * (algorithm m.secret_storage.v1.aes-hmac-sha2).
	 *
	 * @param array{iv: string, ciphertext: string, mac: string} $encrypted
	 */
	public static function decryptSecret(string $ssssKey, string $secretName, array $encrypted): string {
		$derived = Kdf::hkdf($ssssKey, 64, $secretName, str_repeat("\0", 32));
		$aesKey = substr($derived, 0, 32);
		$macKey = substr($derived, 32, 32);
		$ciphertext = Base64::decode((string)$encrypted['ciphertext']);
		$iv = Base64::decode((string)$encrypted['iv']);
		$mac = Base64::decode((string)$encrypted['mac']);
		if (!hash_equals(Kdf::hmac($macKey, $ciphertext), $mac)) {
			throw new CryptoException('Secret storage MAC mismatch – wrong recovery key?');
		}
		return Kdf::aesCtr($aesKey, $iv, $ciphertext);
	}

	/**
	 * Check a recovery key against the SSSS key description (`m.secret_storage.key.<id>` account data),
	 * which stores iv + mac of an all-zero block encrypted with the key.
	 * @param array<string, mixed> $keyInfo
	 */
	public static function checkKeyAgainstDescription(string $ssssKey, array $keyInfo): bool {
		if (!isset($keyInfo['iv'], $keyInfo['mac'])) {
			return true; // nothing to check against
		}
		$derived = Kdf::hkdf($ssssKey, 64, '', str_repeat("\0", 32));
		$ciphertext = Kdf::aesCtr(substr($derived, 0, 32), Base64::decode((string)$keyInfo['iv']), str_repeat("\0", 32));
		return hash_equals(rtrim(Base64::encode(Kdf::hmac(substr($derived, 32, 32), $ciphertext)), '='), rtrim((string)$keyInfo['mac'], '='));
	}

	private static function base58Decode(string $input): string {
		if ($input === '') {
			throw new CryptoException('Empty recovery key');
		}
		$num = '0';
		foreach (str_split($input) as $char) {
			$index = strpos(self::BASE58, $char);
			if ($index === false) {
				throw new CryptoException('Invalid recovery key character');
			}
			$num = bcadd(bcmul($num, '58'), (string)$index);
		}
		$bytes = '';
		while (bccomp($num, '0') > 0) {
			$bytes = chr((int)bcmod($num, '256')) . $bytes;
			$num = bcdiv($num, '256', 0);
		}
		// leading '1's are leading zero bytes
		$leading = strlen($input) - strlen(ltrim($input, '1'));
		return str_repeat("\0", $leading) . $bytes;
	}

	private static function base58Encode(string $bytes): string {
		$num = '0';
		foreach (str_split($bytes) as $byte) {
			$num = bcadd(bcmul($num, '256'), (string)ord($byte));
		}
		$out = '';
		while (bccomp($num, '0') > 0) {
			$out = self::BASE58[(int)bcmod($num, '58')] . $out;
			$num = bcdiv($num, '58', 0);
		}
		$leading = strlen($bytes) - strlen(ltrim($bytes, "\0"));
		return str_repeat('1', $leading) . $out;
	}
}
