<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomProperty\UploadsWithoutStorageException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ConversationFolderService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UploadsWithoutStorageServiceTest extends TestCase {
	protected Config&MockObject $talkConfig;
	protected ConversationFolderService&MockObject $conversationFolderService;
	protected RoomService&MockObject $roomService;
	protected IRootFolder&MockObject $rootFolder;
	protected IShareManager&MockObject $shareManager;
	protected IUserManager&MockObject $userManager;
	protected UploadsWithoutStorageService $service;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(Config::class);
		$this->conversationFolderService = $this->createMock(ConversationFolderService::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->service = new UploadsWithoutStorageService(
			$this->talkConfig,
			$this->conversationFolderService,
			$this->roomService,
			$this->rootFolder,
			$this->shareManager,
			$this->userManager,
		);
	}

	private function createParticipant(string $actorType, string $actorId, int $attendeeId = 7): Participant&MockObject {
		$attendee = new Attendee();
		$attendee->setId($attendeeId);
		$attendee->setActorType($actorType);
		$attendee->setActorId($actorId);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	private function createUser(string $uid, int|float $quotaBytes = -3): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn(ucfirst($uid));
		$user->method('getQuotaBytes')->willReturn($quotaBytes);
		return $user;
	}

	private function createRoom(int $guestsFolderId = 0, int $type = Room::TYPE_PUBLIC, string $objectType = ''): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn('abcdef');
		$room->method('getType')->willReturn($type);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('getGuestsFolderId')->willReturn($guestsFolderId);
		return $room;
	}

	private function mockAvailable(bool $available = true): void {
		$this->talkConfig->method('isConversationSubfoldersEnabled')->willReturn($available);
		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);
	}

	/**
	 * The Guests folder with id 42 as seen by its owner
	 */
	private function mockGuestsFolder(string $ownerId = 'alice'): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getOwner')->willReturn($this->createUser($ownerId));
		$folder->method('getId')->willReturn(42);

		$this->rootFolder->method('getFirstNodeById')->with(42)->willReturn($folder);
		$userFolder = $this->createMock(IUserFolder::class);
		$userFolder->method('getFirstNodeById')->with(42)->willReturn($folder);
		$this->rootFolder->method('getUserFolder')->with($ownerId)->willReturn($userFolder);

		return $folder;
	}

	public static function dataHasOwnStorage(): array {
		return [
			'unlimited quota' => [Attendee::ACTOR_USERS, -3, true],
			'quota of 0' => [Attendee::ACTOR_USERS, 0, false],
			'quota of 0 as float' => [Attendee::ACTOR_USERS, 0.0, false],
			'guest' => [Attendee::ACTOR_GUESTS, -3, false],
		];
	}

	#[DataProvider('dataHasOwnStorage')]
	public function testHasOwnStorage(string $actorType, int|float $quotaBytes, bool $expected): void {
		$this->userManager->method('get')->with('alice')->willReturn($this->createUser('alice', $quotaBytes));

		$this->assertSame($expected, $this->service->hasOwnStorage($this->createParticipant($actorType, 'alice')));
	}

	public function testAllowUploads(): void {
		$room = $this->createRoom();
		$this->mockAvailable();
		$this->userManager->method('get')->willReturn($this->createUser('alice'));
		$guestsFolder = $this->createMock(Folder::class);
		$guestsFolder->method('getId')->willReturn(42);
		$this->conversationFolderService->method('getOrCreateGuestsFolder')->with('alice', $room)->willReturn($guestsFolder);

		$this->roomService->expects($this->once())->method('setGuestsFolderId')->with($room, 42);

		$this->service->allowUploads($this->createParticipant(Attendee::ACTOR_USERS, 'alice'), $room);
	}

	public static function dataAllowUploadsRefused(): array {
		return [
			'one-to-one' => [Room::TYPE_ONE_TO_ONE, '', true, -3, UploadsWithoutStorageException::REASON_TYPE],
			'breakout room' => [Room::TYPE_GROUP, BreakoutRoom::PARENT_OBJECT_TYPE, true, -3, UploadsWithoutStorageException::REASON_BREAKOUT_ROOM],
			'Matrix room' => [Room::TYPE_GROUP, Room::OBJECT_TYPE_MATRIX, true, -3, UploadsWithoutStorageException::REASON_MATRIX],
			'unavailable' => [Room::TYPE_PUBLIC, '', false, -3, UploadsWithoutStorageException::REASON_UNAVAILABLE],
			'moderator without storage' => [Room::TYPE_PUBLIC, '', true, 0, UploadsWithoutStorageException::REASON_STORAGE],
		];
	}

	#[DataProvider('dataAllowUploadsRefused')]
	public function testAllowUploadsRefused(int $type, string $objectType, bool $available, int $quotaBytes, string $reason): void {
		$this->mockAvailable($available);
		$this->userManager->method('get')->willReturn($this->createUser('alice', $quotaBytes));
		$this->conversationFolderService->expects($this->never())->method('getOrCreateGuestsFolder');
		$this->roomService->expects($this->never())->method('setGuestsFolderId');

		try {
			$this->service->allowUploads($this->createParticipant(Attendee::ACTOR_USERS, 'alice'), $this->createRoom(0, $type, $objectType));
			$this->fail('Expected UploadsWithoutStorageException');
		} catch (UploadsWithoutStorageException $e) {
			$this->assertSame($reason, $e->getReason());
		}
	}

	public function testAllowUploadsWithFullQuota(): void {
		$this->mockAvailable();
		$this->userManager->method('get')->willReturn($this->createUser('alice', 1024));
		$this->conversationFolderService->method('getOrCreateGuestsFolder')->willThrowException(new NotEnoughSpaceException());
		$this->roomService->expects($this->never())->method('setGuestsFolderId');

		$this->expectException(UploadsWithoutStorageException::class);
		$this->service->allowUploads($this->createParticipant(Attendee::ACTOR_USERS, 'alice'), $this->createRoom());
	}

	public function testGetGuestsFolderWhenOff(): void {
		$this->rootFolder->expects($this->never())->method('getFirstNodeById');

		$this->expectException(NotFoundException::class);
		$this->service->getGuestsFolder($this->createRoom());
	}

	public function testGetGuestsFolderLocation(): void {
		$guestsFolder = $this->mockGuestsFolder();
		$this->conversationFolderService->method('getRelativePath')->with('alice', $guestsFolder)->willReturn('Talk/Lorem-abcdef/Guests');

		$this->assertSame(['owner' => 'Alice', 'path' => 'Talk/Lorem-abcdef/Guests'], $this->service->getGuestsFolderLocation($this->createRoom(42)));
	}
}
