<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Service\ChecksumVerificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ChecksumVerificationServiceTest extends TestCase {
	protected ChecksumVerificationService $service;

	public function setUp(): void {
		parent::setUp();

		$this->service = new ChecksumVerificationService();
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
			[str_repeat('1', 32), 'fake', '', '', 'No shared SIP secret provided', false],
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
}
