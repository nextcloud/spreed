<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Crypto;

use SensitiveParameter;

/**
 * Encrypted attachments of end-to-end encrypted rooms: AES-256-CTR with a
 * SHA-256 hash of the ciphertext. Files are processed in chunks into
 * temporary streams, so they are never completely in memory.
 */
final class Attachment {
	/** Bytes processed at once, a multiple of the AES block size */
	public const CHUNK_SIZE = 65536;

	/**
	 * @param resource $plaintext
	 * @return array{stream: resource, size: int, file: array<string, mixed>} Rewound ciphertext and the `file` object of the event without its url
	 */
	public static function encrypt($plaintext): array {
		$key = random_bytes(32);
		// The counter half of the IV starts at zero, as recommended for compatibility
		$iv = random_bytes(8) . str_repeat("\0", 8);
		[$stream, $size, $hash] = self::crypt($plaintext, $key, $iv, false);
		return [
			'stream' => $stream,
			'size' => $size,
			'file' => [
				'v' => 'v2',
				'key' => ['kty' => 'oct', 'key_ops' => ['encrypt', 'decrypt'], 'alg' => 'A256CTR', 'k' => Base64::encodeUrl($key), 'ext' => true],
				'iv' => Base64::encode($iv),
				'hashes' => ['sha256' => Base64::encode($hash)],
			],
		];
	}

	/**
	 * Key, IV and hash of the `file` object of an event
	 *
	 * @param array<string, mixed> $file
	 * @return array{key: string, iv: string, sha256: string} Raw bytes
	 * @throws CryptoException when the object is not a supported encrypted file
	 */
	public static function getKeys(array $file): array {
		$k = $file['key']['k'] ?? null;
		$iv = $file['iv'] ?? null;
		$sha256 = $file['hashes']['sha256'] ?? null;
		if (($file['v'] ?? null) !== 'v2' || ($file['key']['alg'] ?? null) !== 'A256CTR' || !is_string($k) || !is_string($iv) || !is_string($sha256)) {
			throw new CryptoException('Unsupported encrypted file');
		}
		$keys = ['key' => Base64::decode($k), 'iv' => Base64::decode($iv), 'sha256' => Base64::decode($sha256)];
		if (strlen($keys['key']) !== 32 || strlen($keys['iv']) !== 16 || strlen($keys['sha256']) !== 32) {
			throw new CryptoException('Unsupported encrypted file');
		}
		return $keys;
	}

	/**
	 * @param resource $ciphertext
	 * @param array{key: string, iv: string, sha256: string} $keys
	 * @return array{stream: resource, size: int} Rewound plaintext
	 * @throws CryptoException when the ciphertext does not match the hash, nothing is returned then
	 */
	public static function decrypt($ciphertext, #[SensitiveParameter] array $keys): array {
		[$stream, $size, $hash] = self::crypt($ciphertext, $keys['key'], $keys['iv'], true);
		if (!hash_equals($keys['sha256'], $hash)) {
			fclose($stream);
			throw new CryptoException('Encrypted file does not match its hash');
		}
		return ['stream' => $stream, 'size' => $size];
	}

	/**
	 * AES-CTR is the same in both directions, the hash is of the ciphertext
	 *
	 * @param resource $input
	 * @return array{0: resource, 1: int, 2: string} Rewound output, its size and the raw SHA-256 of the ciphertext
	 */
	private static function crypt($input, #[SensitiveParameter] string $key, string $iv, bool $inputIsCiphertext): array {
		$output = fopen('php://temp', 'w+b');
		if ($output === false) {
			throw new CryptoException('No temporary stream');
		}
		$hash = hash_init('sha256');
		$size = 0;
		while (!feof($input)) {
			$chunk = self::read($input, self::CHUNK_SIZE);
			if ($chunk === '') {
				break;
			}
			$out = openssl_encrypt($chunk, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
			if ($out === false) {
				throw new CryptoException('AES-CTR failed');
			}
			hash_update($hash, $inputIsCiphertext ? $chunk : $out);
			fwrite($output, $out);
			$size += strlen($out);
			$iv = self::addToCounter($iv, intdiv(strlen($chunk), 16));
		}
		rewind($output);
		return [$output, $size, hash_final($hash, true)];
	}

	/**
	 * Full chunks, as streams may return less than asked for
	 *
	 * @param resource $input
	 */
	private static function read($input, int $length): string {
		$data = '';
		while (strlen($data) < $length && !feof($input)) {
			$read = fread($input, $length - strlen($data));
			if ($read === false || $read === '') {
				break;
			}
			$data .= $read;
		}
		return $data;
	}

	/**
	 * The IV is a 128-bit big-endian counter, as in OpenSSL
	 */
	private static function addToCounter(string $iv, int $blocks): string {
		for ($i = 15; $i >= 0 && $blocks > 0; $i--) {
			$sum = ord($iv[$i]) + ($blocks & 0xFF);
			$iv[$i] = chr($sum & 0xFF);
			$blocks = ($blocks >> 8) + ($sum >> 8);
		}
		return $iv;
	}
}
