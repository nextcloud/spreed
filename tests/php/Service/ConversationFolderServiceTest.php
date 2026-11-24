<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Room;
use OCA\Talk\Service\ConversationFolderService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Teams\ITeamFolderProvider;
use OCP\Teams\ITeamManager;
use OCP\Teams\TeamFolder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConversationFolderServiceTest extends TestCase {
	protected TalkConfig&MockObject $talkConfig;
	protected IRootFolder&MockObject $rootFolder;
	protected IShareManager&MockObject $shareManager;
	protected IFilenameValidator&MockObject $filenameValidator;
	protected LoggerInterface&MockObject $logger;
	protected ITeamManager&MockObject $teamManager;
	protected ConversationFolderService $service;

	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(TalkConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->filenameValidator = $this->createMock(IFilenameValidator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->teamManager = $this->createMock(ITeamManager::class);

		$this->service = new ConversationFolderService(
			$this->talkConfig,
			$this->rootFolder,
			$this->shareManager,
			$this->filenameValidator,
			$this->logger,
			$this->teamManager,
			$this->createMock(IFilesMetadataManager::class),
		);
	}

	private function makeRoom(string $token = 'abc123'): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn($token);
		return $room;
	}

	/**
	 * Create a user-folder mock with getFreeSpace() pre-configured.
	 * Defaults to SPACE_UNLIMITED so tests that don't care about quota
	 * don't trip the quota check.
	 */
	private function makeUserFolderMock(int|float $freeSpace = FileInfo::SPACE_UNLIMITED): IUserFolder&MockObject {
		$userFolder = $this->createMock(IUserFolder::class);
		$userFolder->method('getFreeSpace')->willReturn($freeSpace);
		return $userFolder;
	}

	/** Set up a share mock that accepts all fluent setters and returns itself. */
	private function makeShareMock(): IShare&MockObject {
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

	// -------------------------------------------------------------------------
	// getOrCreateSubfolder — quota check
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderThrowsWhenQuotaExhausted(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(0);
		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);

		$this->expectException(NotEnoughSpaceException::class);
		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	public function testGetOrCreateSubfolderProceedsWithUnlimitedQuota(): void {
		$room = $this->makeRoom('tok0a');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(FileInfo::SPACE_UNLIMITED);
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0a');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		// No exception expected
		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderProceedsWithNotComputedQuota(): void {
		$room = $this->makeRoom('tok0b');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(FileInfo::SPACE_NOT_COMPUTED);
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0b');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderProceedsWhenSpaceAvailable(): void {
		$room = $this->makeRoom('tok0c');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(1024 * 1024); // 1 MB free
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0c');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	// -------------------------------------------------------------------------
	// getOrCreateSubfolder — happy paths
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderAllFoldersExist(): void {
		$room = $this->makeRoom('tok1');
		$userId = 'alice';

		$subfolder = $this->createStub(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$attachmentNode = $this->createMock(Folder::class);
		$userFolder = $this->makeUserFolderMock();

		$this->talkConfig->method('getAttachmentFolder')->with($userId)->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-tok1');
		$this->talkConfig->method('getConversationSubfolderName')->with($userId)->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($attachmentNode);
		$attachmentNode->method('get')->with('My Room-tok1')->willReturn($convFolder);
		$convFolder->method('get')->with('Alice-alice')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		$this->shareManager->expects($this->once())->method('createShare');

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesAttachmentFolder(): void {
		$room = $this->makeRoom('tok2');
		$userId = 'bob';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok2');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Bob-bob');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);

		// Attachment folder doesn't exist yet
		$userFolder->method('get')->with('Talk')->willThrowException(new NotFoundException());
		$userFolder->method('newFolder')->with('Talk')->willReturn($attachmentNode);

		$attachmentNode->method('get')->with('Room-tok2')->willReturn($convFolder);
		$convFolder->method('get')->with('Bob-bob')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesConvFolder(): void {
		$room = $this->makeRoom('tok3');
		$userId = 'carol';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok3');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Carol-carol');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($attachmentNode);

		// Conv folder doesn't exist yet
		$attachmentNode->method('get')->with('Room-tok3')->willThrowException(new NotFoundException());
		$attachmentNode->method('newFolder')->with('Room-tok3')->willReturn($convFolder);

		$convFolder->method('get')->with('Carol-carol')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesUserSubfolder(): void {
		$room = $this->makeRoom('tok4');
		$userId = 'dave';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok4');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Dave-dave');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($attachmentNode);
		$attachmentNode->method('get')->with('Room-tok4')->willReturn($convFolder);

		// User subfolder doesn't exist yet
		$convFolder->method('get')->with('Dave-dave')->willThrowException(new NotFoundException());
		$convFolder->method('newFolder')->with('Dave-dave')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	// -------------------------------------------------------------------------
	// getOrCreateSubfolder — error: path component is a file, not a folder
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderThrowsWhenAttachmentFolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$fileNode = $this->createStub(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	public function testGetOrCreateSubfolderThrowsWhenConvFolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$fileNode = $this->createStub(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	public function testGetOrCreateSubfolderThrowsWhenUserSubfolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$fileNode = $this->createStub(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	// -------------------------------------------------------------------------
	// ensureSubfolderShared — duplicate share is silently ignored
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderSilentlyIgnoresDuplicateShare(): void {
		$room = $this->makeRoom('tok5');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok5');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		// createShare throws "already shared" — must be swallowed, not propagated
		$this->shareManager->method('createShare')
			->willThrowException(new GenericShareException('Already shared', 'Already shared', 403));

		// No exception expected
		$result = $this->service->getOrCreateSubfolder($userId, $room, false);
		$this->assertSame($subfolder, $result);
	}

	// -------------------------------------------------------------------------
	// ensureSubfolderShared — non-duplicate exceptions are re-thrown
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderRethrowsUnexpectedShareException(): void {
		$room = $this->makeRoom('tok6');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok6');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		$this->shareManager->method('createShare')
			->willThrowException(new \RuntimeException('Unexpected DB error'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unexpected DB error');

		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	public function testGetOrCreateSubfolderRethrowsNonDuplicateGenericShareException(): void {
		$room = $this->makeRoom('tok6b');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok6b');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		// A GenericShareException whose message is NOT 'Already shared' must be rethrown.
		$this->shareManager->method('createShare')
			->willThrowException(new GenericShareException('Room not found', 'Conversation not found', 404));

		$this->expectException(GenericShareException::class);
		$this->expectExceptionMessage('Room not found');

		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	// -------------------------------------------------------------------------
	// ensureSubfolderShared — share properties
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderSetsCorrectShareProperties(): void {
		$room = $this->makeRoom('tok7');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createStub(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok7');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);

		$newShare = $this->createMock(IShare::class);
		$this->shareManager->method('newShare')->willReturn($newShare);

		$newShare->expects($this->once())->method('setNode')->with($subfolder)->willReturnSelf();
		$newShare->expects($this->once())->method('setShareType')->with(IShare::TYPE_ROOM)->willReturnSelf();
		$newShare->expects($this->once())->method('setSharedBy')->with($userId)->willReturnSelf();
		$newShare->expects($this->once())->method('setShareOwner')->with($userId)->willReturnSelf();
		$newShare->expects($this->once())->method('setSharedWith')->with('tok7')->willReturnSelf();
		$newShare->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_READ)->willReturnSelf();
		$newShare->expects($this->once())->method('setMailSend')->with(false)->willReturnSelf();

		$this->service->getOrCreateSubfolder($userId, $room, false);
	}

	// -------------------------------------------------------------------------
	// getFileNode
	// -------------------------------------------------------------------------

	public function testGetFileNodeReturnsNodeFromUserFolder(): void {
		$userId = 'alice';
		$filePath = 'Talk/Room-tok/Alice-alice/test.txt';

		$userFolder = $this->createMock(IUserFolder::class);
		$node = $this->createStub(Node::class);

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$userFolder->method('get')->with($filePath)->willReturn($node);

		$result = $this->service->getFileNode($userId, $filePath);
		$this->assertSame($node, $result);
	}

	public function testGetFileNodeThrowsWhenPathNotFound(): void {
		$userId = 'alice';
		$filePath = 'Talk/Room-tok/Alice-alice/missing.txt';

		$userFolder = $this->createMock(IUserFolder::class);

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$userFolder->method('get')->with($filePath)->willThrowException(new NotFoundException('missing.txt'));

		$this->expectException(NotFoundException::class);
		$this->service->getFileNode($userId, $filePath);
	}

	// -------------------------------------------------------------------------
	// getRelativePath
	// -------------------------------------------------------------------------

	public function testGetRelativePathStripsLeadingSlash(): void {
		$userId = 'alice';

		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getPath')->willReturn('/alice/files/Talk/Room-tok/Alice-alice');

		$userFolder = $this->createMock(IUserFolder::class);
		$userFolder->method('getRelativePath')
			->with('/alice/files/Talk/Room-tok/Alice-alice')
			->willReturn('/Talk/Room-tok/Alice-alice');

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);

		$result = $this->service->getRelativePath($userId, $subfolder);
		$this->assertSame('Talk/Room-tok/Alice-alice', $result);
	}

	// -------------------------------------------------------------------------
	// getOrCreateDraftFolder
	// -------------------------------------------------------------------------

	public function testGetOrCreateDraftFolderReturnsExistingDraft(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$draftFolder = $this->createStub(Folder::class);

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($draftFolder);

		$result = $this->service->getOrCreateDraftFolder($subfolder);
		$this->assertSame($draftFolder, $result);
	}

	public function testGetOrCreateDraftFolderCreatesWhenMissing(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$draftFolder = $this->createStub(Folder::class);

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willThrowException(new NotFoundException('Draft'));
		$convFolder->expects($this->once())->method('newFolder')->with('Draft')->willReturn($draftFolder);

		$result = $this->service->getOrCreateDraftFolder($subfolder);
		$this->assertSame($draftFolder, $result);
	}

	public function testGetOrCreateDraftFolderThrowsWhenDraftIsFile(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$fileNode = $this->createStub(Node::class); // not a Folder

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateDraftFolder($subfolder);
	}

	public function testGetOrCreateDeletedFolderCreatesWhenMissing(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$deletedFolder = $this->createStub(Folder::class);

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Deleted')->willThrowException(new NotFoundException('Deleted'));
		$convFolder->expects($this->once())->method('newFolder')->with('Deleted')->willReturn($deletedFolder);

		$this->assertSame($deletedFolder, $this->service->getOrCreateDeletedFolder($subfolder));
	}

	// -------------------------------------------------------------------------
	// moveToDeletedFolder
	// -------------------------------------------------------------------------

	private function makeAttachmentInSubfolder(string $sharedWith): array {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getParent')->willReturn($convFolder);

		$file = $this->createMock(Node::class);
		$file->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$file->method('getParent')->willReturn($subfolder);
		$file->method('getName')->willReturn('photo.jpg');
		$file->method('getPath')->willReturn('/alice/files/Talk/Room-tok/Alice-alice/photo.jpg');

		$userFolder = $this->makeUserFolderMock();
		$userFolder->method('getFirstNodeById')->with(42)->willReturn($file);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$share = $this->createMock(IShare::class);
		$share->method('getSharedWith')->willReturn($sharedWith);
		$this->shareManager->method('getSharesBy')
			->with('alice', IShare::TYPE_ROOM, $subfolder, false, -1)
			->willReturn([$share]);

		return [$convFolder, $file];
	}

	public function testMoveToDeletedFolderMovesSharedAttachment(): void {
		[$convFolder, $file] = $this->makeAttachmentInSubfolder('tok');

		$deletedFolder = $this->createMock(Folder::class);
		$deletedFolder->method('get')->with('photo.jpg')->willThrowException(new NotFoundException());
		$deletedFolder->method('getPath')->willReturn('/alice/files/Talk/Room-tok/Deleted');
		$convFolder->method('get')->with('Deleted')->willReturn($deletedFolder);

		$file->expects($this->once())
			->method('move')
			->with('/alice/files/Talk/Room-tok/Deleted/photo.jpg')
			->willReturn($file);

		$this->service->moveToDeletedFolder('alice', 'tok', 42);
	}

	public function testMoveToDeletedFolderSkipsWhenParentNotSharedWithRoom(): void {
		[$convFolder, $file] = $this->makeAttachmentInSubfolder('other');

		$convFolder->expects($this->never())->method('get');
		$file->expects($this->never())->method('move');

		$this->service->moveToDeletedFolder('alice', 'tok', 42);
	}

	public function testMoveToDeletedFolderSkipsMissingFile(): void {
		$userFolder = $this->makeUserFolderMock();
		$userFolder->method('getFirstNodeById')->with(42)->willReturn(null);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$this->shareManager->expects($this->never())->method('getSharesBy');

		$this->service->moveToDeletedFolder('alice', 'tok', 42);
	}

	public function testTeamAttachmentOfDeletedMessageIsSkippedWhenMissing(): void {
		// Deleted meanwhile, or the author left the team
		$userFolder = $this->makeUserFolderMock();
		$userFolder->method('getFirstNodeById')->with(42)->willReturn(null);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$userFolder->expects($this->never())->method('newFolder');

		$this->service->moveTeamAttachmentToDeletedFolder('alice', 'tok', 42);
	}

	public function testTeamAttachmentMovedElsewhereStaysInTheTeamFolder(): void {
		// Filed in another folder of the team folder: the team works with it now
		$teamFolder = $this->createMock(Folder::class);
		$teamFolder->method('getInternalPath')->willReturn('');
		$documents = $this->createMock(Folder::class);
		$documents->method('getName')->willReturn('Documents');
		$documents->method('getParent')->willReturn($teamFolder);
		$userSubfolder = $this->createMock(Folder::class);
		$userSubfolder->method('getName')->willReturn('Alice-alice');
		$userSubfolder->method('getParent')->willReturn($documents);
		$file = $this->createMock(File::class);
		$file->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$file->method('getParent')->willReturn($userSubfolder);

		$userFolder = $this->makeUserFolderMock();
		$userFolder->method('getFirstNodeById')->with(42)->willReturn($file);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$file->expects($this->never())->method('move');
		$userFolder->expects($this->never())->method('newFolder');

		$this->service->moveTeamAttachmentToDeletedFolder('alice', 'tok', 42);
	}

	// -------------------------------------------------------------------------
	// Team conversations — team folder
	// -------------------------------------------------------------------------

	private function makeTeamRoom(string $token = 'tok1', string $teamId = 'team1'): Room&MockObject {
		$room = $this->makeRoom($token);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_TEAM);
		$room->method('isTeamManaged')->willReturn(true);
		$room->method('getObjectId')->willReturn($teamId);
		return $room;
	}

	/**
	 * @return array{IUserFolder&MockObject, Folder&MockObject, Folder&MockObject} User folder, team folder and the Talk folder inside it
	 */
	private function mockTeamFolder(string $userId, bool $writable = true): array {
		$provider = $this->createMock(ITeamFolderProvider::class);
		$provider->method('getTeamFolder')->with('team1')->willReturn(new TeamFolder(42, 'Team A'));
		$this->teamManager->method('getTeamFolderProvider')->willReturn($provider);

		$userFolder = $this->makeUserFolderMock();
		$teamFolder = $this->createMock(Folder::class);
		$teamTalkFolder = $this->createMock(Folder::class);
		$teamTalkFolder->method('getFreeSpace')->willReturn(FileInfo::SPACE_UNLIMITED);

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$teamFolder->method('isCreatable')->willReturn($writable);
		$teamFolder->method('get')->with(ConversationFolderService::TEAM_ATTACHMENT_FOLDER)->willReturn($teamTalkFolder);

		return [$userFolder, $teamFolder, $teamTalkFolder];
	}

	/**
	 * @return array{0: Folder&MockObject}
	 */
	private function mockTeamSubfolder(): array {
		[$userFolder, $teamFolder, $teamTalkFolder] = $this->mockTeamFolder('alice');
		$userFolder->method('get')->with('Team A')->willReturn($teamFolder);

		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getId')->willReturn(7);
		$subfolder->method('getName')->willReturn('Alice-alice');
		$this->talkConfig->method('getConversationSubfolderName')->with('alice', false)->willReturn('Alice-alice');
		$teamTalkFolder->method('get')->with('Alice-alice')->willThrowException(new NotFoundException());
		$teamTalkFolder->expects($this->once())->method('newFolder')->with('Alice-alice')->willReturn($subfolder);

		return [$subfolder];
	}

	public function testGetOrCreateSubfolderUsesTeamFolderWithoutSharingIt(): void {
		// Teammates can put other files there: only posted files are shared
		$room = $this->makeTeamRoom();
		[$subfolder] = $this->mockTeamSubfolder();
		$this->shareManager->expects($this->never())->method('createShare');

		$result = $this->service->getOrCreateSubfolder('alice', $room, true);
		$this->assertSame($subfolder, $result);
		$this->assertTrue($this->service->isTeamSubfolder($result));
	}

	public function testPostedTeamAttachmentsAreSharedOneByOne(): void {
		// For participants without access to the team folder (federated users,
		// email guests), read only and not mounted for local users
		$room = $this->makeTeamRoom();
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('photo.jpg');
		$share = $this->createMock(IShare::class);
		foreach (['setShareType', 'setSharedBy', 'setShareOwner', 'setSharedWith', 'setMailSend'] as $method) {
			$share->method($method)->willReturnSelf();
		}
		$this->shareManager->method('newShare')->willReturn($share);

		$share->expects($this->once())->method('setNode')->with($file)->willReturnSelf();
		$share->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_READ)->willReturnSelf();
		$share->expects($this->once())->method('setTarget')->with(RoomShareProvider::TEAM_FOLDER_TARGET . '/photo.jpg')->willReturnSelf();
		$this->shareManager->expects($this->once())->method('createShare')->with($share);

		$this->service->shareTeamAttachment($file, 'alice', $room);
	}

	public function testTeamAttachmentsCanBePostedWithoutSharing(): void {
		$room = $this->makeTeamRoom();
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('photo.jpg');
		$share = $this->makeShareMock();
		$share->method('setTarget')->willReturnSelf();
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->method('createShare')->willThrowException(new GenericShareException('Sharing is not allowed'));

		// Only participants without access to the team folder can not open it
		$this->service->shareTeamAttachment($file, 'alice', $room);
		$this->addToAssertionCount(1);
	}

	public function testGetOrCreateSubfolderFallsBackWithoutTeamFolderProvider(): void {
		$room = $this->makeTeamRoom();
		$this->teamManager->method('getTeamFolderProvider')->willReturn(null);

		$subfolder = $this->createStub(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$attachmentNode = $this->createMock(Folder::class);
		$userFolder = $this->makeUserFolderMock();

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Team A-tok1');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($attachmentNode);
		$attachmentNode->method('get')->with('Team A-tok1')->willReturn($convFolder);
		$convFolder->method('get')->with('Alice-alice')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		$this->shareManager->expects($this->once())->method('createShare');

		$result = $this->service->getOrCreateSubfolder('alice', $room, false);
		$this->assertSame($subfolder, $result);
		$this->assertFalse($this->service->isTeamSubfolder($result));
	}

	public function testGetOrCreateSubfolderFallsBackWhenTeamFolderIsReadOnly(): void {
		$room = $this->makeTeamRoom();
		[$userFolder, $teamFolder] = $this->mockTeamFolder('alice', false);

		$subfolder = $this->createStub(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$attachmentNode = $this->createMock(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Team A-tok1');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');
		$userFolder->method('get')->willReturnMap([
			['Team A', $teamFolder],
			['Talk', $attachmentNode],
		]);
		$attachmentNode->method('get')->with('Team A-tok1')->willReturn($convFolder);
		$convFolder->method('get')->with('Alice-alice')->willReturn($subfolder);

		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());
		$this->shareManager->expects($this->once())->method('createShare');

		$this->assertSame($subfolder, $this->service->getOrCreateSubfolder('alice', $room, false));
	}

	public function testGetOrCreateDraftFolderForUserKeepsTeamDraftsInHome(): void {
		$room = $this->makeTeamRoom();
		[$userFolder, $teamFolder, $teamTalkFolder] = $this->mockTeamFolder('alice');

		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getId')->willReturn(7);
		$subfolder->expects($this->never())->method('getParent');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');
		$teamTalkFolder->method('get')->with('Alice-alice')->willReturn($subfolder);

		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$draftFolder = $this->createStub(Folder::class);
		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Team A-tok1');
		$userFolder->method('get')->willReturnMap([
			['Team A', $teamFolder],
			['Talk', $attachmentNode],
		]);
		$attachmentNode->method('get')->with('Team A-tok1')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($draftFolder);

		$subfolder = $this->service->getOrCreateSubfolder('alice', $room, false);
		$this->assertSame($draftFolder, $this->service->getOrCreateDraftFolderForUser('alice', $room, $subfolder));
	}

	public function testGetOrCreateDraftFolderForUserUsesSiblingOutsideTeamFolder(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$draftFolder = $this->createStub(Folder::class);
		$subfolder->method('getId')->willReturn(9);
		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($draftFolder);

		$this->assertSame($draftFolder, $this->service->getOrCreateDraftFolderForUser('alice', $this->makeRoom(), $subfolder));
	}

	// -------------------------------------------------------------------------
	// finalizeUploadedFile — quota of the team folder
	// -------------------------------------------------------------------------

	/**
	 * Returns a team subfolder registered with the service, with the given free space
	 */
	private function makeTeamSubfolder(int|float $freeSpace): Folder&MockObject {
		$room = $this->makeTeamRoom();
		[$userFolder, $teamFolder, $teamTalkFolder] = $this->mockTeamFolder('alice');
		$userFolder->method('get')->with('Team A')->willReturn($teamFolder);

		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getId')->willReturn(7);
		$subfolder->method('getPath')->willReturn('/__groupfolders/1/Talk/Alice-alice');
		$subfolder->method('getFreeSpace')->willReturn($freeSpace);
		$subfolder->method('get')->willThrowException(new NotFoundException());
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');
		$teamTalkFolder->method('get')->with('Alice-alice')->willReturn($subfolder);

		return $this->service->getOrCreateSubfolder('alice', $room, false);
	}

	public function testFinalizeUploadedFileRefusesFileLargerThanTeamFolderSpace(): void {
		$subfolder = $this->makeTeamSubfolder(100);

		$node = $this->createMock(Node::class);
		$node->method('getSize')->willReturn(101);
		$node->expects($this->never())->method('move');

		$this->expectException(NotEnoughSpaceException::class);
		$this->service->finalizeUploadedFile($subfolder, $node, 'big.zip');
	}

	public function testFinalizeUploadedFileTurnsRefusedMoveIntoTeamFolderIntoQuotaError(): void {
		$subfolder = $this->makeTeamSubfolder(FileInfo::SPACE_UNLIMITED);

		$node = $this->createMock(Node::class);
		$node->method('getSize')->willReturn(101);
		$node->method('getPath')->willReturn('/alice/files/Talk/Team A-tok1/Draft/uuid');
		$node->method('move')->willThrowException(new NotPermittedException('Could not move'));

		$this->expectException(NotEnoughSpaceException::class);
		$this->service->finalizeUploadedFile($subfolder, $node, 'big.zip');
	}

	public function testFinalizeUploadedFileKeepsMoveErrorsOutsideTeamFolders(): void {
		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getId')->willReturn(9);
		$subfolder->method('getPath')->willReturn('/alice/files/Talk/Room-tok1/Alice-alice');
		$subfolder->method('get')->willThrowException(new NotFoundException());
		$subfolder->expects($this->never())->method('getFreeSpace');

		$node = $this->createMock(Node::class);
		$node->method('getPath')->willReturn('/alice/files/Talk/Room-tok1/Draft/uuid');
		$node->method('move')->willThrowException(new NotPermittedException('Could not move'));

		$this->expectException(NotPermittedException::class);
		$this->service->finalizeUploadedFile($subfolder, $node, 'photo.jpg');
	}
}
