<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OCA\Talk\Config;
use OCA\Talk\Matrix\Adapter\NetworkException;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Client\Transport;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\HomeserverMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Defaults;
use OCP\IUser;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AccountServiceTest extends TestCase {
	private AccountMapper&MockObject $mapper;
	private HomeserverMapper&MockObject $homeserverMapper;
	private Config&MockObject $config;
	private IUser&MockObject $user;
	/** @var array<string, ResponseInterface|\Throwable> "METHOD path" => response */
	private array $responses = [];
	/** @var list<RequestInterface> */
	private array $requests = [];
	private AccountService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(AccountMapper::class);
		$this->mapper->method('insert')->willReturnArgument(0);
		$this->homeserverMapper = $this->createMock(HomeserverMapper::class);
		$this->config = $this->createMock(Config::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');

		$http = new class($this) implements ClientInterface {
			public function __construct(
				private readonly AccountServiceTest $test,
			) {
			}

			public function sendRequest(RequestInterface $request): ResponseInterface {
				return $this->test->respond($request);
			}
		};
		$clientFactory = $this->createMock(ClientFactory::class);
		$clientFactory->method('forHomeserver')->willReturnCallback(static function (Homeserver $homeserver, ?string $accessToken = null) use ($http): Client {
			$factory = new HttpFactory();
			return (new Client(new Transport($homeserver->getBaseUrl(), $http, $factory, $factory)))->withAccessToken($accessToken);
		});

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'encrypted:' . $plain);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $cipher): string => substr($cipher, strlen('encrypted:')));
		$defaults = $this->createMock(Defaults::class);
		$defaults->method('getName')->willReturn('Cloud');

		$this->service = new AccountService(
			$this->mapper,
			$this->homeserverMapper,
			$clientFactory,
			$this->config,
			$crypto,
			$defaults,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function respond(RequestInterface $request): ResponseInterface {
		$this->requests[] = $request;
		$response = $this->responses[$request->getMethod() . ' ' . $request->getUri()->getPath()] ?? new Response(404);
		if ($response instanceof \Throwable) {
			throw $response;
		}
		return $response;
	}

	private function json(int $status, array $body): Response {
		return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
	}

	private function homeserver(bool $enabled = true): Homeserver {
		$homeserver = Homeserver::fromRow([
			'id' => '42',
			'name' => 'Example',
			'server_name' => 'example.org',
			'base_url' => 'https://matrix.example.org',
			'enabled' => $enabled,
		]);
		$this->homeserverMapper->method('getById')->with('42')->willReturn($homeserver);
		return $homeserver;
	}

	private function allowLinking(): void {
		$this->config->method('canLinkMatrixAccount')->with($this->user)->willReturn(true);
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException(''));
	}

	public static function dataLinkUserInput(): array {
		return [
			['bob'],
			['@bob:example.org'],
			['bob:example.org'],
			['bob@example.org'],
		];
	}

	#[DataProvider('dataLinkUserInput')]
	public function testLink(string $input): void {
		$this->allowLinking();
		$this->homeserver();
		$this->responses['POST /_matrix/client/v3/login'] = $this->json(200, [
			'user_id' => '@bob:example.org',
			'access_token' => 'syt_token',
			'device_id' => 'DEVICE',
		]);

		$account = $this->service->link($this->user, '42', $input, 'secret');

		self::assertSame('alice', $account->getUserId());
		self::assertSame('42', $account->getHomeserverId());
		self::assertSame('@bob:example.org', $account->getMxid());
		self::assertSame('DEVICE', $account->getDeviceId());
		self::assertSame('encrypted:syt_token', $account->getAccessToken());

		self::assertCount(1, $this->requests);
		self::assertSame('https://matrix.example.org/_matrix/client/v3/login', (string)$this->requests[0]->getUri());
		self::assertFalse($this->requests[0]->hasHeader('Authorization'));
		self::assertSame([
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => 'bob'],
			'password' => 'secret',
			'initial_device_display_name' => 'Nextcloud Talk (Cloud)',
		], json_decode((string)$this->requests[0]->getBody(), true));
	}

	public function testLinkNotAllowed(): void {
		$this->config->method('canLinkMatrixAccount')->willReturn(false);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectExceptionObject(new \InvalidArgumentException('not-allowed'));
		$this->service->link($this->user, '42', 'bob', 'secret');
	}

	public function testLinkAlreadyLinked(): void {
		$this->config->method('canLinkMatrixAccount')->willReturn(true);
		$this->mapper->method('getByUserId')->with('alice')->willReturn(new Account());
		$this->mapper->expects(self::never())->method('insert');

		$this->expectExceptionObject(new \InvalidArgumentException('already-linked'));
		$this->service->link($this->user, '42', 'bob', 'secret');
	}

	public function testLinkUnknownHomeserver(): void {
		$this->allowLinking();
		$this->homeserverMapper->method('getById')->willThrowException(new DoesNotExistException(''));

		$this->expectExceptionObject(new \InvalidArgumentException('homeserver'));
		$this->service->link($this->user, '42', 'bob', 'secret');
	}

	public function testLinkDisabledHomeserver(): void {
		$this->allowLinking();
		$this->homeserver(false);

		$this->expectExceptionObject(new \InvalidArgumentException('homeserver'));
		$this->service->link($this->user, '42', 'bob', 'secret');
	}

	public static function dataLinkInvalidUser(): array {
		return [
			'other server' => ['@bob:other.org'],
			'empty' => [' '],
			'whitespace' => ['bob smith'],
		];
	}

	#[DataProvider('dataLinkInvalidUser')]
	public function testLinkInvalidUser(string $input): void {
		$this->allowLinking();
		$this->homeserver();

		$this->expectExceptionObject(new \InvalidArgumentException('user'));
		$this->service->link($this->user, '42', $input, 'secret');
	}

	public function testLinkWrongPassword(): void {
		$this->allowLinking();
		$this->homeserver();
		$this->responses['POST /_matrix/client/v3/login'] = $this->json(403, ['errcode' => 'M_FORBIDDEN', 'error' => 'Invalid password']);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(ForbiddenException::class);
		$this->service->link($this->user, '42', 'bob', 'wrong');
	}

	public function testLinkUnreachable(): void {
		$this->allowLinking();
		$this->homeserver();
		$this->responses['POST /_matrix/client/v3/login'] = new NetworkException($this->createMock(RequestInterface::class), 'Connection refused');
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(TransportException::class);
		$this->service->link($this->user, '42', 'bob', 'secret');
	}

	private function account(): Account {
		return Account::fromRow([
			'id' => '7',
			'user_id' => 'alice',
			'homeserver_id' => '42',
			'mxid' => '@bob:example.org',
			'access_token' => 'encrypted:syt_token',
			'device_id' => 'DEVICE',
		]);
	}

	public function testUnlinkLogsOut(): void {
		$this->homeserver();
		$account = $this->account();
		$this->responses['POST /_matrix/client/v3/logout'] = $this->json(200, []);
		$this->mapper->expects(self::once())->method('delete')->with($account);

		$this->service->unlink($account);

		self::assertCount(1, $this->requests);
		self::assertSame('https://matrix.example.org/_matrix/client/v3/logout', (string)$this->requests[0]->getUri());
		self::assertSame('Bearer syt_token', $this->requests[0]->getHeaderLine('Authorization'));
	}

	public function testUnlinkDeletesWhenLogoutFails(): void {
		$this->homeserver();
		$account = $this->account();
		$this->responses['POST /_matrix/client/v3/logout'] = $this->json(401, ['errcode' => 'M_UNKNOWN_TOKEN', 'error' => 'Unknown token']);
		$this->mapper->expects(self::once())->method('delete')->with($account);

		$this->service->unlink($account);
	}

	public function testUnlinkUserWithoutAccount(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects(self::never())->method('delete');

		$this->service->unlinkUser('alice');
	}
}
