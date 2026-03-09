<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config as TalkConfig;
use OCA\Talk\Controller\FilesIntegrationController;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FilesIntegrationControllerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected RoomService&MockObject $roomService;
	protected IShareManager&MockObject $shareManager;
	protected ISession&MockObject $session;
	protected IUserSession&MockObject $userSession;
	protected TalkSession&MockObject $talkSession;
	protected Util&MockObject $util;
	protected IConfig&MockObject $config;
	protected TalkConfig&MockObject $talkConfig;
	protected IRootFolder&MockObject $rootFolder;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected ITimeFactory&MockObject $timeFactory;

	protected FilesIntegrationController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->session = $this->createMock(ISession::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->util = $this->createMock(Util::class);
		$this->config = $this->createMock(IConfig::class);
		$this->talkConfig = $this->createMock(TalkConfig::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text) => $text);

		$this->controller = new FilesIntegrationController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->manager,
			$this->roomService,
			$this->shareManager,
			$this->session,
			$this->userSession,
			$this->talkSession,
			$this->util,
			$this->config,
			$this->talkConfig,
			$this->rootFolder,
			$this->participantService,
			$this->chatManager,
			$this->timeFactory,
			$l,
		);
	}

	private function mockLoggedInUser(string $userId): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
	}

	private function mockConversationUploadFolder(string $userId, Room&MockObject $room, string $folderPath): void {
		$this->talkConfig->method('getConversationUploadFolder')
			->with($userId, $room)
			->willReturn($folderPath);
	}

	public function testShareConversationFileNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/user/file.txt');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShareConversationFileRoomNotFound(): void {
		$this->mockLoggedInUser('alice');

		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willThrowException(new RoomNotFoundException());

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/file.txt');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShareConversationFileParticipantNotFound(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'alice', false)
			->willThrowException(new ParticipantNotFoundException());

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/file.txt');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShareConversationFileNoChatPermission(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_DEFAULT & ~Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'alice', false)
			->willReturn($participant);

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/file.txt');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testShareConversationFileNotFound(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->with('Talk/Room/alice/file.txt')
			->willThrowException(new NotFoundException());
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('alice')
			->willReturn($userFolder);

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/file.txt');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testShareConversationFileIsDirectory(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FOLDER);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->willReturn($node);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('alice')
			->willReturn($userFolder);

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/subfolder');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testShareConversationFileOutsideConversationFolder(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturn($node);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);

		// The file is in a different folder than the expected conversation upload folder
		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room-token1/alice');

		$response = $this->controller->shareConversationFile('token1', 'Documents/secret.pdf');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testShareConversationFileSuccess(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->with('token1', 'alice')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'alice', false)
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getId')->willReturn(42);
		$node->method('getMimeType')->willReturn('image/png');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->with('Talk/Room/alice/photo.png')
			->willReturn($node);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('alice')
			->willReturn($userFolder);

		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room/alice');

		$dateTime = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($dateTime);

		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				'alice',
				$this->callback(function (string $message): bool {
					$decoded = json_decode($message, true);
					return $decoded['message'] === 'file_shared'
						&& $decoded['parameters']['file'] === '42'
						&& $decoded['parameters']['metaData']['mimeType'] === 'image/png';
				}),
				$dateTime,
				true,
				null,
				null,
				false,
				false,
				0,
			);

		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/photo.png', '', '');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testShareConversationFileWithReferenceIdAndCaption(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getId')->willReturn(99);
		$node->method('getMimeType')->willReturn('text/plain');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturn($node);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room/alice');
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				'alice',
				$this->callback(function (string $message): bool {
					$decoded = json_decode($message, true);
					return $decoded['parameters']['file'] === '99'
						&& $decoded['parameters']['metaData']['caption'] === 'my caption'
						&& $decoded['parameters']['metaData']['mimeType'] === 'text/plain';
				}),
				$this->anything(),
				true,
				'ref-id-123',
				null,
				false,
				false,
				0,
			);

		$talkMetaData = json_encode(['caption' => 'my caption']);
		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/doc.txt', 'ref-id-123', $talkMetaData);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testShareConversationFileWithSilentOption(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getId')->willReturn(77);
		$node->method('getMimeType')->willReturn('audio/mpeg');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturn($node);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room/alice');
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				'alice',
				$this->anything(),
				$this->anything(),
				true,
				null,
				null,
				false,
				true,  // $silent = true
				0,
			);

		$talkMetaData = json_encode([Message::METADATA_SILENT => true]);
		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/audio.mp3', '', $talkMetaData);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testShareConversationFileVoiceMessageValidMimeType(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getId')->willReturn(55);
		$node->method('getMimeType')->willReturn('audio/mpeg');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturn($node);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room/alice');
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		// Voice message type should be kept for audio/mpeg
		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				'alice',
				$this->callback(function (string $message): bool {
					$decoded = json_decode($message, true);
					return $decoded['parameters']['metaData']['messageType'] === ChatManager::VERB_VOICE_MESSAGE;
				}),
				$this->anything(),
				true,
				null,
				null,
				false,
				false,
				0,
			);

		$talkMetaData = json_encode(['messageType' => ChatManager::VERB_VOICE_MESSAGE]);
		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/voice.mp3', '', $talkMetaData);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testShareConversationFileVoiceMessageInvalidMimeType(): void {
		$this->mockLoggedInUser('alice');

		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomForUserByToken')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$participant->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_CHAT);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->willReturn($participant);

		$node = $this->createMock(Node::class);
		$node->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$node->method('getId')->willReturn(66);
		$node->method('getMimeType')->willReturn('image/png');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturn($node);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->mockConversationUploadFolder('alice', $room, 'Talk/Room/alice');
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		// Voice message type should be stripped for non-audio MIME types
		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$room,
				$participant,
				Attendee::ACTOR_USERS,
				'alice',
				$this->callback(function (string $message): bool {
					$decoded = json_decode($message, true);
					return !isset($decoded['parameters']['metaData']['messageType']);
				}),
				$this->anything(),
				true,
				null,
				null,
				false,
				false,
				0,
			);

		$talkMetaData = json_encode(['messageType' => ChatManager::VERB_VOICE_MESSAGE]);
		$response = $this->controller->shareConversationFile('token1', 'Talk/Room/alice/photo.png', '', $talkMetaData);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
