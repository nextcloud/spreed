<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** Another device's published keys (one entry of /keys/query `device_keys`). */
final class DeviceKeys {
	/**
	 * @param list<string> $algorithms
	 * @param array<string, mixed> $raw the full signed object
	 */
	public function __construct(
		public readonly string $userId,
		public readonly string $deviceId,
		public readonly string $curve25519,
		public readonly string $ed25519,
		public readonly array $algorithms,
		public readonly array $raw,
		public readonly ?string $displayName = null,
	) {
	}

	/**
	 * Parse and verify a device_keys object: the object must be self-signed
	 * with the ed25519 key it claims, and belong to the user/device it is
	 * listed under.
	 *
	 * @param array<string, mixed> $raw
	 */
	public static function fromArray(string $userId, string $deviceId, array $raw): self {
		if (($raw['user_id'] ?? null) !== $userId || ($raw['device_id'] ?? null) !== $deviceId) {
			throw new CryptoException('Device keys belong to a different user/device');
		}
		$keys = is_array($raw['keys'] ?? null) ? $raw['keys'] : [];
		$curve = $keys['curve25519:' . $deviceId] ?? null;
		$ed = $keys['ed25519:' . $deviceId] ?? null;
		if (!is_string($curve) || !is_string($ed)) {
			throw new CryptoException('Device keys incomplete');
		}
		if (!Keys::verifyJson($raw, $userId, 'ed25519:' . $deviceId, Base64::decode($ed))) {
			throw new CryptoException('Device keys self-signature invalid');
		}
		$algorithms = array_values(array_filter(is_array($raw['algorithms'] ?? null) ? $raw['algorithms'] : [], 'is_string'));
		$name = $raw['unsigned']['device_display_name'] ?? null;
		return new self($userId, $deviceId, $curve, $ed, $algorithms, $raw, is_string($name) ? $name : null);
	}

	public function supportsOlm(): bool {
		return in_array('m.olm.v1.curve25519-aes-sha2', $this->algorithms, true);
	}

	public function supportsMegolm(): bool {
		return in_array('m.megolm.v1.aes-sha2', $this->algorithms, true);
	}

	/** Whether the device is signed by the given self-signing key (cross-signing). */
	public function isSignedBy(string $signingUserId, string $signingKeyBase64): bool {
		try {
			return Keys::verifyJson($this->raw, $signingUserId, 'ed25519:' . $signingKeyBase64, Base64::decode($signingKeyBase64));
		} catch (CryptoException) {
			return false;
		}
	}
}
