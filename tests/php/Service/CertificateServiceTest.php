<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Service\CertificateService;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CertificateServiceTest extends TestCase {
	protected CertificateService $service;

	public function setUp(): void {
		parent::setUp();

		$logger = $this->createMock(LoggerInterface::class);
		$this->service = new CertificateService($logger);
	}

	public function testGetParsedTlsHost(): void {
		$actual = $this->service->getParsedTlsHost('domain.com');
		$this->assertEquals($actual, 'domain.com');

		$actual = $this->service->getParsedTlsHost('subdomain.domain.com');
		$this->assertEquals($actual, 'subdomain.domain.com');

		$actual = $this->service->getParsedTlsHost('https://domain.com');
		$this->assertEquals($actual, 'domain.com');

		$actual = $this->service->getParsedTlsHost('https://domain.com:1234');
		$this->assertEquals($actual, 'domain.com:1234');

		$actual = $this->service->getParsedTlsHost('https://domain.com:1234/path/1/');
		$this->assertEquals($actual, 'domain.com:1234');

		$actual = $this->service->getParsedTlsHost('http://domain.com:1234/path/1/');
		$this->assertNull($actual);
	}
}
