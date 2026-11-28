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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UploadsWithoutStorageServiceTest extends TestCase {
	protected Config&MockObject $talkConfig;
	protected ConversationFolderService&MockObject $conversationFolderService;
	protected RoomService&MockObject $roomService;
	protected IRootFolder&MockObject $rootFolder;
	protected IShareManager&MockObject $shareManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected ILimiter&MockObject $limiter;
	protected IRequest&MockObject $request;
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
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->limiter = $this->createMock(ILimiter::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn('password');

		$this->service = new UploadsWithoutStorageService(
			$this->talkConfig,
			$this->conversationFolderService,
			$this->roomService,
			$this->rootFolder,
			$this->shareManager,
			$this->createMock(IEventDispatcher::class),
			$secureRandom,
			$this->timeFactory,
			$this->limiter,
			$this->request,
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
		$room->method('getGuestsFolderOwner')->willReturn($guestsFolderId === 0 ? '' : 'alice');
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
	private function mockGuestsFolder(): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(42);

		$userFolder = $this->createMock(IUserFolder::class);
		$userFolder->method('getFirstNodeById')->with(42)->willReturn($folder);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		return $folder;
	}

	private function createDraftFolder(?Folder $uploadFolder = null): Folder&MockObject {
		$draftFolder = $this->createMock(Folder::class);
		$draftFolder->method('getId')->willReturn(23);
		$draftFolder->method('getFirstNodeById')->willReturnCallback(fn (int $id) => $id === 24 ? $uploadFolder : null);
		return $draftFolder;
	}

	private function createUploadFolder(array $fileNames = [], int $parentId = 23): Folder&MockObject {
		$parent = $this->createMock(Folder::class);
		$parent->method('getId')->willReturn($parentId);
		$uploadFolder = $this->createMock(Folder::class);
		$uploadFolder->method('getId')->willReturn(24);
		$uploadFolder->method('getParent')->willReturn($parent);
		$uploadFolder->method('getDirectoryListing')->willReturn(array_map(function (string $name) {
			$file = $this->createMock(File::class);
			$file->method('getName')->willReturn($name);
			return $file;
		}, $fileNames));
		return $uploadFolder;
	}

	private function createLink(string $label, int $nodeId = 24): IShare&MockObject {
		$share = $this->createMock(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_LINK);
		$share->method('getLabel')->willReturn($label);
		$share->method('getToken')->willReturn('linktoken');
		$share->method('getNodeId')->willReturn($nodeId);
		return $share;
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

		$this->roomService->expects($this->once())->method('setGuestsFolder')->with($room, 'alice', 42);

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
		$this->roomService->expects($this->never())->method('setGuestsFolder');

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
		$this->roomService->expects($this->never())->method('setGuestsFolder');

		$this->expectException(UploadsWithoutStorageException::class);
		$this->service->allowUploads($this->createParticipant(Attendee::ACTOR_USERS, 'alice'), $this->createRoom());
	}

	public function testGetGuestsFolderWhenOff(): void {
		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$this->expectException(NotFoundException::class);
		$this->service->getGuestsFolder($this->createRoom());
	}

	public static function dataGetGuestsFolderLocation(): array {
		return [
			'owner' => ['alice', 'Talk/Lorem-abcdef/Guests'],
			'other moderator' => ['bob', null],
		];
	}

	#[DataProvider('dataGetGuestsFolderLocation')]
	public function testGetGuestsFolderLocation(string $moderatorId, ?string $path): void {
		$guestsFolder = $this->mockGuestsFolder();
		$this->userManager->method('getDisplayName')->with('alice')->willReturn('Alice');
		$this->conversationFolderService->method('getRelativePath')->with('alice', $guestsFolder)->willReturn('Talk/Lorem-abcdef/Guests');

		$this->assertSame(
			['owner' => 'Alice', 'path' => $path],
			$this->service->getGuestsFolderLocation($this->createRoom(42), $this->createParticipant(Attendee::ACTOR_USERS, $moderatorId)),
		);
	}

	public function testGetUploadFolders(): void {
		$room = $this->createRoom(42);
		$guestsFolder = $this->mockGuestsFolder();
		$draftFolder = $this->createMock(Folder::class);
		$this->mockAvailable();
		$this->conversationFolderService->expects($this->once())->method('shareGuestsFolder')->with($guestsFolder, 'alice', $room);
		$this->conversationFolderService->method('getOrCreateDraftFolder')->with($guestsFolder)->willReturn($draftFolder);

		$this->assertSame(
			['guestsFolder' => $guestsFolder, 'draftFolder' => $draftFolder],
			$this->service->getUploadFolders($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest'), $room),
		);
	}

	public function testGetUploadFoldersForFederatedUser(): void {
		$this->mockGuestsFolder();
		$this->mockAvailable();

		$this->expectException(NotPermittedException::class);
		$this->service->getUploadFolders($this->createParticipant(Attendee::ACTOR_FEDERATED_USERS, 'bob@example.org'), $this->createRoom(42));
	}

	public function testGetUploadFoldersWhenModeratorMayNotShare(): void {
		$this->mockGuestsFolder();
		$this->mockAvailable();
		$this->conversationFolderService->method('shareGuestsFolder')->willThrowException(new GenericShareException('Sharing is not allowed for you.'));

		$this->expectException(NotPermittedException::class);
		$this->service->getUploadFolders($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest'), $this->createRoom(42));
	}

	public function testCreateUploadLinkForGuest(): void {
		$this->request->method('getRemoteAddress')->willReturn('192.0.2.1');
		$this->limiter->expects($this->once())
			->method('registerAnonRequest')
			->with('talk-uploads-without-storage', 30, 3600, '192.0.2.1');

		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-11-19 12:00:00'));
		$draftFolder = $this->createMock(Folder::class);
		$uploadFolder = $this->createMock(Folder::class);
		$draftFolder->expects($this->once())->method('newFolder')->with('password')->willReturn($uploadFolder);

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())->method('setNode')->with($uploadFolder)->willReturnSelf();
		$share->expects($this->once())->method('setShareType')->with(IShare::TYPE_LINK)->willReturnSelf();
		$share->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_CREATE)->willReturnSelf();
		$share->expects($this->once())->method('setSharedBy')->with('alice')->willReturnSelf();
		$share->expects($this->once())->method('setShareOwner')->with('alice')->willReturnSelf();
		$share->expects($this->once())->method('setLabel')->with('Talk upload abcdef 7')->willReturnSelf();
		$share->expects($this->once())->method('setExpirationDate')->with(new \DateTime('2026-11-20 12:00:00'))->willReturnSelf();
		$share->expects($this->once())->method('setPassword')->with('password')->willReturnSelf();
		$share->method('getToken')->willReturn('linktoken');
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->expects($this->once())->method('createShare')->with($share)->willReturn($share);

		$this->assertSame(
			['uploadToken' => 'linktoken', 'uploadPassword' => 'password'],
			$this->service->createUploadLink($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest'), $this->createRoom(42), $draftFolder),
		);
	}

	public function testCreateUploadLinkCountsUsersPerAccount(): void {
		$user = $this->createUser('bob', 0);
		$this->userManager->method('get')->with('bob')->willReturn($user);
		$this->limiter->expects($this->once())
			->method('registerUserRequest')
			->with('talk-uploads-without-storage', 30, 3600, $user);
		$this->limiter->expects($this->never())->method('registerAnonRequest');

		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());
		$share = $this->createMock(IShare::class);
		foreach (['setNode', 'setShareType', 'setPermissions', 'setSharedBy', 'setShareOwner', 'setLabel', 'setExpirationDate', 'setPassword'] as $setter) {
			$share->method($setter)->willReturnSelf();
		}
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->method('createShare')->willReturn($share);

		$draftFolder = $this->createMock(Folder::class);
		$draftFolder->method('newFolder')->willReturn($this->createMock(Folder::class));
		$this->service->createUploadLink($this->createParticipant(Attendee::ACTOR_USERS, 'bob'), $this->createRoom(42), $draftFolder);
	}

	public function testGetUploadFolder(): void {
		$this->shareManager->method('getShareByToken')->with('linktoken')->willReturn($this->createLink('Talk upload abcdef 7'));
		$uploadFolder = $this->createUploadFolder();

		$this->assertSame($uploadFolder, $this->service->getUploadFolder($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest', 7), $this->createRoom(42), $this->createDraftFolder($uploadFolder), 'linktoken'));
	}

	public static function dataGetUploadFolderRefused(): array {
		return [
			'link of another guest' => ['Talk upload abcdef 8', 24, 23],
			'link of another conversation' => ['Talk upload ghijkl 7', 24, 23],
			'other link of the moderator' => ['Holiday photos', 24, 23],
			'link on a folder outside the Draft folder' => ['Talk upload abcdef 7', 99, 23],
			'link on a folder deeper in the Draft folder' => ['Talk upload abcdef 7', 24, 25],
		];
	}

	#[DataProvider('dataGetUploadFolderRefused')]
	public function testGetUploadFolderRefused(string $label, int $nodeId, int $parentId): void {
		$this->shareManager->method('getShareByToken')->willReturn($this->createLink($label, $nodeId));

		$this->expectException(NotPermittedException::class);
		$this->service->getUploadFolder($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest', 7), $this->createRoom(42), $this->createDraftFolder($this->createUploadFolder([], $parentId)), 'linktoken');
	}

	public function testGetUploadFolderWithUnknownLink(): void {
		$this->shareManager->method('getShareByToken')->willThrowException(new ShareNotFound());

		$this->expectException(NotPermittedException::class);
		$this->service->getUploadFolder($this->createParticipant(Attendee::ACTOR_GUESTS, 'guest'), $this->createRoom(42), $this->createDraftFolder(), 'linktoken');
	}

	public function testFinishUploadKeepsFolderWhileFilesWait(): void {
		$uploadFolder = $this->createUploadFolder(['b.jpg']);
		$uploadFolder->expects($this->never())->method('delete');
		$this->shareManager->expects($this->never())->method('deleteShare');

		$this->service->finishUpload($this->createRoom(42), $uploadFolder);
	}

	public function testFinishUploadDeletesLinkAndFolderWhenDone(): void {
		$uploadFolder = $this->createUploadFolder();
		$uploadFolder->expects($this->once())->method('delete');
		$link = $this->createLink('Talk upload abcdef 7');
		$this->shareManager->method('getSharesBy')->with('alice', IShare::TYPE_LINK, $uploadFolder, false, -1)->willReturn([$link]);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($link);

		$this->service->finishUpload($this->createRoom(42), $uploadFolder);
	}

	public function testRevokeUploadLinksOfTheConversation(): void {
		$guestLink = $this->createLink('Talk upload abcdef 7');
		$otherGuestLink = $this->createLink('Talk upload abcdef 8');
		$this->shareManager->method('getSharesBy')
			->with('alice', IShare::TYPE_LINK, null, false, -1)
			->willReturn([$guestLink, $otherGuestLink, $this->createLink('Talk upload ghijkl 7'), $this->createLink('Holiday photos')]);

		$deleted = [];
		$this->shareManager->method('deleteShare')->willReturnCallback(function (IShare $share) use (&$deleted): void {
			$deleted[] = $share;
		});

		$this->service->revokeUploadLinks($this->createRoom(42), 'alice');
		$this->assertSame([$guestLink, $otherGuestLink], $deleted);
	}

	public function testRevokeUploadLinksOfOneAttendee(): void {
		$guestLink = $this->createLink('Talk upload abcdef 7');
		$this->shareManager->method('getSharesBy')->willReturn([$guestLink, $this->createLink('Talk upload abcdef 8')]);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($guestLink);

		$this->service->revokeUploadLinks($this->createRoom(42), 'alice', $this->createParticipant(Attendee::ACTOR_GUESTS, 'guest', 7)->getAttendee());
	}
}
