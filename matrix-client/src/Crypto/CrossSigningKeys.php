<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto;

/** A user's cross-signing key set as returned by /keys/query. */
final class CrossSigningKeys {
	public function __construct(
		public readonly string $userId,
		public readonly ?string $master,
		public readonly ?string $selfSigning,
		public readonly ?string $userSigning,
		public readonly bool $selfSigningValid,
	) {
	}

	/**
	 * @param array<string, mixed>|null $master  master_keys[user]
	 * @param array<string, mixed>|null $selfSigning self_signing_keys[user]
	 * @param array<string, mixed>|null $userSigning user_signing_keys[user]
	 */
	public static function fromQuery(string $userId, ?array $master, ?array $selfSigning, ?array $userSigning): self {
		$masterKey = self::firstKey($master);
		$ssk = self::firstKey($selfSigning);
		$usk = self::firstKey($userSigning);
		$sskValid = false;
		if ($masterKey !== null && $ssk !== null && is_array($selfSigning)) {
			try {
				$sskValid = Keys::verifyJson($selfSigning, $userId, 'ed25519:' . $masterKey, Base64::decode($masterKey));
			} catch (CryptoException) {
			}
		}
		return new self($userId, $masterKey, $sskValid ? $ssk : null, $usk, $sskValid);
	}

	/** @param array<string, mixed>|null $keyObject */
	private static function firstKey(?array $keyObject): ?string {
		if ($keyObject === null || !is_array($keyObject['keys'] ?? null)) {
			return null;
		}
		foreach ($keyObject['keys'] as $value) {
			if (is_string($value)) {
				return $value;
			}
		}
		return null;
	}

	/** True when $device carries a valid signature from this user's self-signing key. */
	public function signsDevice(DeviceKeys $device): bool {
		return $this->selfSigning !== null && $device->userId === $this->userId && $device->isSignedBy($this->userId, $this->selfSigning);
	}
}
