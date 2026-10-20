<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client\Crypto;

use OCA\Talk\Matrix\Client\Crypto\Attachment;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class AttachmentTest extends TestCase {
	/**
	 * @return resource
	 */
	private static function stream(string $data) {
		$stream = fopen('php://memory', 'r+b');
		fwrite($stream, $data);
		rewind($stream);
		return $stream;
	}

	public static function dataSizes(): array {
		return [
			'empty' => [0],
			'small' => [3],
			'one chunk' => [Attachment::CHUNK_SIZE],
			'several chunks' => [Attachment::CHUNK_SIZE * 2 + 5],
		];
	}

	#[DataProvider('dataSizes')]
	public function testRoundTrip(int $size): void {
		$plaintext = $size > 0 ? random_bytes($size) : '';
		$encrypted = Attachment::encrypt(self::stream($plaintext));
		$keys = Attachment::getKeys($encrypted['file']);
		$ciphertext = stream_get_contents($encrypted['stream']);

		self::assertSame($size, $encrypted['size']);
		// The chunks continue the counter like encrypting the file at once
		self::assertSame(openssl_encrypt($plaintext, 'aes-256-ctr', $keys['key'], OPENSSL_RAW_DATA, $keys['iv']), $ciphertext);
		self::assertSame(hash('sha256', $ciphertext, true), $keys['sha256']);
		self::assertSame("\0\0\0\0\0\0\0\0", substr($keys['iv'], 8), 'The counter starts at zero');

		$decrypted = Attachment::decrypt(self::stream($ciphertext), $keys);
		self::assertSame($plaintext, stream_get_contents($decrypted['stream']));
		self::assertSame($size, $decrypted['size']);
	}

	public function testCounterCarriesIntoHigherBytes(): void {
		// Files of other clients may use the whole IV as counter
		$keys = ['key' => random_bytes(32), 'iv' => str_repeat("\x00", 8) . str_repeat("\xff", 8)];
		$plaintext = random_bytes(Attachment::CHUNK_SIZE * 2);
		$ciphertext = openssl_encrypt($plaintext, 'aes-256-ctr', $keys['key'], OPENSSL_RAW_DATA, $keys['iv']);
		$keys['sha256'] = hash('sha256', $ciphertext, true);

		self::assertSame($plaintext, stream_get_contents(Attachment::decrypt(self::stream($ciphertext), $keys)['stream']));
	}

	public function testTamperedFileIsRefused(): void {
		$encrypted = Attachment::encrypt(self::stream('secret'));
		$ciphertext = stream_get_contents($encrypted['stream']);
		$ciphertext[0] = chr(ord($ciphertext[0]) ^ 1);

		$this->expectException(CryptoException::class);
		Attachment::decrypt(self::stream($ciphertext), Attachment::getKeys($encrypted['file']));
	}

	public static function dataInvalidFiles(): array {
		$valid = ['v' => 'v2', 'key' => ['alg' => 'A256CTR', 'k' => Base64::encodeUrl(str_repeat('k', 32))], 'iv' => Base64::encode(str_repeat('i', 16)), 'hashes' => ['sha256' => Base64::encode(str_repeat('h', 32))]];
		return [
			'old version' => [['v' => 'v1'] + $valid],
			'other algorithm' => [array_replace_recursive($valid, ['key' => ['alg' => 'A128CTR']])],
			'short key' => [array_replace_recursive($valid, ['key' => ['k' => Base64::encodeUrl('short')]])],
			'no hash' => [array_replace($valid, ['hashes' => []])],
		];
	}

	#[DataProvider('dataInvalidFiles')]
	public function testInvalidFiles(array $file): void {
		$this->expectException(CryptoException::class);
		Attachment::getKeys($file);
	}
}
