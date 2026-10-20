<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Config;
use OCA\Talk\Controller\CertificateController;
use OCA\Talk\Service\CertificateService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CertificateControllerTest extends TestCase {
	private CertificateService&MockObject $certificateService;
	private CertificateController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->certificateService = $this->getMockBuilder(CertificateService::class)
			->setConstructorArgs([$this->createMock(LoggerInterface::class)])
			->onlyMethods(['getCertificateExpirationInDays'])
			->getMock();

		$config = $this->createMock(Config::class);
		$config->method('getTurnServers')->with(false)->willReturn([
			['server' => 'turn.example.com:5349', 'schemes' => 'turns'],
		]);
		$config->method('getStunServers')->willReturn(['stun.example.com:443']);
		$config->method('getSignalingServers')->willReturn([
			['server' => 'https://hpb.example.com/standalone-signaling/', 'verify' => true],
		]);
		$config->method('getRecordingServers')->willReturn([
			['server' => 'https://recording.example.com:8443', 'verify' => true],
		]);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->controller = new CertificateController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->certificateService,
			$config,
			$l,
			$this->createMock(LoggerInterface::class),
		);
	}

	public static function dataAllowedHost(): array {
		return [
			['turn.example.com:5349'],
			['stun.example.com'],
			['hpb.example.com'],
			['HPB.example.com:443'],
			['wss://hpb.example.com/spreed'],
			['https://recording.example.com:8443/'],
		];
	}

	#[DataProvider('dataAllowedHost')]
	public function testAllowedHost(string $host): void {
		$this->certificateService->expects($this->once())
			->method('getCertificateExpirationInDays')
			->with($host)
			->willReturn(42);

		$response = $this->controller->getCertificateExpiration($host);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['expiration_in_days' => 42], $response->getData());
	}

	public static function dataDisallowedHost(): array {
		return [
			['internal.example.com'],
			['turn.example.com'],
			['hpb.example.com:22'],
			['https://recording.example.com'],
			['http://hpb.example.com'],
			[''],
		];
	}

	#[DataProvider('dataDisallowedHost')]
	public function testDisallowedHost(string $host): void {
		$this->certificateService->expects($this->never())
			->method('getCertificateExpirationInDays');

		$response = $this->controller->getCertificateExpiration($host);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
