<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Crypto\Verification;

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Kdf;
use Nextcloud\Matrix\Crypto\Keys;
use Nextcloud\Matrix\Util\Canonical;

/**
 * The cryptographic half of `m.sas.v1` verification: ECDH, SAS bytes, MACs.
 * The message flow lives in {@see SasVerification}.
 */
final class Sas {
	public const KEY_AGREEMENT = 'curve25519-hkdf-sha256';
	public const HASH = 'sha256';
	public const MAC_V2 = 'hkdf-hmac-sha256.v2';
	public const MAC_V1 = 'hkdf-hmac-sha256';
	public const SAS_EMOJI = 'emoji';
	public const SAS_DECIMAL = 'decimal';

	/** @var array{public: string, secret: string} */
	private array $ourKey;
	private ?string $sharedSecret = null;

	public function __construct(?array $ourKey = null) {
		$this->ourKey = $ourKey ?? Keys::curve25519KeyPair();
	}

	public function getPublicKey(): string {
		return Base64::encode($this->ourKey['public']);
	}

	public function establish(string $theirPublicKeyBase64): void {
		$their = Base64::decode($theirPublicKeyBase64);
		if (strlen($their) !== 32) {
			throw new CryptoException('Bad SAS public key');
		}
		$this->sharedSecret = Keys::ecdh($this->ourKey['secret'], $their);
	}

	/**
	 * The commitment sent in m.key.verification.accept: sha256(our public key || canonical JSON of the start content).
	 * @param array<string, mixed> $startContent
	 */
	public static function commitment(string $publicKeyBase64, array $startContent): string {
		return Base64::encode(hash('sha256', $publicKeyBase64 . Canonical::encode($startContent), true));
	}

	/**
	 * SAS bytes for curve25519-hkdf-sha256 (spec §11.12.2.2.6.1).
	 */
	public function bytes(string $startUser, string $startDevice, string $startKey, string $acceptUser, string $acceptDevice, string $acceptKey, string $transactionId): string {
		$this->requireSecret();
		$info = 'MATRIX_KEY_VERIFICATION_SAS|' . $startUser . '|' . $startDevice . '|' . $startKey . '|' . $acceptUser . '|' . $acceptDevice . '|' . $acceptKey . '|' . $transactionId;
		return Kdf::hkdf((string)$this->sharedSecret, 6, $info);
	}

	/**
	 * MAC of one value (a key or the comma-joined key id list) per hkdf-hmac-sha256(.v2).
	 */
	public function mac(string $value, string $ourUser, string $ourDevice, string $theirUser, string $theirDevice, string $transactionId, string $keyId, string $method = self::MAC_V2): string {
		$this->requireSecret();
		$info = 'MATRIX_KEY_VERIFICATION_MAC' . $ourUser . $ourDevice . $theirUser . $theirDevice . $transactionId . $keyId;
		$macKey = Kdf::hkdf((string)$this->sharedSecret, 32, $info);
		$mac = Kdf::hmac($macKey, $value);
		return $method === self::MAC_V1 ? base64_encode($mac) : Base64::encode($mac);
	}

	public function macMatches(string $expectedBase64, string $value, string $ourUser, string $ourDevice, string $theirUser, string $theirDevice, string $transactionId, string $keyId, string $method): bool {
		$computed = $this->mac($value, $ourUser, $ourDevice, $theirUser, $theirDevice, $transactionId, $keyId, $method);
		return hash_equals(rtrim($computed, '='), rtrim($expectedBase64, '='));
	}

	/** Low-level: SAS bytes for an arbitrary, fully assembled info string. */
	public function rawBytes(string $info, int $length = 6): string {
		$this->requireSecret();
		return Kdf::hkdf((string)$this->sharedSecret, $length, $info);
	}

	/** Low-level: MAC for an arbitrary, fully assembled info string (unpadded base64). */
	public function rawMac(string $value, string $info): string {
		$this->requireSecret();
		return Base64::encode(Kdf::hmac(Kdf::hkdf((string)$this->sharedSecret, 32, $info), $value));
	}

	private function requireSecret(): void {
		if ($this->sharedSecret === null) {
			throw new CryptoException('SAS not established yet');
		}
	}

	public function pickle(): string {
		return json_encode(['p' => Base64::encode($this->ourKey['public']), 's' => Base64::encode($this->ourKey['secret']), 'x' => $this->sharedSecret === null ? null : Base64::encode($this->sharedSecret)], JSON_THROW_ON_ERROR);
	}

	public static function unpickle(string $pickle): self {
		$d = json_decode($pickle, true);
		if (!is_array($d)) {
			throw new CryptoException('Invalid SAS pickle');
		}
		$sas = new self(['public' => Base64::decode($d['p']), 'secret' => Base64::decode($d['s'])]);
		$sas->sharedSecret = $d['x'] === null ? null : Base64::decode($d['x']);
		return $sas;
	}
}
