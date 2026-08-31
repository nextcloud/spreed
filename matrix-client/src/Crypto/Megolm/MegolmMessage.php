<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Megolm;

use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Wire;

/** Megolm message format (version 3): body || MAC(8) || Ed25519 signature(64). */
final class MegolmMessage {
	public const VERSION = 0x03;
	public const MAC_LENGTH = 8;
	public const SIGNATURE_LENGTH = 64;
	private const TAG_INDEX = 0x08;
	private const TAG_CIPHERTEXT = 0x12;

	public static function encodeBody(int $index, string $ciphertext): string {
		return chr(self::VERSION) . Wire::int(self::TAG_INDEX, $index) . Wire::bytes(self::TAG_CIPHERTEXT, $ciphertext);
	}

	/**
	 * @return array{index: int, ciphertext: string, body: string, mac: string, signature: string, signed: string}
	 */
	public static function decode(string $message): array {
		$minimum = 1 + self::MAC_LENGTH + self::SIGNATURE_LENGTH;
		if (strlen($message) < $minimum || ord($message[0]) !== self::VERSION) {
			throw new CryptoException('Unsupported Megolm message');
		}
		$signedLength = strlen($message) - self::SIGNATURE_LENGTH;
		$bodyLength = $signedLength - self::MAC_LENGTH;
		$fields = Wire::parse($message, 1, $bodyLength);
		if (!isset($fields[self::TAG_INDEX], $fields[self::TAG_CIPHERTEXT])) {
			throw new CryptoException('Incomplete Megolm message');
		}
		return [
			'index' => (int)$fields[self::TAG_INDEX],
			'ciphertext' => (string)$fields[self::TAG_CIPHERTEXT],
			'body' => substr($message, 0, $bodyLength),
			'mac' => substr($message, $bodyLength, self::MAC_LENGTH),
			'signature' => substr($message, $signedLength),
			'signed' => substr($message, 0, $signedLength),
		];
	}
}
