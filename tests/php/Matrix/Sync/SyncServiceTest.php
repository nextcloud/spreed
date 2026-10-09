<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Sync;

use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Exception\TransportException;
use OCA\Talk\Matrix\Client\Exception\UnknownTokenException;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SyncServiceTest extends TestCase {
	private AccountMapper&MockObject $accountMapper;
	private AccountService&MockObject $accountService;
	private RoomSyncService&MockObject $roomSyncService;
	private Client&MockObject $client;
	private SyncService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->accountMapper = $this->createMock(AccountMapper::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->roomSyncService = $this->createMock(RoomSyncService::class);
		$this->client = $this->createMock(Client::class);
		$this->accountService->method('getClient')->willReturn($this->client);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1000);

		$this->service = new SyncService(
			$this->accountMapper,
			$this->accountService,
			$this->roomSyncService,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function account(?string $nextBatch = null, int $status = Account::STATUS_ACTIVE): Account {
		return Account::fromRow([
			'id' => '7',
			'mxid' => '@alice:example.org',
			'status' => $status,
			'next_batch' => $nextBatch,
			'filter_id' => $nextBatch === null ? null : 'filter',
		]);
	}

	private static function batch(string $nextBatch, bool $empty = false): SyncBatch {
		return SyncBatch::fromArray([
			'next_batch' => $nextBatch,
			'rooms' => ['leave' => $empty ? [] : ['!room:example.org' => []]],
		]);
	}

	public function testInactiveAccountIsSkipped(): void {
		$this->accountMapper->expects(self::never())->method('acquireLock');

		self::assertNull($this->service->syncAccount($this->account(null, Account::STATUS_TOKEN_INVALID), 10));
	}

	public function testLockedAccountIsSkipped(): void {
		$account = $this->account();
		$this->accountMapper->method('acquireLock')->with($account, 1000, 1070)->willReturn(false);
		$this->client->expects(self::never())->method('sync');

		self::assertNull($this->service->syncAccount($account, 10));
	}

	public function testInitialSync(): void {
		$account = $this->account();
		$this->accountMapper->method('acquireLock')->willReturn(true);
		$this->client->expects(self::once())
			->method('createFilter')
			->with('@alice:example.org', SyncService::FILTER)
			->willReturn('filter');
		$this->client->expects(self::exactly(2))
			->method('sync')
			->willReturnCallback(fn (string $since, string $filterId): SyncBatch => match ($since) {
				'' => self::batch('first'),
				'first' => self::batch('second', true),
			});
		$this->roomSyncService->expects(self::exactly(2))
			->method('process')
			->willReturnCallback(static function (Account $account, SyncBatch $batch, bool $initial): array {
				self::assertSame($batch->nextBatch === 'first', $initial, 'Only the first batch is from the initial sync');
				return ['rooms' => 2, 'messages' => $initial ? 5 : 1, 'failed' => 0];
			});
		$this->accountMapper->expects(self::once())->method('releaseLock')->with($account);

		self::assertSame(['batches' => 2, 'rooms' => 4, 'messages' => 6, 'failed' => 0], $this->service->syncAccount($account, 10));
		self::assertSame('second', $account->getNextBatch());
		self::assertSame('filter', $account->getFilterId());
		self::assertSame(1000, $account->getLastSync());
		self::assertNull($account->getLastError());
	}

	public function testFailedInitialSyncIsRepeated(): void {
		$account = $this->account();
		$this->accountMapper->method('acquireLock')->willReturn(true);
		$this->client->method('createFilter')->willReturn('filter');
		$this->client->expects(self::once())->method('sync')->willReturn(self::batch('first'));
		$this->roomSyncService->method('process')->willReturn(['rooms' => 1, 'messages' => 0, 'failed' => 1]);

		$this->service->syncAccount($account, 10);

		self::assertNull($account->getNextBatch());
		self::assertSame('1 rooms failed in the initial sync, see the log', $account->getLastError());
	}

	public function testFailedRoomInIncrementalSyncIsSkipped(): void {
		$account = $this->account('first');
		$this->accountMapper->method('acquireLock')->willReturn(true);
		$this->client->expects(self::never())->method('createFilter');
		$this->client->method('sync')->with('first', 'filter')->willReturn(self::batch('second', true));
		$this->roomSyncService->method('process')->willReturn(['rooms' => 0, 'messages' => 0, 'failed' => 1]);

		$this->service->syncAccount($account, 10);

		self::assertSame('second', $account->getNextBatch());
		self::assertSame('1 rooms failed to sync, see the log', $account->getLastError());
	}

	public function testRejectedToken(): void {
		$account = $this->account('first');
		$this->accountMapper->method('acquireLock')->willReturn(true);
		$this->client->method('sync')->willThrowException(new UnknownTokenException('Token expired', 401, 'M_UNKNOWN_TOKEN'));
		$this->accountService->expects(self::once())->method('markTokenInvalid')->with($account, 'Token expired');
		$this->accountMapper->expects(self::once())->method('releaseLock')->with($account);

		$this->service->syncAccount($account, 10);
	}

	public function testUnreachableHomeserver(): void {
		$account = $this->account('first');
		$this->accountMapper->method('acquireLock')->willReturn(true);
		$this->client->method('sync')->willThrowException(new TransportException('Homeserver unreachable'));
		$this->accountService->expects(self::never())->method('markTokenInvalid');
		$this->accountMapper->expects(self::once())->method('releaseLock')->with($account);

		$this->service->syncAccount($account, 10);

		self::assertSame('first', $account->getNextBatch());
		self::assertSame('Homeserver unreachable', $account->getLastError());
	}
}
