<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\MatrixSsoController;
use OCA\Talk\Matrix\Client\Exception\ForbiddenException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatrixSsoControllerTest extends TestCase {
	private AccountService&MockObject $accountService;
	private HomeserverService&MockObject $homeserverService;
	private ITimeFactory&MockObject $timeFactory;
	/** @var array<string, mixed> */
	private array $sessionData = [];
	private MatrixSsoController $controller;

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$session = $this->createMock(ISession::class);
		$session->method('set')->willReturnCallback(function (string $key, mixed $value): void {
			$this->sessionData[$key] = $value;
		});
		$session->method('get')->willReturnCallback(fn (string $key): mixed => $this->sessionData[$key] ?? null);
		$session->method('remove')->willReturnCallback(function (string $key): void {
			unset($this->sessionData[$key]);
		});

		$this->accountService = $this->createMock(AccountService::class);
		$this->homeserverService = $this->createMock(HomeserverService::class);
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn(str_repeat('s', 48));
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')
			->with('spreed.MatrixSso.callback')
			->willReturnCallback(static fn (string $route, array $parameters): string => 'https://cloud.example.com/index.php/apps/spreed/matrix/sso/callback?' . http_build_query($parameters));
		$urlGenerator->method('linkToRoute')->with('spreed.Page.index')->willReturn('/index.php/apps/spreed/');
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(10000);

		$this->controller = new MatrixSsoController(
			'spreed',
			$this->createMock(IRequest::class),
			$userSession,
			$session,
			$this->accountService,
			$this->homeserverService,
			$secureRandom,
			$urlGenerator,
			$this->timeFactory,
		);
	}

	private function homeserver(bool $sso = true, bool $enabled = true): Homeserver {
		$homeserver = Homeserver::fromRow([
			'id' => '42',
			'name' => 'Example',
			'server_name' => 'example.org',
			'base_url' => 'https://matrix.example.org',
			'enabled' => $enabled,
		]);
		$this->homeserverService->method('get')->with('42')->willReturn($homeserver);
		$this->homeserverService->method('getLoginFlows')->willReturn([
			'password' => false,
			'sso' => $sso,
			'identityProviders' => $sso ? [['id' => 'oidc-surfconext', 'name' => 'SURFconext']] : [],
		]);
		return $homeserver;
	}

	public function testStart(): void {
		$this->homeserver();

		$response = $this->controller->start('42', 'oidc-surfconext');

		$callback = 'https://cloud.example.com/index.php/apps/spreed/matrix/sso/callback?state=' . str_repeat('s', 48);
		self::assertSame('https://matrix.example.org/_matrix/client/v3/login/sso/redirect/oidc-surfconext?redirectUrl=' . rawurlencode($callback), $response->getRedirectURL());
		self::assertSame([
			'state' => str_repeat('s', 48),
			'user' => 'alice',
			'homeserverId' => '42',
			'mode' => 'link',
			'time' => 10000,
		], $this->sessionData[MatrixSsoController::SESSION_KEY]);
	}

	public function testStartWithoutIdentityProvider(): void {
		$this->homeserver();

		$response = $this->controller->start('42');

		self::assertStringStartsWith('https://matrix.example.org/_matrix/client/v3/login/sso/redirect?redirectUrl=', $response->getRedirectURL());
	}

	public static function dataStartFails(): array {
		return [
			'unknown identity provider' => ['oidc-other', 'link', true, true, 'idp'],
			'no single sign-on' => ['', 'link', false, true, 'sso'],
			'disabled homeserver' => ['', 'link', true, false, 'homeserver'],
			'invalid mode' => ['', 'other', true, true, 'mode'],
		];
	}

	#[DataProvider('dataStartFails')]
	public function testStartFails(string $idp, string $mode, bool $sso, bool $enabled, string $reason): void {
		$this->homeserver($sso, $enabled);

		$response = $this->controller->start('42', $idp, $mode);

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=' . $reason, $response->getRedirectURL());
		self::assertArrayNotHasKey(MatrixSsoController::SESSION_KEY, $this->sessionData);
	}

	public function testStartUnknownHomeserver(): void {
		$this->homeserverService->method('get')->willThrowException(new DoesNotExistException(''));

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=homeserver', $this->controller->start('42')->getRedirectURL());
	}

	public function testStartReloginUsesTheHomeserverOfTheAccount(): void {
		$this->homeserver();
		$this->accountService->method('getForUser')->with('alice')->willReturn(Account::fromRow(['id' => '7', 'homeserver_id' => '42']));

		$this->controller->start('0', '', 'relogin');

		self::assertSame('42', $this->sessionData[MatrixSsoController::SESSION_KEY]['homeserverId']);
		self::assertSame('relogin', $this->sessionData[MatrixSsoController::SESSION_KEY]['mode']);
	}

	private function started(string $mode = 'link', int $time = 10000, string $user = 'alice'): void {
		$this->sessionData[MatrixSsoController::SESSION_KEY] = [
			'state' => str_repeat('s', 48),
			'user' => $user,
			'homeserverId' => '42',
			'mode' => $mode,
			'time' => $time,
		];
	}

	public function testCallbackLinks(): void {
		$this->started();
		$this->accountService->expects(self::once())
			->method('linkWithLoginToken')
			->with(self::anything(), '42', 'login-token')
			->willReturn(new Account());

		$response = $this->controller->callback(str_repeat('s', 48), 'login-token');

		self::assertSame('/index.php/apps/spreed/?matrix_sso=ok', $response->getRedirectURL());
		self::assertArrayNotHasKey(MatrixSsoController::SESSION_KEY, $this->sessionData, 'The state is only used once');
	}

	public function testCallbackLogsInAgain(): void {
		$this->started('relogin');
		$account = Account::fromRow(['id' => '7', 'homeserver_id' => '42']);
		$this->accountService->method('getForUser')->with('alice')->willReturn($account);
		$this->accountService->expects(self::never())->method('linkWithLoginToken');
		$this->accountService->expects(self::once())
			->method('reloginWithLoginToken')
			->with(self::anything(), $account, 'login-token')
			->willReturn($account);

		self::assertSame('/index.php/apps/spreed/?matrix_sso=ok', $this->controller->callback(str_repeat('s', 48), 'login-token')->getRedirectURL());
	}

	public static function dataCallbackFails(): array {
		return [
			'wrong state' => [str_repeat('x', 48), 'login-token', 10000, 'alice', 'state', true],
			'empty state' => ['', 'login-token', 10000, 'alice', 'state', true],
			'other user' => [str_repeat('s', 48), 'login-token', 10000, 'mallory', 'state', true],
			'expired' => [str_repeat('s', 48), 'login-token', 10000 - MatrixSsoController::STATE_TTL - 1, 'alice', 'expired', false],
			'cancelled' => [str_repeat('s', 48), '', 10000, 'alice', 'cancelled', false],
		];
	}

	#[DataProvider('dataCallbackFails')]
	public function testCallbackFails(string $state, string $loginToken, int $time, string $user, string $reason, bool $throttled): void {
		$this->started('link', $time, $user);
		$this->accountService->expects(self::never())->method('linkWithLoginToken');

		$response = $this->controller->callback($state, $loginToken);

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=' . $reason, $response->getRedirectURL());
		self::assertSame($throttled, $response->isThrottled());
		self::assertArrayNotHasKey(MatrixSsoController::SESSION_KEY, $this->sessionData);
	}

	public function testCallbackWithoutStart(): void {
		$this->accountService->expects(self::never())->method('linkWithLoginToken');

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=state', $this->controller->callback(str_repeat('s', 48), 'login-token')->getRedirectURL());
	}

	public function testCallbackWithRejectedToken(): void {
		$this->started();
		$this->accountService->method('linkWithLoginToken')->willThrowException(new ForbiddenException('Invalid login token', 403, 'M_FORBIDDEN'));

		$response = $this->controller->callback(str_repeat('s', 48), 'login-token');

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=token', $response->getRedirectURL());
		self::assertTrue($response->isThrottled());
	}

	public function testCallbackWithUserOfOtherHomeserver(): void {
		$this->started();
		$this->accountService->method('linkWithLoginToken')->willThrowException(new \InvalidArgumentException('user'));

		self::assertSame('/index.php/apps/spreed/?matrix_sso=error&reason=user', $this->controller->callback(str_repeat('s', 48), 'login-token')->getRedirectURL());
	}
}
