<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\InvitationService;
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class InvitationServiceTest extends TestCase {
	private MatrixRoomMapper&MockObject $roomMapper;
	private MatrixMemberMapper&MockObject $memberMapper;
	private RoomSyncService&MockObject $roomSyncService;
	private SyncService&MockObject $syncService;
	private Manager&MockObject $manager;
	private Client&MockObject $client;
	private Account $account;
	private InvitationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->account = Account::fromRow(['id' => '7', 'user_id' => 'alice', 'mxid' => '@alice:example.org']);
		$this->client = $this->createMock(Client::class);
		$accountService = $this->createMock(AccountService::class);
		$accountService->method('getClient')->with($this->account)->willReturn($this->client);
		$this->roomMapper = $this->createMock(MatrixRoomMapper::class);
		$this->memberMapper = $this->createMock(MatrixMemberMapper::class);
		$this->roomSyncService = $this->createMock(RoomSyncService::class);
		$this->syncService = $this->createMock(SyncService::class);
		$this->manager = $this->createMock(Manager::class);

		$this->service = new InvitationService(
			$accountService,
			$this->roomMapper,
			$this->memberMapper,
			$this->roomSyncService,
			$this->syncService,
			$this->manager,
		);
	}

	private function invited(string $membership = 'invite', int $roomId = 0): void {
		$this->roomMapper->method('getById')->with('100')->willReturn(MatrixRoom::fromRow(['id' => '100', 'room_id' => $roomId, 'matrix_room_id' => '!room:example.org']));
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@alice:example.org' => MatrixMember::fromRow(['mxid' => '@alice:example.org', 'membership' => $membership, 'account_id' => '7']),
		]);
	}

	public function testAccept(): void {
		$this->roomMapper->method('getById')->with('100')->willReturn(MatrixRoom::fromRow(['id' => '100', 'room_id' => 0, 'matrix_room_id' => '!room:example.org']));
		$this->roomMapper->method('getByMatrixRoomId')->with('!room:example.org')->willReturn(MatrixRoom::fromRow(['id' => '100', 'room_id' => 23, 'matrix_room_id' => '!room:example.org']));
		$this->memberMapper->method('getForRoom')->willReturn([
			'@alice:example.org' => MatrixMember::fromRow(['mxid' => '@alice:example.org', 'membership' => 'invite', 'account_id' => '7']),
		]);
		$this->client->expects(self::once())->method('join')->with('!room:example.org', ['example.org']);
		$this->syncService->expects(self::once())->method('syncAccount')->with($this->account, InvitationService::SYNC_BUDGET);
		$room = $this->createMock(Room::class);
		$this->manager->method('getRoomById')->with(23)->willReturn($room);

		self::assertSame($room, $this->service->accept($this->account, '100'));
	}

	public function testAcceptWithoutInvite(): void {
		$this->invited('join');
		$this->client->expects(self::never())->method('join');

		$this->expectExceptionObject(new \InvalidArgumentException('invitation'));
		$this->service->accept($this->account, '100');
	}

	public function testAcceptUnknownRoom(): void {
		$this->roomMapper->method('getById')->willThrowException(new DoesNotExistException(''));

		$this->expectExceptionObject(new \InvalidArgumentException('invitation'));
		$this->service->accept($this->account, '100');
	}

	public function testDecline(): void {
		$this->invited();
		$this->client->expects(self::once())->method('leave')->with('!room:example.org');
		$this->roomSyncService->expects(self::once())->method('leaveRoom')->with($this->account, '!room:example.org');

		$this->service->decline($this->account, '100');
	}

	public function testJoin(): void {
		$this->client->expects(self::once())
			->method('join')
			->with('#room:example.org', ['other.org'])
			->willReturn('!room:example.org');
		$this->syncService->expects(self::once())->method('syncAccount');
		$this->roomMapper->method('getByMatrixRoomId')->with('!room:example.org')->willThrowException(new DoesNotExistException(''));

		self::assertNull($this->service->join($this->account, 'https://matrix.to/#/%23room:example.org?via=other.org'), 'Not created yet by another sync');
	}

	public function testJoinInvalidReference(): void {
		$this->client->expects(self::never())->method('join');

		$this->expectExceptionObject(new \InvalidArgumentException('reference'));
		$this->service->join($this->account, 'room');
	}

	public function testCreateRoom(): void {
		$this->client->expects(self::once())
			->method('createRoom')
			->with([
				'preset' => 'private_chat',
				'visibility' => 'private',
				'invite' => ['@bob:example.org'],
				'is_direct' => false,
				'name' => 'Team',
				'topic' => 'About us',
			])
			->willReturn('!room:example.org');
		$this->roomMapper->method('getByMatrixRoomId')->willReturn(MatrixRoom::fromRow(['id' => '100', 'room_id' => 23, 'matrix_room_id' => '!room:example.org']));
		$room = $this->createMock(Room::class);
		$this->manager->method('getRoomById')->with(23)->willReturn($room);

		self::assertSame($room, $this->service->create($this->account, ' Team ', ' About us ', ['@bob:example.org', '@bob:example.org'], false));
	}

	public function testCreateDirectChat(): void {
		$this->client->expects(self::once())
			->method('createRoom')
			->with(['preset' => 'trusted_private_chat', 'visibility' => 'private', 'invite' => ['@bob:example.org'], 'is_direct' => true])
			->willReturn('!dm:example.org');
		$this->roomMapper->method('getByMatrixRoomId')->willThrowException(new DoesNotExistException(''));

		self::assertNull($this->service->create($this->account, '', '', ['@bob:example.org'], true));
	}

	public static function dataCreateInvalid(): array {
		return [
			'room without name' => [' ', [], false, 'name'],
			'invalid user' => ['Team', ['bob'], false, 'invite'],
			'direct chat without user' => ['', [], true, 'invite'],
			'direct chat with two users' => ['', ['@bob:example.org', '@carol:example.org'], true, 'invite'],
		];
	}

	#[DataProvider('dataCreateInvalid')]
	public function testCreateInvalid(string $name, array $invites, bool $direct, string $error): void {
		$this->client->expects(self::never())->method('createRoom');

		$this->expectExceptionObject(new \InvalidArgumentException($error));
		$this->service->create($this->account, $name, '', $invites, $direct);
	}
}
