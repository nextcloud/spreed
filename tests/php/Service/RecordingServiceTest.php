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
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Share\IManager as ShareManager;
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
	protected IFactory&MockObject $l10nFactory;
	protected IUserManager&MockObject $userManager;
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
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);

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
			$this->l10nFactory,
			$this->userManager,
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

		$room = $this->createMock(Room::class);
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'participant1',
		]);
		$participant = new Participant($room, $attendee, null);

		$actual = stream_get_contents($this->recordingService->getResourceFromFileArray($file, $room, $participant));
		$this->assertEquals($expected, $actual);
	}
}
