<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\MatrixAccountController;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\CryptoException;
use OCA\Talk\Matrix\Client\Crypto\Verification\SasVerification;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\CryptoService;
use OCA\Talk\Matrix\Service\VerificationService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Http;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatrixAccountControllerTest extends TestCase {
	private AccountService&MockObject $accountService;
	private CryptoService&MockObject $cryptoService;
	private VerificationService&MockObject $verificationService;
	private SyncService&MockObject $syncService;
	private Account $account;
	private MatrixAccountController $controller;

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$this->account = Account::fromRow(['id' => '7', 'user_id' => 'alice', 'mxid' => '@alice:hs', 'status' => Account::STATUS_ACTIVE]);
		$this->accountService = $this->createMock(AccountService::class);
		$this->accountService->method('getForUser')->with('alice')->willReturnCallback(fn (): Account => $this->account);
		$this->accountService->method('getClient')->willReturn($this->createMock(Client::class));
		$this->cryptoService = $this->createMock(CryptoService::class);
		$this->verificationService = $this->createMock(VerificationService::class);
		$this->syncService = $this->createMock(SyncService::class);

		$arguments = [];
		foreach ((new \ReflectionClass(MatrixAccountController::class))->getConstructor()->getParameters() as $parameter) {
			$arguments[] = match ((string)$parameter->getType()) {
				'string' => 'spreed',
				IUserSession::class => $userSession,
				AccountService::class => $this->accountService,
				CryptoService::class => $this->cryptoService,
				VerificationService::class => $this->verificationService,
				SyncService::class => $this->syncService,
				default => $this->createMock((string)$parameter->getType()),
			};
		}
		$this->controller = new MatrixAccountController(...$arguments);
	}

	public function testStartVerificationWithoutSodium(): void {
		$this->cryptoService->method('isAvailable')->willReturn(false);
		$this->verificationService->expects(self::never())->method('start');

		self::assertSame(['error' => 'unavailable'], $this->controller->startVerification()->getData());
	}

	public function testStartVerificationSyncsWhenThereAreNoKeys(): void {
		$this->cryptoService->method('isAvailable')->willReturn(true);
		$this->cryptoService->method('getSigningKey')->willReturnOnConsecutiveCalls(null, 'key');
		$this->syncService->expects(self::once())->method('syncAccount')->with($this->account);
		$this->verificationService->expects(self::once())
			->method('start')
			->with($this->account, self::anything(), 'key')
			->willReturn(SasVerification::request('txn', '@alice:hs', 'TALK', 'key', 1000));

		self::assertSame(Http::STATUS_CREATED, $this->controller->startVerification()->getStatus());
	}

	public function testStartVerificationWithoutKeysAfterSync(): void {
		$this->cryptoService->method('isAvailable')->willReturn(true);
		$this->cryptoService->method('getSigningKey')->willReturn(null);
		$this->verificationService->expects(self::never())->method('start');

		self::assertSame(['error' => 'keys'], $this->controller->startVerification()->getData());
	}

	public function testStartVerificationOfInactiveAccount(): void {
		$this->account->setStatus(Account::STATUS_TOKEN_INVALID);
		$this->verificationService->expects(self::never())->method('start');

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->startVerification()->getStatus());
	}

	public function testStartVerification(): void {
		$this->cryptoService->method('isAvailable')->willReturn(true);
		$this->cryptoService->method('getSigningKey')->willReturn('key');
		$this->syncService->expects(self::never())->method('syncAccount');
		$this->verificationService->expects(self::once())
			->method('start')
			->with($this->account, self::anything(), 'key')
			->willReturn(SasVerification::request('txn', '@alice:hs', 'TALK', 'key', 1000));

		$response = $this->controller->startVerification();

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		self::assertSame(['state' => SasVerification::STATE_REQUESTED, 'emoji' => [], 'reason' => null], $response->getData());
	}

	public function testGetRunningVerificationSyncsFirst(): void {
		$verification = SasVerification::request('txn', '@alice:hs', 'TALK', 'key', 1000);
		$this->verificationService->method('get')->willReturn($verification);
		$this->syncService->expects(self::once())->method('syncAccount')->with($this->account);

		self::assertSame(SasVerification::STATE_REQUESTED, $this->controller->getVerification()->getData()['state']);
	}

	public function testGetFinishedVerificationDoesNotSync(): void {
		$verification = SasVerification::request('txn', '@alice:hs', 'TALK', 'key', 1000);
		$verification->cancel('m.user', 'Cancelled by the user');
		$this->verificationService->method('get')->willReturn($verification);
		$this->syncService->expects(self::never())->method('syncAccount');

		self::assertSame(['state' => SasVerification::STATE_CANCELLED, 'emoji' => [], 'reason' => 'Cancelled by the user'], $this->controller->getVerification()->getData());
	}

	public function testGetWithoutVerification(): void {
		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->getVerification()->getStatus());
	}

	public function testConfirmWithoutEmoji(): void {
		$this->verificationService->method('confirm')->willThrowException(new CryptoException('Nothing to confirm'));

		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller->confirmVerification()->getStatus());
	}
}
