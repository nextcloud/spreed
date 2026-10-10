<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Sync;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\MessageSyncService;
use OCA\Talk\Matrix\Sync\RoomSyncService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RoomSyncServiceTest extends TestCase {
	private Manager&MockObject $manager;
	private RoomService&MockObject $roomService;
	private ParticipantService&MockObject $participantService;
	private MatrixRoomMapper&MockObject $roomMapper;
	private MatrixMemberMapper&MockObject $memberMapper;
	private AccountMapper&MockObject $accountMapper;
	private EventMapMapper&MockObject $eventMapMapper;
	private MessageSyncService&MockObject $messageSyncService;
	private INotificationManager&MockObject $notificationManager;
	private INotification&MockObject $notification;
	private Room&MockObject $room;
	/** @var array<string, MatrixMember> */
	private array $insertedMembers = [];
	private RoomSyncService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->roomMapper = $this->createMock(MatrixRoomMapper::class);
		$this->memberMapper = $this->createMock(MatrixMemberMapper::class);
		$this->memberMapper->method('insert')->willReturnCallback(function (MatrixMember $member): MatrixMember {
			$inserted = MatrixMember::fromRow([
				'id' => (string)(count($this->insertedMembers) + 1),
				'matrix_room_id' => $member->getMatrixRoomId(),
				'mxid' => $member->getMxid(),
				'membership' => $member->getMembership(),
				'display_name' => $member->getDisplayName(),
				'account_id' => $member->getAccountId(),
			]);
			$this->insertedMembers[$member->getMxid()] = $inserted;
			return $inserted;
		});
		$this->accountMapper = $this->createMock(AccountMapper::class);
		$this->accountMapper->method('getByMxids')->willReturnCallback(fn (array $mxids): array => array_intersect_key(
			['@alice:example.org' => $this->account()],
			array_flip($mxids),
		));
		$this->eventMapMapper = $this->createMock(EventMapMapper::class);
		$this->messageSyncService = $this->createMock(MessageSyncService::class);
		$this->notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setObject', 'setSubject', 'setDateTime'] as $setter) {
			$this->notification->method($setter)->willReturnSelf();
		}
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->notificationManager->method('createNotification')->willReturn($this->notification);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-09 12:00:00'));
		$this->room = $this->createMock(Room::class);
		$this->room->method('getId')->willReturn(23);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->service = new RoomSyncService(
			$this->manager,
			$this->roomService,
			$this->participantService,
			$this->roomMapper,
			$this->memberMapper,
			$this->accountMapper,
			$this->eventMapMapper,
			$this->messageSyncService,
			$this->notificationManager,
			$timeFactory,
			$l,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function account(): Account {
		return Account::fromRow([
			'id' => '7',
			'user_id' => 'alice',
			'mxid' => '@alice:example.org',
		]);
	}

	private function matrixRoom(int $roomId = 23): MatrixRoom {
		return MatrixRoom::fromRow([
			'id' => '100',
			'room_id' => $roomId,
			'matrix_room_id' => '!room:example.org',
			'name' => null,
			'topic' => null,
			'canonical_alias' => null,
		]);
	}

	private function member(string $mxid, string $membership, ?string $accountId = null, ?string $displayName = null): MatrixMember {
		return MatrixMember::fromRow([
			'id' => '1' . strlen($mxid),
			'matrix_room_id' => '100',
			'mxid' => $mxid,
			'membership' => $membership,
			'display_name' => $displayName,
			'account_id' => $accountId,
		]);
	}

	private function participant(string $actorType, string $actorId, string $displayName = ''): Participant&MockObject {
		$attendee = Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => $actorId,
			'display_name' => $displayName,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	private static function memberEvent(string $mxid, string $membership, ?string $displayName = null): array {
		$content = ['membership' => $membership];
		if ($displayName !== null) {
			$content['displayname'] = $displayName;
		}
		return ['type' => 'm.room.member', 'state_key' => $mxid, 'sender' => $mxid, 'content' => $content];
	}

	private static function batch(array $joinedRoom, array $left = []): SyncBatch {
		return SyncBatch::fromArray([
			'next_batch' => 'next',
			'rooms' => [
				'join' => $joinedRoom === [] ? [] : ['!room:example.org' => $joinedRoom],
				'leave' => array_fill_keys($left, []),
			],
		]);
	}

	public function testNewRoom(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willThrowException(new DoesNotExistException(''));
		$this->roomMapper->expects(self::once())
			->method('insert')
			->willReturnCallback(static fn (MatrixRoom $matrixRoom): MatrixRoom => MatrixRoom::fromRow([
				'id' => '100',
				'room_id' => 0,
				'matrix_room_id' => $matrixRoom->getMatrixRoomId(),
			]));
		$this->roomService->expects(self::once())
			->method('createConversation')
			->with(Room::TYPE_GROUP, 'Bob and Carol', null, Room::OBJECT_TYPE_MATRIX, '100', '', Room::READ_WRITE, Room::LISTABLE_NONE, 0, Webinary::LOBBY_NONE, null, Webinary::SIP_DISABLED, RoomSyncService::DEFAULT_PERMISSIONS)
			->willReturn($this->room);
		$this->roomMapper->expects(self::once())
			->method('update')
			->with(self::callback(static fn (MatrixRoom $matrixRoom): bool => $matrixRoom->getRoomId() === 23))
			->willReturnArgument(0);
		$this->participantService->method('getParticipantsForRoom')->willReturn([]);
		$this->participantService->expects(self::once())
			->method('addUsers')
			->with($this->room, [
				['actorType' => Attendee::ACTOR_USERS, 'actorId' => 'alice', 'displayName' => 'Alice', 'participantType' => Participant::USER],
				['actorType' => Attendee::ACTOR_MATRIX, 'actorId' => '@bob:example.org', 'displayName' => 'Bob', 'participantType' => Participant::USER],
			]);

		$this->messageSyncService->expects(self::once())
			->method('apply')
			->with($this->room, self::anything(), [], self::anything(), self::anything(), true)
			->willReturn(0);

		$stats = $this->service->process($this->account(), self::batch([
			'state' => ['events' => [
				self::memberEvent('@alice:example.org', 'join', 'Alice'),
				self::memberEvent('@bob:example.org', 'join', 'Bob'),
				self::memberEvent('@carol:example.org', 'invite', 'Carol'),
			]],
		]), false);

		self::assertSame(['rooms' => 1, 'messages' => 0, 'failed' => 0], $stats);
		self::assertSame('7', $this->insertedMembers['@alice:example.org']->getAccountId());
		self::assertNull($this->insertedMembers['@bob:example.org']->getAccountId());
		self::assertSame('invite', $this->insertedMembers['@carol:example.org']->getMembership());
	}

	public function testExistingRoomUpdatesMembersAndName(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willReturn($this->matrixRoom());
		$this->manager->method('getRoomById')->with(23)->willReturn($this->room);
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@alice:example.org' => $this->member('@alice:example.org', 'join', '7', 'Alice'),
			'@bob:example.org' => $this->member('@bob:example.org', 'join', null, 'Bob'),
		]);
		$this->roomService->expects(self::never())->method('createConversation');
		$this->roomService->expects(self::once())->method('setName')->with($this->room, 'Robert and @carol:example.org');
		$this->roomService->expects(self::once())->method('setDescription')->with($this->room, 'About us');

		$alice = $this->participant(Attendee::ACTOR_USERS, 'alice');
		$bob = $this->participant(Attendee::ACTOR_MATRIX, '@bob:example.org', 'Bob');
		$dave = $this->participant(Attendee::ACTOR_MATRIX, '@dave:example.org', 'Dave');
		$this->participantService->method('getParticipantsForRoom')->willReturn([$alice, $bob, $dave]);
		$this->participantService->expects(self::once())
			->method('updateDisplayNameForActor')
			->with(Attendee::ACTOR_MATRIX, '@bob:example.org', 'Robert');
		$this->participantService->expects(self::once())
			->method('removeAttendee')
			->with($this->room, $dave, AAttendeeRemovedEvent::REASON_REMOVED);
		$this->participantService->expects(self::once())
			->method('addUsers')
			->with($this->room, [
				['actorType' => Attendee::ACTOR_MATRIX, 'actorId' => '@carol:example.org', 'displayName' => '@carol:example.org', 'participantType' => Participant::USER],
			]);

		$this->messageSyncService->expects(self::once())
			->method('apply')
			->with($this->room, self::anything(), self::countOf(0), self::anything(), self::callback(static fn (array $accounts): bool => array_keys($accounts) === ['@alice:example.org']), false)
			->willReturn(0);

		$stats = $this->service->process($this->account(), self::batch([
			'state' => ['events' => [
				['type' => 'm.room.topic', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['topic' => 'About us']],
			]],
			'timeline' => ['events' => [
				self::memberEvent('@bob:example.org', 'join', 'Robert'),
				self::memberEvent('@carol:example.org', 'join'),
			]],
		]), false);

		self::assertSame(['rooms' => 1, 'messages' => 0, 'failed' => 0], $stats);
	}

	public function testEncryptedRoomAndPowerLevels(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willReturn($this->matrixRoom());
		$this->manager->method('getRoomById')->with(23)->willReturn($this->room);
		$this->room->method('getDefaultPermissions')->willReturn(Attendee::PERMISSIONS_DEFAULT);
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@alice:example.org' => $this->member('@alice:example.org', 'join', '7', 'Alice'),
		]);
		$this->roomService->expects(self::once())->method('setReadOnly')->with($this->room, Room::READ_ONLY);
		$this->roomService->expects(self::once())->method('setDefaultPermissions')->with($this->room, RoomSyncService::DEFAULT_PERMISSIONS);

		$alice = $this->participant(Attendee::ACTOR_USERS, 'alice');
		$this->participantService->method('getParticipantsForRoom')->willReturn([$alice]);
		$this->participantService->expects(self::once())
			->method('updatePermissions')
			->with($this->room, $alice, Attendee::PERMISSIONS_MODIFY_SET, Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_REACT);

		$this->service->process($this->account(), self::batch([
			'state' => ['events' => [
				['type' => 'm.room.encryption', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['algorithm' => 'm.megolm.v1.aes-sha2']],
				['type' => 'm.room.power_levels', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['users' => ['@bob:example.org' => 100], 'events' => ['m.room.message' => 50]]],
			]],
		]), false);
	}

	private static function inviteBatch(): SyncBatch {
		return SyncBatch::fromArray([
			'next_batch' => 'next',
			'rooms' => ['invite' => ['!room:example.org' => ['invite_state' => ['events' => [
				['type' => 'm.room.name', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['name' => 'Team']],
				['type' => 'm.room.member', 'state_key' => '@bob:example.org', 'sender' => '@bob:example.org', 'content' => ['membership' => 'join', 'displayname' => 'Bob']],
				['type' => 'm.room.member', 'state_key' => '@alice:example.org', 'sender' => '@bob:example.org', 'content' => ['membership' => 'invite']],
			]]]]],
		]);
	}

	public function testInvite(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willThrowException(new DoesNotExistException(''));
		$this->roomMapper->expects(self::once())
			->method('insert')
			->willReturnCallback(static function (MatrixRoom $matrixRoom): MatrixRoom {
				self::assertSame('Team', $matrixRoom->getName());
				return MatrixRoom::fromRow(['id' => '100', 'room_id' => 0, 'matrix_room_id' => $matrixRoom->getMatrixRoomId(), 'name' => 'Team']);
			});
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([]);
		$this->roomService->expects(self::never())->method('createConversation');
		$this->notification->expects(self::once())->method('setObject')->with('matrix_invite', '100')->willReturnSelf();
		$this->notification->expects(self::once())
			->method('setSubject')
			->with('matrix_invite', ['inviter' => '@bob:example.org', 'inviterName' => 'Bob', 'roomName' => 'Team'])
			->willReturnSelf();
		$this->notificationManager->expects(self::once())->method('notify')->with($this->notification);

		self::assertSame(['rooms' => 0, 'messages' => 0, 'failed' => 0], $this->service->process($this->account(), self::inviteBatch(), false));
		self::assertSame('invite', $this->insertedMembers['@alice:example.org']->getMembership());
		self::assertSame('7', $this->insertedMembers['@alice:example.org']->getAccountId());
	}

	public function testRepeatedInviteIsNotNotified(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willReturn($this->matrixRoom(0));
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@alice:example.org' => $this->member('@alice:example.org', 'invite', '7'),
		]);
		$this->memberMapper->expects(self::never())->method('insert');
		$this->notificationManager->expects(self::never())->method('notify');

		$this->service->process($this->account(), self::inviteBatch(), false);
	}

	public function testJoinAfterInviteAndRoles(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willReturn($this->matrixRoom());
		$this->manager->method('getRoomById')->with(23)->willReturn($this->room);
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@alice:example.org' => $this->member('@alice:example.org', 'invite', '7', 'Alice'),
			'@bob:example.org' => $this->member('@bob:example.org', 'join', null, 'Bob'),
		]);
		$this->notification->expects(self::once())->method('setObject')->with('matrix_invite', '100')->willReturnSelf();
		$this->notificationManager->expects(self::once())->method('markProcessed')->with($this->notification);

		$alice = $this->participant(Attendee::ACTOR_USERS, 'alice', 'Alice');
		$bob = $this->participant(Attendee::ACTOR_MATRIX, '@bob:example.org', 'Bob');
		$this->participantService->method('getParticipantsForRoom')->willReturnOnConsecutiveCalls([$bob], [$alice, $bob]);
		$this->participantService->expects(self::once())
			->method('addUsers')
			->with($this->room, [
				['actorType' => Attendee::ACTOR_USERS, 'actorId' => 'alice', 'displayName' => 'Alice', 'participantType' => Participant::MODERATOR],
			]);
		$this->participantService->expects(self::exactly(2))
			->method('updateParticipantType')
			->willReturnCallback(function (Room $room, Participant $participant, int $participantType) use ($alice, $bob): void {
				// Alice was added as a mock with the default type, Bob has power level 100
				self::assertSame($participant === $alice ? Participant::MODERATOR : Participant::OWNER, $participantType);
			});
		$this->participantService->expects(self::never())->method('updatePermissions');

		$this->service->process($this->account(), self::batch([
			'state' => ['events' => [
				self::memberEvent('@alice:example.org', 'join', 'Alice'),
				['type' => 'm.room.power_levels', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['users' => ['@bob:example.org' => 100, '@alice:example.org' => 50]]],
			]],
		]), false);
	}

	public function testSpaceIsSkipped(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willThrowException(new DoesNotExistException(''));
		$this->roomMapper->expects(self::never())->method('insert');
		$this->roomService->expects(self::never())->method('createConversation');

		$stats = $this->service->process($this->account(), self::batch([
			'state' => ['events' => [
				['type' => 'm.room.create', 'state_key' => '', 'sender' => '@bob:example.org', 'content' => ['type' => 'm.space']],
				self::memberEvent('@alice:example.org', 'join', 'Alice'),
			]],
		]), false);

		self::assertSame(['rooms' => 1, 'messages' => 0, 'failed' => 0], $stats);
	}

	public function testFailingRoomIsCounted(): void {
		$this->roomMapper->method('getByMatrixRoomId')->willThrowException(new \RuntimeException('Database is gone'));

		$stats = $this->service->process($this->account(), self::batch([
			'state' => ['events' => [self::memberEvent('@alice:example.org', 'join')]],
		]), false);

		self::assertSame(['rooms' => 0, 'messages' => 0, 'failed' => 1], $stats);
	}

	public function testLeaveDeletesConversationWithoutLinkedMembers(): void {
		$matrixRoom = $this->matrixRoom();
		$this->roomMapper->method('getByMatrixRoomId')->willReturn($matrixRoom);
		$this->roomMapper->method('getById')->with('100')->willReturn($matrixRoom);
		$this->manager->method('getRoomById')->with(23)->willReturn($this->room);
		$member = $this->member('@alice:example.org', 'join', '7');
		$this->memberMapper->method('getForRoom')->willReturn(['@alice:example.org' => $member]);
		$this->memberMapper->expects(self::once())->method('update')->with($member);
		$this->memberMapper->method('hasLinkedMembers')->with('100')->willReturn(false);

		$alice = $this->participant(Attendee::ACTOR_USERS, 'alice');
		$this->participantService->method('getParticipantByActor')->with($this->room, Attendee::ACTOR_USERS, 'alice')->willReturn($alice);
		$this->participantService->expects(self::once())->method('removeAttendee')->with($this->room, $alice, AAttendeeRemovedEvent::REASON_LEFT);
		$this->memberMapper->expects(self::once())->method('deleteForRoom')->with('100');
		$this->roomMapper->expects(self::once())->method('delete')->with($matrixRoom);
		$this->eventMapMapper->expects(self::once())->method('deleteForRoom')->with('100');
		$this->roomService->expects(self::once())->method('deleteRoom')->with($this->room);

		$this->service->process($this->account(), self::batch([], ['!room:example.org']), false);

		self::assertSame('leave', $member->getMembership());
	}

	public function testRemoveAccountKeepsConversationWithOtherLinkedMembers(): void {
		$matrixRoom = $this->matrixRoom();
		$member = $this->member('@alice:example.org', 'join', '7');
		$this->memberMapper->method('getForAccount')->with('7')->willReturn([$member]);
		$this->memberMapper->expects(self::once())
			->method('update')
			->with(self::callback(static fn (MatrixMember $member): bool => $member->getAccountId() === null));
		$this->roomMapper->method('getById')->with('100')->willReturn($matrixRoom);
		$this->manager->method('getRoomById')->with(23)->willReturn($this->room);
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$this->participantService->expects(self::never())->method('removeAttendee');
		$this->memberMapper->method('hasLinkedMembers')->with('100')->willReturn(true);
		$this->roomService->expects(self::never())->method('deleteRoom');

		$this->service->removeAccount($this->account());
	}
}
