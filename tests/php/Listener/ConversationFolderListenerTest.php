<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Listener\ConversationFolderListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Room;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConversationFolderListenerTest extends TestCase {
	private TalkConfig&MockObject $talkConfig;
	private Manager&MockObject $manager;
	private AttendeeMapper&MockObject $attendeeMapper;
	private IShareManager&MockObject $shareManager;
	private RoomShareProvider&MockObject $roomShareProvider;
	private IRootFolder&MockObject $rootFolder;
	private LoggerInterface&MockObject $logger;
	private ConversationFolderListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(TalkConfig::class);
		$this->manager = $this->createMock(Manager::class);
		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new ConversationFolderListener(
			$this->talkConfig,
			$this->manager,
			$this->attendeeMapper,
			$this->shareManager,
			$this->roomShareProvider,
			$this->rootFolder,
			$this->logger,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Build a Folder mock with a fixed path and optional owner uid. */
	private function folderWithPath(string $path, ?string $ownerUid = null): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getPath')->willReturn($path);
		if ($ownerUid !== null) {
			$owner = $this->createMock(IUser::class);
			$owner->method('getUID')->willReturn($ownerUid);
			$folder->method('getOwner')->willReturn($owner);
		}
		return $folder;
	}

	/** Build an IShare mock with all fluent setters returning itself. */
	private function shareMock(): IShare&MockObject {
		$share = $this->createMock(IShare::class);
		$share->method('setNode')->willReturnSelf();
		$share->method('setShareType')->willReturnSelf();
		$share->method('setSharedBy')->willReturnSelf();
		$share->method('setShareOwner')->willReturnSelf();
		$share->method('setSharedWith')->willReturnSelf();
		$share->method('setPermissions')->willReturnSelf();
		$share->method('setMailSend')->willReturnSelf();
		return $share;
	}

	/**
	 * Configure mocks for the happy-path case:
	 *   /uid/files/Talk/<roomName>-<token>/<prefix>-<uid>/
	 *
	 * @return array{Folder&MockObject}
	 */
	private function setUpValidSubfolder(
		string $uid = 'user1',
		string $token = 'TOKEN',
		string $roomName = 'Group room',
		int $roomType = Room::TYPE_GROUP,
	): array {
		$convFolder = $roomName . '-' . $token;
		$subfolder = 'dis-' . $uid;
		$path = '/' . $uid . '/files/Talk/' . $convFolder . '/' . $subfolder;

		$folder = $this->folderWithPath($path, $uid);

		$this->talkConfig->method('getAttachmentFolder')->with($uid)->willReturn('/Talk');
		$this->talkConfig->method('buildConversationFolderName')->with($roomName, $token)->willReturn($convFolder);

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn($roomType);
		$room->method('getName')->willReturn($roomName);
		$this->manager->method('getRoomForUserByToken')->with($token, $uid)->willReturn($room);

		return [$folder];
	}

	private function nodeCreatedEvent(Folder|File $node): NodeCreatedEvent&MockObject {
		$event = $this->createMock(NodeCreatedEvent::class);
		$event->method('getNode')->willReturn($node);
		return $event;
	}

	// -------------------------------------------------------------------------
	// processCreatedFolder — happy path
	// -------------------------------------------------------------------------

	public function testShareCreatedForValidGroupRoomSubfolder(): void {
		[$folder] = $this->setUpValidSubfolder();
		$share = $this->shareMock();
		$this->shareManager->method('getSharesBy')->willReturn([]);
		$this->shareManager->method('newShare')->willReturn($share);

		$this->shareManager->expects($this->once())->method('createShare')->with($share);

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testShareCreatedForValidPublicRoomSubfolder(): void {
		[$folder] = $this->setUpValidSubfolder(roomType: Room::TYPE_PUBLIC);
		$share = $this->shareMock();
		$this->shareManager->method('getSharesBy')->willReturn([]);
		$this->shareManager->method('newShare')->willReturn($share);

		$this->shareManager->expects($this->once())->method('createShare')->with($share);

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testSubfolderNameWithoutPrefixIsAccepted(): void {
		// subfolder name is just the uid (no display-name prefix)
		$uid = 'user1';
		$token = 'TOKEN';
		$roomName = 'Group room';
		$convFolder = $roomName . '-' . $token;
		$path = '/' . $uid . '/files/Talk/' . $convFolder . '/' . $uid;

		$folder = $this->folderWithPath($path, $uid);
		$this->talkConfig->method('getAttachmentFolder')->with($uid)->willReturn('/Talk');
		$this->talkConfig->method('buildConversationFolderName')->with($roomName, $token)->willReturn($convFolder);

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getName')->willReturn($roomName);
		$this->manager->method('getRoomForUserByToken')->with($token, $uid)->willReturn($room);

		$share = $this->shareMock();
		$this->shareManager->method('getSharesBy')->willReturn([]);
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->expects($this->once())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	// -------------------------------------------------------------------------
	// processCreatedFolder — share creation failure must propagate
	// -------------------------------------------------------------------------

	public function testShareCreationExceptionPropagates(): void {
		[$folder] = $this->setUpValidSubfolder();
		$share = $this->shareMock();
		$this->shareManager->method('getSharesBy')->willReturn([]);
		$this->shareManager->method('newShare')->willReturn($share);

		$this->shareManager->method('createShare')
			->willThrowException(new \RuntimeException('DB write failed'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('DB write failed');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	// -------------------------------------------------------------------------
	// processCreatedFolder — filter / guard cases (no share, no exception)
	// -------------------------------------------------------------------------

	public function testFolderOutsideAttachmentFolderIsIgnored(): void {
		$folder = $this->folderWithPath('/user1/files/Documents/somefolder/sub', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testConvFolderLevelIsIgnored(): void {
		// Only one segment after the prefix — this is the conversation folder
		// itself, not a user subfolder. No share should be created.
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testFolderTooDeepIsIgnored(): void {
		// Three segments after the prefix — deeper than expected.
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN/dis-user1/deep', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testSubfolderNameNotMatchingUidIsIgnored(): void {
		// Subfolder does not end with the owner's uid.
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN/someone-else', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testConvFolderWithoutDashIsIgnored(): void {
		// Cannot extract a token — conv folder has no dash.
		$folder = $this->folderWithPath('/user1/files/Talk/NODASH/dis-user1', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testRoomNotFoundIsIgnored(): void {
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN/dis-user1', 'user1');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->manager->method('getRoomForUserByToken')
			->willThrowException(new RoomNotFoundException());
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testOneToOneRoomIsIgnored(): void {
		[$folder] = $this->setUpValidSubfolder(roomType: Room::TYPE_ONE_TO_ONE);
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testConvFolderNameMismatchIsIgnored(): void {
		// Room was renamed — folder name no longer matches what Talk would generate.
		$uid = 'user1';
		$token = 'TOKEN';
		$folder = $this->folderWithPath('/' . $uid . '/files/Talk/Old name-' . $token . '/dis-' . $uid, $uid);
		$this->talkConfig->method('getAttachmentFolder')->with($uid)->willReturn('/Talk');
		$this->talkConfig->method('buildConversationFolderName')
			->with('New name', $token)->willReturn('New name-' . $token);

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getName')->willReturn('New name');
		$this->manager->method('getRoomForUserByToken')->willReturn($room);

		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testShareAlreadyExistsIsIdempotent(): void {
		[$folder] = $this->setUpValidSubfolder();

		$this->shareManager->method('getSharesBy')
			->willReturn([$this->createMock(IShare::class)]);
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testNullOwnerIsIgnored(): void {
		// getOwner() returns null — no share should be created.
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN/dis-user1');
		// ownerUid not passed → getOwner() returns null by default
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	public function testOwnerUidMismatchIsIgnored(): void {
		// getOwner() returns a different uid than what the path implies.
		$folder = $this->folderWithPath('/user1/files/Talk/Group room-TOKEN/dis-user1', 'other-user');
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($this->nodeCreatedEvent($folder));
	}

	// -------------------------------------------------------------------------
	// Non-folder NodeCreatedEvent is ignored
	// -------------------------------------------------------------------------

	public function testNonFolderNodeIsIgnored(): void {
		$file = $this->createMock(File::class);
		$event = $this->createMock(NodeCreatedEvent::class);
		$event->method('getNode')->willReturn($file);

		$this->shareManager->expects($this->never())->method('createShare');

		$this->listener->handle($event);
	}

	// -------------------------------------------------------------------------
	// handleRoomModified — exceptions are logged, not propagated
	// -------------------------------------------------------------------------

	public function testRoomModifiedExceptionIsLoggedNotPropagated(): void {
		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getToken')->willReturn('TOKEN');

		$event = $this->createMock(RoomModifiedEvent::class);
		$event->method('getProperty')->willReturn(ARoomModifiedEvent::PROPERTY_NAME);
		$event->method('getRoom')->willReturn($room);
		$event->method('getOldValue')->willReturn('Old name');
		$event->method('getNewValue')->willReturn('New name');

		$this->talkConfig->method('buildConversationFolderName')
			->willReturnMap([
				['Old name', 'TOKEN', 'Old name-TOKEN'],
				['New name', 'TOKEN', 'New name-TOKEN'],
			]);

		$this->attendeeMapper->method('getActorsByType')
			->willThrowException(new \RuntimeException('DB error'));

		$this->logger->expects($this->once())->method('error');

		// Must not throw.
		$this->listener->handle($event);
	}
}
