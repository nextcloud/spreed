<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\ConversationFolderService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\TeamFileThreadService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamFileThreadServiceTest extends TestCase {
	protected CirclesAdapter&MockObject $circlesAdapter;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected ThreadService&MockObject $threadService;
	protected TeamFileThreadService&MockObject $service;
	protected Room&MockObject $room;
	protected IUser&MockObject $user;
	protected Node&MockObject $node;

	public function setUp(): void {
		parent::setUp();

		$this->circlesAdapter = $this->createMock(CirclesAdapter::class);
		$this->circlesAdapter->method('isAvailable')->willReturn(true);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->threadService = $this->createMock(ThreadService::class);

		// The lookups of the team folder and of the message are database and filesystem work
		$this->service = $this->getMockBuilder(TeamFileThreadService::class)
			->setConstructorArgs([
				$this->circlesAdapter,
				$this->createMock(Manager::class),
				$this->participantService,
				$this->createMock(ConversationFolderService::class),
				$this->chatManager,
				$this->threadService,
				$this->createMock(ITimeFactory::class),
				$this->createMock(IDBConnection::class),
			])
			->onlyMethods(['getTeamRoomOfAttachment', 'findMessageOfFile'])
			->getMock();

		$this->room = $this->createMock(Room::class);
		$this->room->method('getId')->willReturn(12);
		$this->room->method('getToken')->willReturn('teamroom');
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->node = $this->createMock(Node::class);
		$this->node->method('getId')->willReturn(42);
		$this->node->method('getName')->willReturn('photo.jpg');
		$this->node->method('getPath')->willReturn('/alice/files/Team/' . ConversationFolderService::TEAM_ATTACHMENT_FOLDER . '/alice/photo.jpg');
	}

	public function testNothingIsLookedUpWithoutCircles(): void {
		$circlesAdapter = $this->createMock(CirclesAdapter::class);
		$circlesAdapter->method('isAvailable')->willReturn(false);
		$manager = $this->createMock(Manager::class);
		$manager->expects($this->never())->method('getRoomsForUser');
		$service = new TeamFileThreadService(
			$circlesAdapter,
			$manager,
			$this->participantService,
			$this->createMock(ConversationFolderService::class),
			$this->chatManager,
			$this->threadService,
			$this->createMock(ITimeFactory::class),
			$this->createMock(IDBConnection::class),
		);
		$this->assertNull($service->getThreadForFile($this->user, $this->node));
	}

	public function testNothingIsLookedUpForFilesOutsideOfTalkFolders(): void {
		$node = $this->createMock(Node::class);
		$node->method('getPath')->willReturn('/alice/files/Documents/photo.jpg');
		$this->service->expects($this->never())->method('getTeamRoomOfAttachment');
		$this->assertNull($this->service->getThreadForFile($this->user, $node));
	}

	private function makeComment(int $id, int $topmostParentId = 0): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn((string)$id);
		$comment->method('getTopmostParentId')->willReturn((string)$topmostParentId);
		return $comment;
	}

	public function testFileOutsideOfTeamConversationsKeepsTheFileConversation(): void {
		$this->service->method('getTeamRoomOfAttachment')->willReturn(null);
		$this->assertNull($this->service->getThreadForFile($this->user, $this->node));
	}

	public function testFileThatWasNotPostedKeepsTheFileConversation(): void {
		$this->service->method('getTeamRoomOfAttachment')->willReturn($this->room);
		$this->service->method('findMessageOfFile')->willReturn(null);
		$this->assertNull($this->service->getThreadForFile($this->user, $this->node));
	}

	public function testExistingThreadOfTheMessageIsUsed(): void {
		$this->service->method('getTeamRoomOfAttachment')->willReturn($this->room);
		$this->service->method('findMessageOfFile')->with($this->room, 42)->willReturn($this->makeComment(100));
		$this->threadService->method('findByThreadId')->with(12, 100)->willReturn(new Thread());
		$this->threadService->expects($this->never())->method('createThread');

		$this->assertSame(['room' => $this->room, 'threadId' => 100], $this->service->getThreadForFile($this->user, $this->node));
	}

	public function testFilePostedInAThreadUsesThatThread(): void {
		$this->service->method('getTeamRoomOfAttachment')->willReturn($this->room);
		$this->service->method('findMessageOfFile')->willReturn($this->makeComment(100, 80));
		$this->threadService->expects($this->never())->method('createThread');

		$this->assertSame(['room' => $this->room, 'threadId' => 80], $this->service->getThreadForFile($this->user, $this->node));
	}

	public function testThreadIsStartedOnTheMessageWithTheFileName(): void {
		$this->service->method('getTeamRoomOfAttachment')->willReturn($this->room);
		$comment = $this->makeComment(100);
		$this->service->method('findMessageOfFile')->willReturn($comment);
		$this->threadService->method('findByThreadId')->willThrowException(new DoesNotExistException('none'));

		$thread = new Thread();
		$thread->setId(100);
		$thread->setName('photo.jpg');
		$this->threadService->expects($this->once())->method('createThread')->with($this->room, 100, 'photo.jpg')->willReturn($thread);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow(['actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$participant->method('getPermissions')->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->method('getParticipant')->willReturn($participant);
		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with($this->room, $participant, Attendee::ACTOR_USERS, 'alice', $this->stringContains('thread_created'));

		$this->assertSame(['room' => $this->room, 'threadId' => 100], $this->service->getThreadForFile($this->user, $this->node));
	}

	public static function dataNoThreadIsStarted(): array {
		return [
			// E.g. the team was deleted
			'locked conversation' => [Room::READ_ONLY, Attendee::PERMISSIONS_CHAT, Webinary::LOBBY_NONE, false],
			'no chat permission' => [Room::READ_WRITE, Attendee::PERMISSIONS_DEFAULT & ~Attendee::PERMISSIONS_CHAT, Webinary::LOBBY_NONE, false],
			'waiting in the lobby' => [Room::READ_WRITE, Attendee::PERMISSIONS_CHAT, Webinary::LOBBY_NON_MODERATORS, false],
		];
	}

	/**
	 * Same rules as posting in the chat: nothing is posted on their behalf
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('dataNoThreadIsStarted')]
	public function testNoThreadIsStarted(int $readOnly, int $permissions, int $lobbyState, bool $moderator): void {
		$this->room->method('getReadOnly')->willReturn($readOnly);
		$this->room->method('getLobbyState')->willReturn($lobbyState);
		$this->service->method('getTeamRoomOfAttachment')->willReturn($this->room);
		$this->service->method('findMessageOfFile')->willReturn($this->makeComment(100));
		$this->threadService->method('findByThreadId')->willThrowException(new DoesNotExistException('none'));
		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')->willReturn($permissions);
		$participant->method('hasModeratorPermissions')->willReturn($moderator);
		$this->participantService->method('getParticipant')->willReturn($participant);
		$this->threadService->expects($this->never())->method('createThread');

		$this->assertNull($this->service->getThreadForFile($this->user, $this->node));
	}

	public function testTheRememberedMessageIsUsedWithoutSearchingTheChat(): void {
		$conversationFolderService = $this->createMock(ConversationFolderService::class);
		$conversationFolderService->method('getTeamAttachmentMessageId')->with($this->node)->willReturn(100);
		$comment = $this->createMock(IComment::class);
		$comment->method('getVerb')->willReturn(ChatManager::VERB_OBJECT_SHARED);
		$comment->method('getMessage')->willReturn(json_encode(['message' => 'file_shared', 'parameters' => ['fileId' => '42', 'teamFolder' => true]]));
		$this->chatManager->method('getComment')->with($this->room, '100')->willReturn($comment);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');

		$service = new TeamFileThreadService(
			$this->circlesAdapter,
			$this->createMock(Manager::class),
			$this->participantService,
			$conversationFolderService,
			$this->chatManager,
			$this->threadService,
			$this->createMock(ITimeFactory::class),
			$db,
		);

		$this->assertSame($comment, self::invokePrivate($service, 'findMessageOfFile', [$this->room, 42, $this->node]));
	}
}
