<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\MatrixRoomController;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\DirectoryService;
use OCA\Talk\Matrix\Service\InvitationService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatrixRoomControllerTest extends TestCase {
	protected AccountService&MockObject $accountService;
	protected DirectoryService&MockObject $directoryService;
	protected Account $account;

	protected function setUp(): void {
		parent::setUp();
		$this->accountService = $this->createMock(AccountService::class);
		$this->directoryService = $this->createMock(DirectoryService::class);
		$this->account = new Account();
		$this->account->setUserId('alice');
		$this->account->setStatus(Account::STATUS_ACTIVE);
	}

	private function getController(?string $userId = 'alice'): MatrixRoomController {
		return new MatrixRoomController(
			'spreed',
			$this->createMock(IRequest::class),
			$userId,
			$this->accountService,
			$this->createMock(InvitationService::class),
			$this->directoryService,
		);
	}

	public function testSearchRooms(): void {
		$this->accountService->method('getForUser')->with('alice')->willReturn($this->account);
		$result = ['rooms' => [['roomId' => '!a:hs', 'alias' => '#a:hs', 'name' => 'A', 'topic' => '', 'members' => 1, 'joinRule' => 'public', 'token' => null]], 'nextBatch' => null, 'total' => 1];
		$this->directoryService->expects(self::once())
			->method('searchRooms')
			->with($this->account, 'a', 20, '')
			->willReturn($result);

		$response = $this->getController()->searchRooms('a');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($result, $response->getData());
	}

	public function testSearchUsers(): void {
		$this->accountService->method('getForUser')->with('alice')->willReturn($this->account);
		$result = ['users' => [['mxid' => '@bob:hs', 'displayName' => 'Bob']], 'limited' => false];
		$this->directoryService->expects(self::once())
			->method('searchUsers')
			->with($this->account, 'bo', 10)
			->willReturn($result);

		$response = $this->getController()->searchUsers('bo');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($result, $response->getData());
	}

	public function testSearchWithoutAccount(): void {
		$this->accountService->method('getForUser')->willReturn(null);
		$this->directoryService->expects(self::never())->method('searchRooms');
		$this->directoryService->expects(self::never())->method('searchUsers');

		foreach ([$this->getController()->searchRooms('a'), $this->getController()->searchUsers('a')] as $response) {
			self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
			self::assertSame(['error' => 'account'], $response->getData());
		}
	}

	public function testSearchWithLoggedOutAccount(): void {
		$this->account->setStatus(Account::STATUS_TOKEN_INVALID);
		$this->accountService->method('getForUser')->willReturn($this->account);
		$this->directoryService->expects(self::never())->method('searchRooms');

		self::assertSame(Http::STATUS_NOT_FOUND, $this->getController()->searchRooms('a')->getStatus());
	}

	public static function dataSearchError(): array {
		return [
			[new TransportException('Homeserver unreachable'), Http::STATUS_BAD_GATEWAY, 'unreachable'],
			[new MatrixException('Forbidden', 403, 'M_FORBIDDEN'), Http::STATUS_BAD_GATEWAY, 'matrix'],
			[new MatrixException('Too many requests', 429, 'M_LIMIT_EXCEEDED'), Http::STATUS_TOO_MANY_REQUESTS, 'ratelimited'],
			// A proxy in front of the homeserver without a Matrix error
			[new TransportException('Unexpected response from homeserver: HTTP 429', 429), Http::STATUS_TOO_MANY_REQUESTS, 'ratelimited'],
		];
	}

	#[DataProvider('dataSearchError')]
	public function testSearchError(MatrixException $exception, int $status, string $error): void {
		$this->accountService->method('getForUser')->willReturn($this->account);
		$this->directoryService->method('searchRooms')->willThrowException($exception);
		$this->directoryService->method('searchUsers')->willThrowException($exception);

		foreach ([$this->getController()->searchRooms('a'), $this->getController()->searchUsers('a')] as $response) {
			self::assertSame($status, $response->getStatus());
			self::assertSame(['error' => $error], $response->getData());
		}
	}
}
