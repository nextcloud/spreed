<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OC\Memcache\ArrayCache;
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
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\ICacheFactory;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
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
	private RoomSyncService&MockObject $roomSyncService;
	private INotificationManager&MockObject $notificationManager;
	private INotification&MockObject $notification;
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
		$this->mapper->method('update')->willReturnArgument(0);
		$this->homeserverMapper = $this->createMock(HomeserverMapper::class);
		$this->config = $this->createMock(Config::class);
		$this->roomSyncService = $this->createMock(RoomSyncService::class);
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
		$this->notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setObject', 'setSubject', 'setDateTime'] as $setter) {
			$this->notification->method($setter)->willReturnSelf();
		}
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->notificationManager->method('createNotification')->willReturn($this->notification);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-08 12:00:00'));
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn(new ArrayCache(''));

		$this->service = new AccountService(
			$this->mapper,
			$this->homeserverMapper,
			$clientFactory,
			$this->roomSyncService,
			$this->config,
			$crypto,
			$defaults,
			$this->notificationManager,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
			$cacheFactory,
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

	private function account(int $status = Account::STATUS_ACTIVE): Account {
		return Account::fromRow([
			'id' => '7',
			'user_id' => 'alice',
			'homeserver_id' => '42',
			'mxid' => '@bob:example.org',
			'access_token' => $status === Account::STATUS_ACTIVE ? 'encrypted:syt_token' : '',
			'device_id' => 'DEVICE',
			'status' => $status,
			'last_error' => $status === Account::STATUS_ACTIVE ? null : 'Token expired',
		]);
	}

	public function testRelogin(): void {
		$this->config->method('canLinkMatrixAccount')->with($this->user)->willReturn(true);
		$this->homeserver();
		$this->responses['POST /_matrix/client/v3/login'] = $this->json(200, [
			'user_id' => '@bob:example.org',
			'access_token' => 'syt_new',
			'device_id' => 'DEVICE',
		]);
		$this->notification->expects(self::once())->method('setObject')->with('matrix_account', '7')->willReturnSelf();
		$this->notificationManager->expects(self::once())->method('markProcessed')->with($this->notification);

		$account = $this->service->relogin($this->user, $this->account(Account::STATUS_TOKEN_INVALID), 'secret');

		self::assertSame(Account::STATUS_ACTIVE, $account->getStatus());
		self::assertNull($account->getLastError());
		self::assertSame('encrypted:syt_new', $account->getAccessToken());
		self::assertSame([
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => 'bob'],
			'password' => 'secret',
			'initial_device_display_name' => 'Nextcloud Talk (Cloud)',
			'device_id' => 'DEVICE',
		], json_decode((string)$this->requests[0]->getBody(), true));
	}

	public function testReloginAsOtherUser(): void {
		$this->config->method('canLinkMatrixAccount')->willReturn(true);
		$this->homeserver();
		$this->responses['POST /_matrix/client/v3/login'] = $this->json(200, [
			'user_id' => '@mallory:example.org',
			'access_token' => 'syt_other',
			'device_id' => 'OTHER',
		]);
		$this->responses['POST /_matrix/client/v3/logout'] = $this->json(200, []);
		$this->mapper->expects(self::never())->method('update');

		try {
			$this->service->relogin($this->user, $this->account(Account::STATUS_TOKEN_INVALID), 'secret');
			self::fail('Expected exception');
		} catch (\InvalidArgumentException $e) {
			self::assertSame('user', $e->getMessage());
		}
		self::assertCount(2, $this->requests);
		self::assertSame('/_matrix/client/v3/logout', $this->requests[1]->getUri()->getPath());
		self::assertSame('Bearer syt_other', $this->requests[1]->getHeaderLine('Authorization'));
	}

	public function testReloginNotAllowed(): void {
		$this->config->method('canLinkMatrixAccount')->willReturn(false);

		$this->expectExceptionObject(new \InvalidArgumentException('not-allowed'));
		$this->service->relogin($this->user, $this->account(Account::STATUS_TOKEN_INVALID), 'secret');
	}

	public function testCheckConnectionValid(): void {
		$this->homeserver();
		$this->responses['GET /_matrix/client/v3/account/whoami'] = $this->json(200, ['user_id' => '@bob:example.org']);
		$this->mapper->expects(self::never())->method('update');
		$account = $this->account();

		self::assertTrue($this->service->checkConnection($account));
		self::assertSame(Account::STATUS_ACTIVE, $account->getStatus());
		self::assertSame('Bearer syt_token', $this->requests[0]->getHeaderLine('Authorization'));
	}

	public function testCheckConnectionIsCachedUnlessForced(): void {
		$this->homeserver();
		$this->responses['GET /_matrix/client/v3/account/whoami'] = $this->json(200, ['user_id' => '@bob:example.org']);
		$account = $this->account();

		self::assertTrue($this->service->checkConnection($account));
		self::assertTrue($this->service->checkConnection($account));
		self::assertCount(1, $this->requests);

		$this->responses['GET /_matrix/client/v3/account/whoami'] = new NetworkException($this->createMock(RequestInterface::class), 'Connection refused');
		self::assertFalse($this->service->checkConnection($account, true));
		self::assertFalse($this->service->checkConnection($account));
		self::assertCount(2, $this->requests);
	}

	public function testCheckConnectionRejected(): void {
		$this->homeserver();
		$this->responses['GET /_matrix/client/v3/account/whoami'] = $this->json(401, ['errcode' => 'M_UNKNOWN_TOKEN', 'error' => 'Access token has expired']);
		$this->notification->expects(self::once())->method('setSubject')->with('matrix_relogin', ['mxid' => '@bob:example.org'])->willReturnSelf();
		$this->notificationManager->expects(self::once())->method('notify')->with($this->notification);
		$account = $this->account();

		self::assertFalse($this->service->checkConnection($account));
		self::assertSame(Account::STATUS_TOKEN_INVALID, $account->getStatus());
		self::assertSame('Access token has expired', $account->getLastError());
		self::assertSame('', $account->getAccessToken());
	}

	public function testCheckConnectionUnreachable(): void {
		$this->homeserver();
		$this->responses['GET /_matrix/client/v3/account/whoami'] = new NetworkException($this->createMock(RequestInterface::class), 'Connection refused');
		$this->mapper->expects(self::never())->method('update');
		$this->notificationManager->expects(self::never())->method('notify');
		$account = $this->account();

		self::assertFalse($this->service->checkConnection($account));
		self::assertSame(Account::STATUS_ACTIVE, $account->getStatus());
	}

	public function testCheckConnectionSkipsInvalidAccount(): void {
		self::assertFalse($this->service->checkConnection($this->account(Account::STATUS_TOKEN_INVALID), true));
		self::assertSame([], $this->requests);
	}

	public function testUnlinkInvalidAccountSkipsLogout(): void {
		$account = $this->account(Account::STATUS_TOKEN_INVALID);
		$this->notificationManager->expects(self::once())->method('markProcessed')->with($this->notification);
		$this->mapper->expects(self::once())->method('delete')->with($account);

		$this->service->unlink($account);

		self::assertSame([], $this->requests);
	}

	public function testUnlinkLogsOut(): void {
		$this->homeserver();
		$account = $this->account();
		$this->responses['POST /_matrix/client/v3/logout'] = $this->json(200, []);
		$this->mapper->expects(self::once())->method('delete')->with($account);

		$this->roomSyncService->expects(self::once())->method('removeAccount')->with($account);

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

	public function testUnlinkAllOnHomeserver(): void {
		$this->homeserver();
		$first = $this->account();
		$second = $this->account();
		$this->responses['POST /_matrix/client/v3/logout'] = $this->json(200, []);
		$this->mapper->method('getByHomeserver')
			->with('42', 1000)
			->willReturnOnConsecutiveCalls([$first, $second], []);
		$deleted = [];
		$this->mapper->expects(self::exactly(2))
			->method('delete')
			->willReturnCallback(static function (Account $account) use (&$deleted): Account {
				$deleted[] = $account;
				return $account;
			});

		self::assertSame(2, $this->service->unlinkAllOnHomeserver('42'));
		self::assertSame([$first, $second], $deleted);
		self::assertCount(2, $this->requests);
	}

	public function testUnlinkUserWithoutAccount(): void {
		$this->mapper->method('getByUserId')->willThrowException(new DoesNotExistException(''));
		$this->mapper->expects(self::never())->method('delete');

		$this->service->unlinkUser('alice');
	}
}
