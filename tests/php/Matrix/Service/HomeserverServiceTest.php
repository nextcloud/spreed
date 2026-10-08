<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OCA\Talk\Matrix\Client\Discovery;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Test\TestCase;

class HomeserverServiceTest extends TestCase {
	private HomeserverMapper&MockObject $mapper;
	private AccountMapper&MockObject $accountMapper;
	private ClientFactory&MockObject $clientFactory;
	private ITimeFactory&MockObject $timeFactory;
	/** @var array<string, ResponseInterface> URL => response, missing URLs answer 404 */
	private array $responses = [];
	/** @var list<string> */
	private array $requested = [];
	private HomeserverService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(HomeserverMapper::class);
		$this->mapper->method('insert')->willReturnArgument(0);
		$this->mapper->method('update')->willReturnArgument(0);
		$this->accountMapper = $this->createMock(AccountMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-08 12:00:00'));

		$http = new class($this) implements ClientInterface {
			public function __construct(
				private readonly HomeserverServiceTest $test,
			) {
			}

			public function sendRequest(RequestInterface $request): ResponseInterface {
				return $this->test->respond((string)$request->getUri());
			}
		};
		$this->clientFactory = $this->createMock(ClientFactory::class);
		$this->clientFactory->method('discovery')->willReturn(new Discovery($http, new HttpFactory()));

		$this->service = new HomeserverService($this->mapper, $this->accountMapper, $this->clientFactory, $this->timeFactory);
	}

	public function respond(string $url): ResponseInterface {
		$this->requested[] = $url;
		return $this->responses[$url] ?? new Response(404);
	}

	private function json(array $body): Response {
		return new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
	}

	public function testAddResolvesWellKnown(): void {
		$this->mapper->method('getByServerName')->willThrowException(new DoesNotExistException(''));
		$this->responses['https://example.org/.well-known/matrix/client'] = $this->json(['m.homeserver' => ['base_url' => 'https://matrix.example.org/']]);
		$this->responses['https://matrix.example.org/_matrix/client/versions'] = $this->json(['versions' => ['v1.11', 'v1.12']]);

		$homeserver = $this->service->add('', ' Example.ORG ', null);

		self::assertSame('example.org', $homeserver->getServerName());
		self::assertSame('example.org', $homeserver->getName(), 'label falls back to the server name');
		self::assertSame('https://matrix.example.org', $homeserver->getBaseUrl());
		self::assertSame(['v1.11', 'v1.12'], $homeserver->jsonSerialize()['specVersions']);
	}

	public function testAddFallsBackToServerNameWithoutWellKnown(): void {
		$this->mapper->method('getByServerName')->willThrowException(new DoesNotExistException(''));
		$this->responses['https://example.org/_matrix/client/versions'] = $this->json(['versions' => ['v1.12']]);

		$homeserver = $this->service->add('Example', 'example.org');

		self::assertSame('Example', $homeserver->getName());
		self::assertSame('https://example.org', $homeserver->getBaseUrl());
	}

	public function testAddWithBaseUrlSkipsWellKnown(): void {
		$this->mapper->method('getByServerName')->willThrowException(new DoesNotExistException(''));
		$this->responses['https://hs.example.org/_matrix/client/versions'] = $this->json(['versions' => ['v1.12']]);

		$homeserver = $this->service->add('', 'example.org', 'https://hs.example.org/');

		self::assertSame('https://hs.example.org', $homeserver->getBaseUrl());
		self::assertSame(['https://hs.example.org/_matrix/client/versions'], $this->requested);
	}

	public function testAddRejectsNonMatrixServer(): void {
		$this->mapper->method('getByServerName')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(TransportException::class);
		$this->service->add('', 'example.org');
	}

	public function testAddRejectsDuplicate(): void {
		$this->mapper->method('getByServerName')->willReturn(new Homeserver());
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('exists');
		$this->service->add('', 'example.org');
	}

	public function testAddRejectsEmptyServerName(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('server_name');
		$this->service->add('Label', '  ');
	}

	public function testUpdateRevalidatesChangedBaseUrlOnly(): void {
		$homeserver = new Homeserver();
		$homeserver->setName('Old');
		$homeserver->setBaseUrl('https://hs.example.org');
		$this->mapper->method('getById')->willReturn($homeserver);

		$this->service->update('1', ['name' => ' New ', 'enabled' => false, 'baseUrl' => 'https://hs.example.org/']);
		self::assertSame('New', $homeserver->getName());
		self::assertFalse($homeserver->getEnabled());
		self::assertSame([], $this->requested, 'unchanged base URL is not revalidated');

		$this->responses['https://new.example.org/_matrix/client/versions'] = $this->json(['versions' => ['v1.12']]);
		$this->service->update('1', ['baseUrl' => 'https://new.example.org']);
		self::assertSame('https://new.example.org', $homeserver->getBaseUrl());
	}

	public function testRemove(): void {
		$homeserver = new Homeserver();
		$this->mapper->method('getById')->with('42')->willReturn($homeserver);
		$this->accountMapper->method('hasAccountsOnHomeserver')->with('42')->willReturn(false);
		$this->mapper->expects(self::once())->method('delete')->with($homeserver);

		$this->service->remove('42');
	}

	public function testRemoveRejectsHomeserverWithLinkedAccounts(): void {
		$this->mapper->method('getById')->with('42')->willReturn(new Homeserver());
		$this->accountMapper->method('hasAccountsOnHomeserver')->with('42')->willReturn(true);
		$this->mapper->expects(self::never())->method('delete');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('accounts');
		$this->service->remove('42');
	}
}
