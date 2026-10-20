<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client\Crypto;

use OCA\Talk\Matrix\Client\Crypto\Backup;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

/**
 * The known answers were computed with Python and the OpenSSL command line
 * for the key 0x00 … 0x1f and the backup key 32 × 0x07
 */
class BackupTest extends TestCase {
	private const RECOVERY_KEY = 'EsSz ykH7 LCZx 7Cae cmKD wcmY JRXi Ybtu 8iQ3 t8Ez nRwK pUY1';
	private const DESCRIPTION = ['algorithm' => 'm.secret_storage.v1.aes-hmac-sha2', 'iv' => 'AQEBAQEBAQEBAQEBAQEBAQ', 'mac' => 'DQxMLgRRGfFSF1v8MOMW5FrveBi7tbHSXrY+0DXIbww'];
	private const SECRET = ['iv' => 'AgICAgICAgICAgICAgICAg', 'ciphertext' => '1ZfN3I7AOgiA3CtATI0ZymC5eOHbZfhM0Px5k6YpM6+utzs1x5Fsj5f32w', 'mac' => 'IpsrK8i8oMxE7r5gCcKf9gRFad05TUbYb0M3JaRMKtI'];
	private const SESSION = [
		'ephemeral' => 'V9tLNZ8jrl4Ubk4lEgVnBHIlBjSMFQwUdT0Mkz0E1CE',
		'ciphertext' => 'lMccxcW04s9u3uzmKa1rv5PEYtj1BFa8BmpUve7OpJ9wWZ2LlOOxVnTSo9E0LytVRNgEdG9lIbbCgFe0NT9aHIA0zIdTyDK9eva4lVuM9fz2zkDkv/xRdr5DyvTUKF0qScTvwUn5koNpsSNsmrVlh5bAWtFWC8+/44muza5hJOY/b+ThnG8jeY/iQ/LvdW4hRfv5Sf4gRjkSHkvvYQN+gfYGSa5DSVPF1ZbH3aV0cI0',
		'mac' => '9CVg4mmitc4',
	];

	public function testRestoreChain(): void {
		$key = Backup::decodeRecoveryKey(self::RECOVERY_KEY);
		self::assertSame(implode('', array_map('chr', range(0, 31))), $key);
		self::assertTrue(Backup::matchesKeyDescription($key, self::DESCRIPTION));

		$backupKey = Base64::decode(Backup::decryptSecret($key, Backup::SECRET_NAME, self::SECRET));
		self::assertSame(str_repeat("\x07", 32), $backupKey);

		self::assertSame([
			'algorithm' => 'm.megolm.v1.aes-sha2',
			'sender_key' => 'senderkey',
			'session_key' => 'sessionkey',
			'sender_claimed_keys' => ['ed25519' => 'claimed'],
			'forwarding_curve25519_key_chain' => [],
		], Backup::decryptSession($backupKey, self::SESSION));
	}

	public static function dataInvalidRecoveryKeys(): array {
		return [
			'typo' => [str_replace('EsSz', 'EsSa', self::RECOVERY_KEY)],
			'invalid character' => ['EsSz 0kH7'],
			'too short' => ['EsSz ykH7'],
			'empty' => [''],
		];
	}

	#[DataProvider('dataInvalidRecoveryKeys')]
	public function testInvalidRecoveryKeys(string $recoveryKey): void {
		$this->expectException(CryptoException::class);
		Backup::decodeRecoveryKey($recoveryKey);
	}

	public function testWrongKey(): void {
		$wrongKey = str_repeat("\x01", 32);
		self::assertFalse(Backup::matchesKeyDescription($wrongKey, self::DESCRIPTION));
		$this->expectException(CryptoException::class);
		Backup::decryptSecret($wrongKey, Backup::SECRET_NAME, self::SECRET);
	}

	public function testTamperedSession(): void {
		$this->expectException(CryptoException::class);
		Backup::decryptSession(str_repeat("\x07", 32), ['mac' => 'AAAAAAAAAAA'] + self::SESSION);
	}
}
