<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit\Crypto;

use Nextcloud\Matrix\Crypto\Backup;
use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\CryptoException;
use Nextcloud\Matrix\Crypto\Kdf;
use Nextcloud\Matrix\Crypto\Keys;
use PHPUnit\Framework\TestCase;

final class BackupTest extends TestCase {
	public function testRecoveryKeyRoundTrip(): void {
		$key = random_bytes(32);
		$encoded = Backup::encodeRecoveryKey($key);
		self::assertMatchesRegularExpression('/^E[1-9A-HJ-NP-Za-km-z]{3}( [1-9A-HJ-NP-Za-km-z]{1,4})+$/', $encoded, 'recovery keys start with Es…');
		self::assertSame($key, Backup::decodeRecoveryKey($encoded));
		self::assertSame($key, Backup::decodeRecoveryKey(str_replace(' ', '', $encoded)));
		$this->expectException(CryptoException::class);
		Backup::decodeRecoveryKey(substr($encoded, 0, -1) . ($encoded[-1] === '2' ? '3' : '2'));
	}

	public function testSessionDataRoundTrip(): void {
		$backup = Keys::curve25519KeyPair();
		$data = ['algorithm' => 'm.megolm.v1.aes-sha2', 'sender_key' => 'abc', 'session_key' => 'AQ…', 'sender_claimed_keys' => ['ed25519' => 'x'], 'forwarding_curve25519_key_chain' => []];
		$encrypted = Backup::encryptSessionData($backup['public'], $data);
		self::assertSame($data, Backup::decryptSessionData($backup['secret'], $encrypted));
		$encrypted['mac'] = Base64::encode(random_bytes(8));
		$this->expectException(CryptoException::class);
		Backup::decryptSessionData($backup['secret'], $encrypted);
	}

	public function testSecretStorageDecryptAndKeyCheck(): void {
		$ssss = random_bytes(32);
		$secret = Base64::encode(random_bytes(32));
		$derived = Kdf::hkdf($ssss, 64, Backup::SECRET_NAME, str_repeat("\0", 32));
		$iv = random_bytes(16);
		$ciphertext = Kdf::aesCtr(substr($derived, 0, 32), $iv, $secret);
		$encrypted = ['iv' => Base64::encode($iv), 'ciphertext' => Base64::encode($ciphertext), 'mac' => Base64::encode(Kdf::hmac(substr($derived, 32, 32), $ciphertext))];
		self::assertSame($secret, Backup::decryptSecret($ssss, Backup::SECRET_NAME, $encrypted));

		$check = Kdf::hkdf($ssss, 64, '', str_repeat("\0", 32));
		$zeroIv = random_bytes(16);
		$zeroCipher = Kdf::aesCtr(substr($check, 0, 32), $zeroIv, str_repeat("\0", 32));
		$description = ['iv' => Base64::encode($zeroIv), 'mac' => Base64::encode(Kdf::hmac(substr($check, 32, 32), $zeroCipher))];
		self::assertTrue(Backup::checkKeyAgainstDescription($ssss, $description));
		self::assertFalse(Backup::checkKeyAgainstDescription(random_bytes(32), $description));
	}
}
