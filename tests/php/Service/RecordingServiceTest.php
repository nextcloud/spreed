<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

/**
 * Overwrite is_uploaded_file in the OCA\Talk\Service namespace
 * to allow proper unit testing of the postAvatar call.
 */
function is_uploaded_file($filename) {
	return file_exists($filename);
}

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Recording\BackendNotifier;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RecordingServiceTest extends TestCase {
	private IMimeTypeDetector $mimeTypeDetector;
	protected ParticipantService&MockObject $participantService;
	protected IRootFolder&MockObject $rootFolder;
	protected Config&MockObject $config;
	protected IConfig&MockObject $serverConfig;
	protected IAppConfig&MockObject $appConfig;
	protected IManager&MockObject $notificationManager;
	protected Manager&MockObject $roomManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected RoomService&MockObject $roomService;
	protected ShareManager&MockObject $shareManager;
	protected ChatManager&MockObject $chatManager;
	protected LoggerInterface&MockObject $logger;
	protected BackendNotifier&MockObject $backendNotifier;
	protected ITaskProcessingManager&MockObject $taskProcessingManager;
	protected ISystemTagObjectMapper&MockObject $systemTagMapper;
	protected IFactory&MockObject $l10nFactory;
	protected IUserManager&MockObject $userManager;
	protected IEventDispatcher&MockObject $eventDispatcher;
	protected ISecureRandom&MockObject $secureRandom;
	protected RecordingService $recordingService;

	public function setUp(): void {
		parent::setUp();

		$this->mimeTypeDetector = \OCP\Server::get(IMimeTypeDetector::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->roomManager = $this->createMock(Manager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(Config::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->shareManager = $this->createMock(ShareManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->taskProcessingManager = $this->createMock(ITaskProcessingManager::class);
		$this->systemTagMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);

		$this->recordingService = new RecordingService(
			$this->mimeTypeDetector,
			$this->participantService,
			$this->rootFolder,
			$this->notificationManager,
			$this->roomManager,
			$this->timeFactory,
			$this->config,
			$this->serverConfig,
			$this->appConfig,
			$this->roomService,
			$this->shareManager,
			$this->chatManager,
			$this->logger,
			$this->backendNotifier,
			$this->taskProcessingManager,
			$this->systemTagMapper,
			$this->l10nFactory,
			$this->userManager,
			$this->eventDispatcher,
			$this->secureRandom,
		);
	}

	public static function dataValidateFileFormat(): array {
		return [
			# file_invalid_path
			['', '', 'file_invalid_path'],
			# file_mimetype
			['', realpath(__DIR__ . '/../../../img/app.svg'), 'file_mimetype'],
			['name.ogg', realpath(__DIR__ . '/../../../img/app.svg'), 'file_mimetype'],
			# file_extension
			['', realpath(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			['name', realpath(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			['name.mp3', realpath(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			# Success
			['name.ogg', realpath(__DIR__ . '/../../../img/join_call.ogg'), ''],
		];
	}

	#[DataProvider('dataValidateFileFormat')]
	public function testValidateFileFormat(string $fileName, string $fileRealPath, string $exceptionMessage): void {
		if ($exceptionMessage) {
			$this->expectExceptionMessage($exceptionMessage);
		} else {
			$this->expectNotToPerformAssertions();
		}
		$this->recordingService->validateFileFormat($fileName, $fileRealPath);
	}

	public static function dataGetResourceFromFileArray(): array {
		$fileWithContent = tempnam(sys_get_temp_dir(), 'txt');
		file_put_contents($fileWithContent, 'bla');
		return [
			[['error' => 1, 'tmp_name' => ''], '', 'invalid_file'],
			[['error' => 1, 'tmp_name' => 'a'], '', 'invalid_file'],
			# Empty file
			[['error' => 0, 'tmp_name' => tempnam(sys_get_temp_dir(), 'txt')], '', 'empty_file'],
			# file with content
			[['error' => 0, 'tmp_name' => $fileWithContent], 'bla', ''],
		];
	}

	#[DataProvider('dataGetResourceFromFileArray')]
	public function testGetResourceFromFileArray(array $file, string $expected, string $exceptionMessage): void {
		if ($exceptionMessage) {
			$this->expectExceptionMessage($exceptionMessage);
		}

		$room = $this->createStub(Room::class);
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'participant1',
		]);
		$participant = new Participant($room, $attendee, null);

		$actual = stream_get_contents($this->recordingService->getResourceFromFileArray($file, $room, $participant));
		$this->assertEquals($expected, $actual);
	}

	protected function createRoom(string $token = 'token123'): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getToken')->willReturn($token);
		return $room;
	}

	protected function createParticipant(Room $room, string $actorId = 'user1'): Participant {
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => $actorId,
		]);
		return new Participant($room, $attendee, null);
	}

	protected function mockRecordingFolder(string $owner, string $token): Folder&MockObject {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')->with($owner)->willReturn($userFolder);
		$this->config->method('getRecordingFolder')->with($owner)->willReturn('/Talk');

		$rootRecordingFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->with('/Talk')->willReturn($rootRecordingFolder);
		$rootRecordingFolder->method('isShared')->willReturn(false);

		$recordingFolder = $this->createMock(Folder::class);
		$rootRecordingFolder->method('get')->with($token)->willReturn($recordingFolder);

		return $recordingFolder;
	}

	protected function mockNotification(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->timeFactory->method('getDateTime')->willReturnCallback(fn () => new \DateTime());
	}

	public function testRequestUpload(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$recordingFolder = $this->mockRecordingFolder($owner, 'token123');

		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(true);

		$this->secureRandom->method('generate')->willReturn('s3cr3tp4ssw0rd');
		$this->timeFactory->method('getDateTime')->willReturnCallback(fn () => new \DateTime());

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())->method('setNode')->with($recordingFolder);
		$share->expects($this->once())->method('setShareType')->with(IShare::TYPE_LINK);
		$share->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_CREATE);
		$share->expects($this->once())->method('setPassword')->with('s3cr3tp4ssw0rd');
		$share->method('getToken')->willReturn('shareToken');
		$this->shareManager->method('newShare')->willReturn($share);
		$this->shareManager->expects($this->once())->method('createShare')->with($share)->willReturn($share);

		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with(RecordingService::APPCONFIG_UPLOAD_PREFIX . 'token123/' . sha1('recording.mp4'), 'shareToken', true, true);

		// The active-recording marker is cleared once the upload share is created,
		// so a new recording can start while this one is still being uploaded.
		$this->appConfig->expects($this->once())
			->method('deleteAppValue')
			->with(RecordingService::APPCONFIG_PREFIX . 'token123');

		$result = $this->recordingService->requestUpload($room, $owner, 'recording.mp4');

		$this->assertSame([
			'token' => 'shareToken',
			'password' => 's3cr3tp4ssw0rd',
			'fileName' => 'recording.mp4',
		], $result);
	}

	public function testRequestUploadSharingDisabled(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$this->shareManager->method('shareApiAllowLinks')->willReturn(true);
		$this->shareManager->method('shareApiLinkAllowPublicUpload')->willReturn(false);
		$this->shareManager->expects($this->never())->method('createShare');

		$this->expectExceptionMessage('sharing_disabled');
		$this->recordingService->requestUpload($room, $owner, 'recording.mp4');
	}

	public function testRequestUploadInvalidExtension(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$this->shareManager->expects($this->never())->method('createShare');

		$this->expectExceptionMessage('file_extension');
		$this->recordingService->requestUpload($room, $owner, 'recording.exe');
	}

	public function testFinishUpload(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$recordingFolder = $this->mockRecordingFolder($owner, 'token123');

		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('name.ogg');
		$file->method('getMimeType')->willReturn('audio/ogg');
		$file->method('getSize')->willReturn(1024);
		$file->method('getId')->willReturn(42);
		$recordingFolder->method('get')->with('name.ogg')->willReturn($file);

		$this->mockNotification();
		// Disable both AI tasks to keep the finalize path simple
		$this->serverConfig->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = '') => $key === 'call_recording_summary' ? 'no' : 'no'
		);

		// Cleanup of the temporary share
		$this->appConfig->method('getAppValueString')->willReturn('shareToken');
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')->with('shareToken')->willReturn($share);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($share);
		// Only the temporary upload share's tracking value is cleared here; the
		// active-recording marker was already removed in requestUpload().
		$this->appConfig->expects($this->once())->method('deleteAppValue')
			->with(RecordingService::APPCONFIG_UPLOAD_PREFIX . 'token123/' . sha1('name.ogg'));

		$this->notificationManager->expects($this->once())->method('notify');

		$this->recordingService->finishUpload($room, $owner, 'name.ogg');
	}

	public function testFinishUploadMissingFile(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$recordingFolder = $this->mockRecordingFolder($owner, 'token123');
		$recordingFolder->method('get')->with('name.ogg')->willThrowException(new NotFoundException());

		$this->appConfig->method('getAppValueString')->willReturn('');

		$this->expectExceptionMessage('invalid_file');
		$this->recordingService->finishUpload($room, $owner, 'name.ogg');
	}

	public function testFinishUploadEmptyFile(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$recordingFolder = $this->mockRecordingFolder($owner, 'token123');

		$file = $this->createMock(File::class);
		$file->method('getSize')->willReturn(0);
		$file->expects($this->once())->method('delete');
		$recordingFolder->method('get')->with('name.ogg')->willReturn($file);

		$this->appConfig->method('getAppValueString')->willReturn('');

		$this->expectExceptionMessage('empty_file');
		$this->recordingService->finishUpload($room, $owner, 'name.ogg');
	}

	public function testFinishUploadInvalidFormat(): void {
		$owner = 'user1';
		$room = $this->createRoom();
		$participant = $this->createParticipant($room, $owner);
		$this->participantService->method('getParticipant')
			->with($room, $owner)
			->willReturn($participant);

		$recordingFolder = $this->mockRecordingFolder($owner, 'token123');

		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('name.ogg');
		$file->method('getMimeType')->willReturn('image/svg+xml');
		$file->method('getSize')->willReturn(1024);
		$file->expects($this->once())->method('delete');
		$recordingFolder->method('get')->with('name.ogg')->willReturn($file);

		$this->appConfig->method('getAppValueString')->willReturn('shareToken');
		$share = $this->createMock(IShare::class);
		$this->shareManager->method('getShareByToken')->willReturn($share);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($share);

		$this->expectExceptionMessage('file_mimetype');
		$this->recordingService->finishUpload($room, $owner, 'name.ogg');
	}
}
