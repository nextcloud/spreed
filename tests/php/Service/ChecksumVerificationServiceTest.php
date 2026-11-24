<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OC\Memcache\ArrayCache;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Service\ChecksumVerificationService;
use OCA\Talk\Vendor\Firebase\JWT\JWT;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ChecksumVerificationServiceTest extends TestCase {
	private const NOW = 1_800_000_000;
	private const BODY = '{"owner":"alice","roomName":"Meeting"}';
	private const PATH = '/ocs/v2.php/apps/spreed/api/v4/room/abcdef';

	protected Config&MockObject $config;
	protected IRequest&MockObject $request;
	protected ICacheFactory&MockObject $cacheFactory;
	protected ITimeFactory&MockObject $timeFactory;
	protected ChecksumVerificationService $service;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->request = $this->createMock(IRequest::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->service = new ChecksumVerificationService($this->config, $this->request, $this->cacheFactory, $this->timeFactory);
	}

	public static function dataValidateRequest(): array {
		$validRandom = md5(random_bytes(15));
		$fakeData = json_encode(['fake' => 'data']);
		$validSecret = 'valid secret';
		$validChecksum = hash_hmac('sha256', $validRandom . $fakeData, $validSecret);
		return [
			['', '', '', '', '', false],
			['1234', '', '', '', 'Invalid random provided', false],
			[str_repeat('1', 32), '', '', '', 'Invalid checksum provided', false],
			[str_repeat('1', 32), 'fake', '', '', 'No secret provided', false],
			[str_repeat('1', 32), 'fake', 'invalid', '', 'Invalid HMAC provided', false],
			[$validRandom, $validChecksum, $validSecret, $fakeData, '', true],
		];
	}

	#[DataProvider('dataValidateRequest')]
	public function testValidateRequest(string $random, string $checksum, string $secret, string $token, string $exceptionMessage, bool $expectedReturn): void {
		if ($exceptionMessage) {
			$this->expectException(UnauthorizedException::class);
			$this->expectExceptionMessage($exceptionMessage);
		}
		$actual = $this->service->validateRequest($random, $checksum, $secret, $token);
		if (!$exceptionMessage) {
			$this->assertEquals($expectedReturn, $actual);
		}
	}

	private static function claims(array $overrides = []): array {
		$claims = array_merge([
			'iat' => self::NOW,
			'exp' => self::NOW + 60,
			'jti' => str_repeat('a', 64),
			'method' => 'PUT',
			'uri' => self::PATH,
			'body' => hash('sha256', self::BODY),
		], $overrides);
		return array_filter($claims, static fn ($value): bool => $value !== null);
	}

	/** @var array<string, array{private: string, public: string}> */
	private static array $keys = [];

	/**
	 * @return array{private: string, public: string}
	 */
	private static function keyPair(string $name): array {
		if (!isset(self::$keys[$name])) {
			$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
			openssl_pkey_export($key, $private);
			self::$keys[$name] = ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
		}
		return self::$keys[$name];
	}

	private static function jwt(array $overrides = [], string $key = 'valid'): string {
		return JWT::encode(self::claims($overrides), self::keyPair($key)['private'], 'ES256');
	}

	public static function dataValidateExternalCallServiceRequest(): array {
		$base64Url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		$unsigned = $base64Url('{"alg":"none","typ":"JWT"}') . '.' . $base64Url(json_encode(self::claims(), JSON_THROW_ON_ERROR)) . '.';

		return [
			'valid' => [true, self::jwt(), 'PUT', self::PATH, self::BODY, true],
			'valid at oldest allowed time' => [true, self::jwt(['iat' => self::NOW - 60, 'exp' => self::NOW]), 'PUT', self::PATH, self::BODY, true],
			'valid at newest allowed time' => [true, self::jwt(['iat' => self::NOW + 60, 'exp' => self::NOW + 120]), 'PUT', self::PATH, self::BODY, true],
			'valid with query string' => [true, self::jwt(['uri' => self::PATH . '?format=json']), 'PUT', self::PATH . '?format=json', self::BODY, true],
			'valid in subdirectory' => [true, self::jwt(['uri' => '/nextcloud' . self::PATH]), 'PUT', '/nextcloud' . self::PATH, self::BODY, true],
			'not configured' => [false, self::jwt(), 'PUT', self::PATH, self::BODY, false],
			'missing token' => [true, '', 'PUT', self::PATH, self::BODY, false],
			'malformed token' => [true, 'not.a.jwt', 'PUT', self::PATH, self::BODY, false],
			'other key' => [true, self::jwt([], 'other'), 'PUT', self::PATH, self::BODY, false],
			'HMAC with the public key as secret' => [true, JWT::encode(self::claims(), self::keyPair('valid')['public'], 'HS256'), 'PUT', self::PATH, self::BODY, false],
			'unsigned token' => [true, $unsigned, 'PUT', self::PATH, self::BODY, false],
			'expired' => [true, self::jwt(['iat' => self::NOW - 61, 'exp' => self::NOW - 1]), 'PUT', self::PATH, self::BODY, false],
			'issued too far in the future' => [true, self::jwt(['iat' => self::NOW + 61, 'exp' => self::NOW + 121]), 'PUT', self::PATH, self::BODY, false],
			'lifetime too long' => [true, self::jwt(['exp' => self::NOW + 61]), 'PUT', self::PATH, self::BODY, false],
			'exp before iat' => [true, self::jwt(['iat' => self::NOW, 'exp' => self::NOW - 1]), 'PUT', self::PATH, self::BODY, false],
			'missing iat' => [true, self::jwt(['iat' => null]), 'PUT', self::PATH, self::BODY, false],
			'missing exp' => [true, self::jwt(['exp' => null]), 'PUT', self::PATH, self::BODY, false],
			'missing jti' => [true, self::jwt(['jti' => null]), 'PUT', self::PATH, self::BODY, false],
			'short jti' => [true, self::jwt(['jti' => 'short']), 'PUT', self::PATH, self::BODY, false],
			'missing method' => [true, self::jwt(['method' => null]), 'PUT', self::PATH, self::BODY, false],
			'missing uri' => [true, self::jwt(['uri' => null]), 'PUT', self::PATH, self::BODY, false],
			'missing body' => [true, self::jwt(['body' => null]), 'PUT', self::PATH, self::BODY, false],
			'other method' => [true, self::jwt(), 'POST', self::PATH, self::BODY, false],
			'other path' => [true, self::jwt(), 'PUT', self::PATH . '/participants', self::BODY, false],
			'added query string' => [true, self::jwt(), 'PUT', self::PATH . '?roomName=evil', self::BODY, false],
			'subdirectory not signed' => [true, self::jwt(), 'PUT', '/nextcloud' . self::PATH, self::BODY, false],
			'other body' => [true, self::jwt(), 'PUT', self::PATH, '{"owner":"alice","roomName":"Evil"}', false],
			'empty body' => [true, self::jwt(['body' => hash('sha256', '')]), 'PUT', self::PATH, '', false],
		];
	}

	#[DataProvider('dataValidateExternalCallServiceRequest')]
	public function testValidateExternalCallServiceRequest(bool $configured, string $jwt, string $method, string $uri, string $body, bool $expected): void {
		$service = $this->mockRequest($configured, $jwt, $method, $uri, $body);
		$this->cacheFactory->method('createDistributed')->willReturn(new ArrayCache());

		$this->assertSame($expected, $service->validateExternalCallServiceRequest());
		$this->assertNull(JWT::$timestamp);
		$this->assertSame(0, JWT::$leeway);
	}

	public function testValidateExternalCallServiceRequestRejectsReplay(): void {
		$service = $this->mockRequest(true, self::jwt(), 'PUT', self::PATH, self::BODY);
		$this->cacheFactory->method('createDistributed')->willReturn(new ArrayCache());

		$this->assertTrue($service->validateExternalCallServiceRequest());
		$this->assertFalse($service->validateExternalCallServiceRequest());
	}

	private function mockRequest(bool $configured, string $jwt, string $method, string $uri, string $body): ChecksumVerificationService {
		$this->config->method('isExternalCallServiceConfigured')->willReturn($configured);
		$this->config->method('getExternalCallServicePublicKey')->willReturn(self::keyPair('valid')['public']);
		$this->timeFactory->method('getTime')->willReturn(self::NOW);
		$this->request->method('getHeader')->willReturnMap([
			['x-nextcloud-talk-external-service-jwt', $jwt],
		]);
		$this->request->method('getMethod')->willReturn($method);
		$this->request->method('getRequestUri')->willReturn($uri);

		$service = $this->getMockBuilder(ChecksumVerificationService::class)
			->setConstructorArgs([$this->config, $this->request, $this->cacheFactory, $this->timeFactory])
			->onlyMethods(['getInputStream'])
			->getMock();
		$service->method('getInputStream')->willReturn($body);
		return $service;
	}
}
