<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\DirectoryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DirectoryServiceTest extends TestCase {
	protected AccountService&MockObject $accountService;
	protected MatrixRoomMapper&MockObject $roomMapper;
	protected Client&MockObject $client;
	protected Account $account;
	protected DirectoryService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->accountService = $this->createMock(AccountService::class);
		$this->roomMapper = $this->createMock(MatrixRoomMapper::class);
		$this->client = $this->createMock(Client::class);

		$this->account = new Account();
		$this->account->setUserId('alice');
		$this->account->setMxid('@alice:hs');

		$this->service = new DirectoryService($this->accountService, $this->roomMapper);
	}

	public function testSearchRoomsAddsTheConversationsOfTheUser(): void {
		$this->accountService->method('getClient')->willReturn($this->client);
		$this->client->expects(self::once())
			->method('searchPublicRooms')
			->with('team', 20, 'p1')
			->willReturn([
				'rooms' => [
					['roomId' => '!mine:hs', 'alias' => '#mine:hs', 'name' => 'Mine', 'topic' => '', 'members' => 2, 'joinRule' => 'public'],
					['roomId' => '!other:hs', 'alias' => '', 'name' => 'Other', 'topic' => '', 'members' => 5, 'joinRule' => 'knock'],
				],
				'nextBatch' => 'p2',
				'total' => 40,
			]);
		$this->roomMapper->expects(self::once())
			->method('findConversationTokens')
			->with(['!mine:hs', '!other:hs'], 'alice')
			->willReturn(['!mine:hs' => 'abcdef']);

		$result = $this->service->searchRooms($this->account, ' team ', 20, 'p1');

		self::assertSame(['abcdef', null], array_column($result['rooms'], 'token'));
		self::assertSame(['public', 'knock'], array_column($result['rooms'], 'joinRule'));
		self::assertSame('p2', $result['nextBatch']);
		self::assertSame(40, $result['total']);
	}

	public static function dataLimit(): array {
		return [
			[0, 1],
			[20, 20],
			[500, DirectoryService::MAX_LIMIT],
		];
	}

	#[DataProvider('dataLimit')]
	public function testSearchRoomsLimit(int $limit, int $expected): void {
		$this->accountService->method('getClient')->willReturn($this->client);
		$this->client->expects(self::once())
			->method('searchPublicRooms')
			->with('', $expected, '')
			->willReturn(['rooms' => [], 'nextBatch' => null, 'total' => null]);

		$this->service->searchRooms($this->account, '', $limit);
	}

	public function testSearchUsersWithoutSearchTerm(): void {
		$this->accountService->expects(self::never())->method('getClient');

		self::assertSame(['users' => [], 'limited' => false], $this->service->searchUsers($this->account, '  ', 10));
	}

	public function testSearchUsersLeavesOutTheOwnAccount(): void {
		$this->accountService->method('getClient')->willReturn($this->client);
		$this->client->expects(self::once())
			->method('searchUsers')
			->with('al', 10)
			->willReturn([
				'users' => [
					['mxid' => '@alice:hs', 'displayName' => 'Alice'],
					['mxid' => '@albert:hs', 'displayName' => 'Albert'],
				],
				'limited' => false,
			]);

		self::assertSame([
			'users' => [['mxid' => '@albert:hs', 'displayName' => 'Albert']],
			'limited' => false,
		], $this->service->searchUsers($this->account, 'al', 10));
	}
}
