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
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\NotFoundException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConversationFolderServiceTest extends TestCase {
	protected TalkConfig&MockObject $talkConfig;
	protected IRootFolder&MockObject $rootFolder;
	protected IShareManager&MockObject $shareManager;
	protected LoggerInterface&MockObject $logger;
	protected ConversationFolderService $service;

	public function setUp(): void {
		parent::setUp();

		$this->talkConfig = $this->createMock(TalkConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ConversationFolderService(
			$this->talkConfig,
			$this->rootFolder,
			$this->shareManager,
			$this->logger,
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
	private function makeUserFolderMock(int|float $freeSpace = FileInfo::SPACE_UNLIMITED): Folder&MockObject {
		$userFolder = $this->createMock(Folder::class);
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
		$this->service->getOrCreateSubfolder($userId, $room);
	}

	public function testGetOrCreateSubfolderProceedsWithUnlimitedQuota(): void {
		$room = $this->makeRoom('tok0a');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(FileInfo::SPACE_UNLIMITED);
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0a');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		// No exception expected
		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderProceedsWithNotComputedQuota(): void {
		$room = $this->makeRoom('tok0b');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(FileInfo::SPACE_NOT_COMPUTED);
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0b');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderProceedsWhenSpaceAvailable(): void {
		$room = $this->makeRoom('tok0c');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock(1024 * 1024); // 1 MB free
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('Room-tok0c');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($subfolder);
		$this->shareManager->method('newShare')->willReturn($this->makeShareMock());

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	// -------------------------------------------------------------------------
	// getOrCreateSubfolder — happy paths
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderAllFoldersExist(): void {
		$room = $this->makeRoom('tok1');
		$userId = 'alice';

		$subfolder = $this->createMock(Folder::class);
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

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesAttachmentFolder(): void {
		$room = $this->makeRoom('tok2');
		$userId = 'bob';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

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

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesConvFolder(): void {
		$room = $this->makeRoom('tok3');
		$userId = 'carol';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

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

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	public function testGetOrCreateSubfolderCreatesUserSubfolder(): void {
		$room = $this->makeRoom('tok4');
		$userId = 'dave';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

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

		$result = $this->service->getOrCreateSubfolder($userId, $room);
		$this->assertSame($subfolder, $result);
	}

	// -------------------------------------------------------------------------
	// getOrCreateSubfolder — error: path component is a file, not a folder
	// -------------------------------------------------------------------------

	public function testGetOrCreateSubfolderThrowsWhenAttachmentFolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$fileNode = $this->createMock(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->with('Talk')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room);
	}

	public function testGetOrCreateSubfolderThrowsWhenConvFolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$fileNode = $this->createMock(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room);
	}

	public function testGetOrCreateSubfolderThrowsWhenUserSubfolderIsFile(): void {
		$room = $this->makeRoom();
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$fileNode = $this->createMock(Node::class); // not a Folder

		$this->talkConfig->method('getAttachmentFolder')->willReturn('/Talk');
		$this->talkConfig->method('getConversationFolderName')->willReturn('My Room-abc123');
		$this->talkConfig->method('getConversationSubfolderName')->willReturn('Alice-alice');

		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$userFolder->method('get')->willReturn($attachmentNode);
		$attachmentNode->method('get')->willReturn($convFolder);
		$convFolder->method('get')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateSubfolder($userId, $room);
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
		$subfolder = $this->createMock(Folder::class);

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
		$result = $this->service->getOrCreateSubfolder($userId, $room);
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
		$subfolder = $this->createMock(Folder::class);

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

		$this->service->getOrCreateSubfolder($userId, $room);
	}

	public function testGetOrCreateSubfolderRethrowsNonDuplicateGenericShareException(): void {
		$room = $this->makeRoom('tok6b');
		$userId = 'alice';

		$userFolder = $this->makeUserFolderMock();
		$attachmentNode = $this->createMock(Folder::class);
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);

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

		$this->service->getOrCreateSubfolder($userId, $room);
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
		$subfolder = $this->createMock(Folder::class);

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

		$this->service->getOrCreateSubfolder($userId, $room);
	}

	// -------------------------------------------------------------------------
	// getFileNode
	// -------------------------------------------------------------------------

	public function testGetFileNodeReturnsNodeFromUserFolder(): void {
		$userId = 'alice';
		$filePath = 'Talk/Room-tok/Alice-alice/test.txt';

		$userFolder = $this->createMock(Folder::class);
		$node = $this->createMock(Node::class);

		$this->rootFolder->method('getUserFolder')->with($userId)->willReturn($userFolder);
		$userFolder->method('get')->with($filePath)->willReturn($node);

		$result = $this->service->getFileNode($userId, $filePath);
		$this->assertSame($node, $result);
	}

	public function testGetFileNodeThrowsWhenPathNotFound(): void {
		$userId = 'alice';
		$filePath = 'Talk/Room-tok/Alice-alice/missing.txt';

		$userFolder = $this->createMock(Folder::class);

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

		$userFolder = $this->createMock(Folder::class);
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
		$draftFolder = $this->createMock(Folder::class);

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($draftFolder);

		$result = $this->service->getOrCreateDraftFolder($subfolder);
		$this->assertSame($draftFolder, $result);
	}

	public function testGetOrCreateDraftFolderCreatesWhenMissing(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$draftFolder = $this->createMock(Folder::class);

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willThrowException(new NotFoundException('Draft'));
		$convFolder->expects($this->once())->method('newFolder')->with('Draft')->willReturn($draftFolder);

		$result = $this->service->getOrCreateDraftFolder($subfolder);
		$this->assertSame($draftFolder, $result);
	}

	public function testGetOrCreateDraftFolderThrowsWhenDraftIsFile(): void {
		$convFolder = $this->createMock(Folder::class);
		$subfolder = $this->createMock(Folder::class);
		$fileNode = $this->createMock(Node::class); // not a Folder

		$subfolder->method('getParent')->willReturn($convFolder);
		$convFolder->method('get')->with('Draft')->willReturn($fileNode);

		$this->expectException(\RuntimeException::class);
		$this->service->getOrCreateDraftFolder($subfolder);
	}
}
