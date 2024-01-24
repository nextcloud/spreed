<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022, Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\Notification\IManager;
use OCP\Share\IManager as ShareManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RecordingServiceTest extends TestCase {
	/** @var IMimeTypeDetector */
	private $mimeTypeDetector;
	/** @var ParticipantService|MockObject */
	private $participantService;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var Config|MockObject */
	private $config;
	/** @var IConfig|MockObject */
	private $serverConfig;
	/** @var IManager|MockObject */
	private $notificationManager;
	/** @var Manager|MockObject */
	private $roomManager;
	/** @var ITimeFactory|MockObject */
	private $timeFactory;
	/** @var RoomService|MockObject */
	private $roomService;
	/** @var ShareManager|MockObject */
	private $shareManager;
	/** @var ChatManager|MockObject */
	private $chatManager;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var BackendNotifier|MockObject */
	private $backendNotifier;
	/** @var RecordingService */
	protected $recordingService;

	public function setUp(): void {
		parent::setUp();

		$this->mimeTypeDetector = \OC::$server->get(IMimeTypeDetector::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->roomManager = $this->createMock(Manager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->config = $this->createMock(Config::class);
		$this->serverConfig = $this->createMock(IConfig::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->shareManager = $this->createMock(ShareManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->backendNotifier = $this->createMock(BackendNotifier::class);

		$this->recordingService = new RecordingService(
			$this->mimeTypeDetector,
			$this->participantService,
			$this->rootFolder,
			$this->notificationManager,
			$this->roomManager,
			$this->timeFactory,
			$this->config,
			$this->serverConfig,
			$this->roomService,
			$this->shareManager,
			$this->chatManager,
			$this->logger,
			$this->backendNotifier,
		);
	}

	/** @dataProvider dataValidateFileFormat */
	public function testValidateFileFormat(string $fileName, string $content, string $exceptionMessage):void {
		if ($exceptionMessage) {
			$this->expectExceptionMessage($exceptionMessage);
		} else {
			$this->expectNotToPerformAssertions();
		}
		$this->recordingService->validateFileFormat($fileName, $content);
	}

	public function dataValidateFileFormat(): array {
		return [
			# file_mimetype
			['', '', 'file_mimetype'],
			['', file_get_contents(__DIR__ . '/../../../img/app.svg'), 'file_mimetype'],
			['name.ogg', file_get_contents(__DIR__ . '/../../../img/app.svg'), 'file_mimetype'],
			# file_extension
			['', file_get_contents(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			['name', file_get_contents(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			['name.mp3', file_get_contents(__DIR__ . '/../../../img/join_call.ogg'), 'file_extension'],
			# Success
			['name.ogg', file_get_contents(__DIR__ . '/../../../img/join_call.ogg'), ''],
		];
	}

	/**
	 * @dataProvider dataGetContentFromFileArray
	 */
	public function testGetContentFromFileArray(array $file, $expected, string $exceptionMessage): void {
		if ($exceptionMessage) {
			$this->expectExceptionMessage($exceptionMessage);
		}

		$room = $this->createMock(Room::class);
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'participant1',
		]);
		$participant = new Participant($room, $attendee, null);

		$actual = $this->recordingService->getContentFromFileArray($file, $room, $participant);
		$this->assertEquals($expected, $actual);
		$this->assertFileDoesNotExist($file['tmp_name']);
	}

	public function dataGetContentFromFileArray(): array {
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
}
