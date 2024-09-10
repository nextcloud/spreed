<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Recording;

use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Config;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Manager;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Recording\BackendNotifier;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\TalkSession;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CustomBackendNotifier extends BackendNotifier {
	private array $requests = [];

	public function getRequests(): array {
		return $this->requests;
	}

	public function clearRequests() {
		$this->requests = [];
	}

	protected function doRequest(string $url, array $params, int $retries = 3): void {
		$this->requests[] = [
			'url' => $url,
			'params' => $params,
		];
	}
}

/**
 * @group DB
 */
class BackendNotifierTest extends TestCase {
	protected IURLGenerator&MockObject $urlGenerator;
	protected ParticipantService $participantService;
	protected ?CustomBackendNotifier $backendNotifier = null;
	protected ?Config $config = null;
	protected ?ISecureRandom $secureRandom = null;
	protected ?Manager $manager = null;
	protected ?string $recordingSecret = null;
	protected ?string $baseUrl = null;

	public function setUp(): void {
		parent::setUp();

		$config = \OCP\Server::get(IConfig::class);
		$this->recordingSecret = 'the-recording-secret';
		$this->baseUrl = 'https://localhost/recording';
		$config->setAppValue('spreed', 'recording_servers', json_encode([
			'secret' => $this->recordingSecret,
			'servers' => [
				[
					'server' => $this->baseUrl,
				],
			],
		]));

		$this->secureRandom = \OCP\Server::get(ISecureRandom::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$appConfig = $this->createMock(IAppConfig::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$dispatcher = \OCP\Server::get(IEventDispatcher::class);

		$this->config = new Config($config, $appConfig, $this->secureRandom, $groupManager, $userManager, $this->urlGenerator, $timeFactory, $dispatcher);

		$this->recreateBackendNotifier();

		$this->participantService = \OCP\Server::get(ParticipantService::class);

		$dbConnection = \OCP\Server::get(IDBConnection::class);
		$this->manager = new Manager(
			$dbConnection,
			$config,
			$this->config,
			\OCP\Server::get(IAppManager::class),
			\OCP\Server::get(AttendeeMapper::class),
			\OCP\Server::get(SessionMapper::class),
			$this->participantService,
			$this->secureRandom,
			$this->createMock(IUserManager::class),
			$groupManager,
			$this->createMock(CommentsManager::class),
			$this->createMock(TalkSession::class),
			$dispatcher,
			$timeFactory,
			$this->createMock(IHasher::class),
			$this->createMock(IL10N::class),
			$this->createMock(Authenticator::class),
		);
	}

	public function tearDown(): void {
		$config = \OCP\Server::get(IConfig::class);
		$config->deleteAppValue('spreed', 'recording_servers');
		parent::tearDown();
	}

	private function recreateBackendNotifier() {
		$this->backendNotifier = new CustomBackendNotifier(
			$this->config,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IClientService::class),
			$this->secureRandom,
			$this->urlGenerator,
		);
	}

	private function calculateBackendChecksum($data, $random) {
		if (empty($random) || strlen($random) < 32) {
			return false;
		}
		return hash_hmac('sha256', $random . $data, $this->recordingSecret);
	}

	private function validateBackendRequest($expectedUrl, $request) {
		$this->assertTrue(isset($request));
		$this->assertEquals($expectedUrl, $request['url']);
		$headers = $request['params']['headers'];
		$this->assertEquals('application/json', $headers['Content-Type']);
		$random = $headers['Talk-Recording-Random'];
		$checksum = $headers['Talk-Recording-Checksum'];
		$body = $request['params']['body'];
		$this->assertEquals($this->calculateBackendChecksum($body, $random), $checksum);
		return $body;
	}

	private function assertMessageWasSent(Room $room, array $message): void {
		$expectedUrl = $this->baseUrl . '/api/v1/room/' . $room->getToken();

		$requests = $this->backendNotifier->getRequests();
		$requests = array_filter($requests, function ($request) use ($expectedUrl) {
			return $request['url'] === $expectedUrl;
		});
		$bodies = array_map(function ($request) use ($expectedUrl) {
			return json_decode($this->validateBackendRequest($expectedUrl, $request), true);
		}, $requests);

		$bodies = array_filter($bodies, function (array $body) use ($message) {
			return $body['type'] === $message['type'];
		});

		$this->assertContainsEquals($message, $bodies, json_encode($bodies, JSON_PRETTY_PRINT));
	}

	public function testStart(): void {
		$userId = 'testUser';

		/** @var IUser&MockObject $testUser */
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn($userId);

		$roomService = $this->createMock(RoomService::class);
		$roomService->method('verifyPassword')
			->willReturn(['result' => true, 'url' => '']);

		$room = $this->manager->createRoom(Room::TYPE_PUBLIC);
		$this->participantService->addUsers($room, [[
			'actorType' => 'users',
			'actorId' => $userId,
		]]);
		$participant = $this->participantService->joinRoom($roomService, $room, $testUser, '');

		$this->backendNotifier->start($room, Room::RECORDING_VIDEO, 'participant1', $participant);

		$this->assertMessageWasSent($room, [
			'type' => 'start',
			'start' => [
				'status' => Room::RECORDING_VIDEO,
				'owner' => 'participant1',
				'actor' => [
					'type' => 'users',
					'id' => $userId,
				],
			],
		]);
	}

	public function testStop(): void {
		$userId = 'testUser';

		/** @var IUser&MockObject $testUser */
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn($userId);

		$roomService = $this->createMock(RoomService::class);
		$roomService->method('verifyPassword')
			->willReturn(['result' => true, 'url' => '']);

		$room = $this->manager->createRoom(Room::TYPE_PUBLIC);
		$this->participantService->addUsers($room, [[
			'actorType' => 'users',
			'actorId' => $userId,
		]]);
		$participant = $this->participantService->joinRoom($roomService, $room, $testUser, '');

		$this->backendNotifier->stop($room, $participant);

		$this->assertMessageWasSent($room, [
			'type' => 'stop',
			'stop' => [
				'actor' => [
					'type' => 'users',
					'id' => $userId,
				],
			],
		]);

		$this->backendNotifier->stop($room);

		$this->assertMessageWasSent($room, [
			'type' => 'stop',
			'stop' => [
			],
		]);
	}
}
