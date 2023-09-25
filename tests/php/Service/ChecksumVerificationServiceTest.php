<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Service\ChecksumVerificationService;
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

	/**
	 * @dataProvider dataValidateRequest
	 */
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
