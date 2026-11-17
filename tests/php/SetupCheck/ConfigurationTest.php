<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\SetupCheck;

use OCA\Talk\Config;
use OCA\Talk\SetupCheck\Configuration;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IAppConfig as IServerAppConfig;
use OCP\IL10N;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ConfigurationTest extends TestCase {
	private static function publicKey(array $options): string {
		$key = openssl_pkey_new($options);
		return openssl_pkey_get_details($key)['key'];
	}

	public static function dataRunPublicKey(): array {
		$p256 = self::publicKey(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
		openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']), $privateKey);

		return [
			'not configured' => ['', SetupResult::SUCCESS],
			'P-256 public key' => [$p256, SetupResult::SUCCESS],
			'P-384 public key' => [self::publicKey(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']), SetupResult::ERROR],
			'RSA public key' => [self::publicKey(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]), SetupResult::ERROR],
			'private key' => [$privateKey, SetupResult::ERROR],
			'without PEM armor' => [implode('', array_slice(explode("\n", trim($p256)), 1, -1)), SetupResult::ERROR],
			'garbage' => ['not a key', SetupResult::ERROR],
		];
	}

	#[DataProvider('dataRunPublicKey')]
	public function testRunPublicKey(string $publicKey, string $severity): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')
			->with(Config::EXTERNAL_CALL_SERVICE_PUBLIC_KEY)
			->willReturn($publicKey);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$check = new Configuration($l10n, $appConfig, $this->createMock(IServerAppConfig::class));
		self::assertSame($severity, $check->run()->getSeverity());
	}
}
