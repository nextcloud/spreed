<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Olm;

use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Wire;

/**
 * Olm wire formats (version 3): normal messages and pre-key messages.
 */
final class Message {
	public const VERSION = 0x03;
	public const MAC_LENGTH = 8;

	public const TYPE_PREKEY = 0;
	public const TYPE_NORMAL = 1;

	private const TAG_RATCHET_KEY = 0x0A;
	private const TAG_CHAIN_INDEX = 0x10;
	private const TAG_CIPHERTEXT = 0x22;

	private const TAG_ONE_TIME_KEY = 0x0A;
	private const TAG_BASE_KEY = 0x12;
	private const TAG_IDENTITY_KEY = 0x1A;
	private const TAG_MESSAGE = 0x22;

	/** Body of a normal message without the trailing MAC (which is computed over exactly these bytes). */
	public static function encodeBody(string $ratchetKey, int $chainIndex, string $ciphertext): string {
		return chr(self::VERSION)
			. Wire::bytes(self::TAG_RATCHET_KEY, $ratchetKey)
			. Wire::int(self::TAG_CHAIN_INDEX, $chainIndex)
			. Wire::bytes(self::TAG_CIPHERTEXT, $ciphertext);
	}

	/**
	 * @return array{ratchetKey: string, chainIndex: int, ciphertext: string, body: string, mac: string}
	 */
	public static function decode(string $message): array {
		if (strlen($message) < 1 + self::MAC_LENGTH || ord($message[0]) !== self::VERSION) {
			throw new CryptoException('Unsupported Olm message version');
		}
		$bodyLength = strlen($message) - self::MAC_LENGTH;
		$fields = Wire::parse($message, 1, $bodyLength);
		if (!isset($fields[self::TAG_RATCHET_KEY], $fields[self::TAG_CHAIN_INDEX], $fields[self::TAG_CIPHERTEXT])) {
			throw new CryptoException('Incomplete Olm message');
		}
		return [
			'ratchetKey' => (string)$fields[self::TAG_RATCHET_KEY],
			'chainIndex' => (int)$fields[self::TAG_CHAIN_INDEX],
			'ciphertext' => (string)$fields[self::TAG_CIPHERTEXT],
			'body' => substr($message, 0, $bodyLength),
			'mac' => substr($message, $bodyLength),
		];
	}

	public static function encodePreKey(string $oneTimeKey, string $baseKey, string $identityKey, string $message): string {
		return chr(self::VERSION)
			. Wire::bytes(self::TAG_ONE_TIME_KEY, $oneTimeKey)
			. Wire::bytes(self::TAG_BASE_KEY, $baseKey)
			. Wire::bytes(self::TAG_IDENTITY_KEY, $identityKey)
			. Wire::bytes(self::TAG_MESSAGE, $message);
	}

	/**
	 * @return array{oneTimeKey: string, baseKey: string, identityKey: string, message: string}
	 */
	public static function decodePreKey(string $message): array {
		if ($message === '' || ord($message[0]) !== self::VERSION) {
			throw new CryptoException('Unsupported Olm pre-key message version');
		}
		$fields = Wire::parse($message, 1, strlen($message));
		foreach ([self::TAG_ONE_TIME_KEY, self::TAG_BASE_KEY, self::TAG_IDENTITY_KEY, self::TAG_MESSAGE] as $tag) {
			if (!isset($fields[$tag]) || !is_string($fields[$tag])) {
				throw new CryptoException('Incomplete Olm pre-key message');
			}
		}
		return [
			'oneTimeKey' => $fields[self::TAG_ONE_TIME_KEY],
			'baseKey' => $fields[self::TAG_BASE_KEY],
			'identityKey' => $fields[self::TAG_IDENTITY_KEY],
			'message' => $fields[self::TAG_MESSAGE],
		];
	}
}
