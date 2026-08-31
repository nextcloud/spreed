<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** Unpadded standard base64 as used throughout Matrix end-to-end encryption. */
final class Base64 {
	public static function encode(string $bytes): string {
		return rtrim(base64_encode($bytes), '=');
	}

	public static function decode(string $encoded): string {
		$encoded = strtr($encoded, '-_', '+/');
		$padded = $encoded . str_repeat('=', (4 - strlen($encoded) % 4) % 4);
		$decoded = base64_decode($padded, true);
		if ($decoded === false) {
			throw new CryptoException('Invalid base64');
		}
		return $decoded;
	}

	/** URL-safe unpadded variant (used by encrypted attachments' JWK). */
	public static function encodeUrl(string $bytes): string {
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}
}
