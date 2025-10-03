<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Config;
use OCA\Talk\Controller\SignalingController;
use OCA\Talk\Events\BeforeSignalingResponseSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Signaling\Messages;
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
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CustomInputSignalingController extends SignalingController {
	private $inputStream;

	public function setInputStream($data) {
		$this->inputStream = $data;
	}

	protected function getInputStream(): string {
		return $this->inputStream;
	}
}

#[Group('DB')]
class SignalingControllerTest extends TestCase {
	protected TalkSession&MockObject $session;
	protected \OCA\Talk\Signaling\Manager&MockObject $signalingManager;
	protected Manager|MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected RoomService&MockObject $roomService;
	protected SessionService&MockObject $sessionService;
	protected Messages&MockObject $messages;
	protected IUserManager&MockObject $userManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected IClientService&MockObject $clientService;
	protected IThrottler&MockObject $throttler;
	protected BanService&MockObject $banService;
	protected LoggerInterface&MockObject $logger;
	protected Authenticator&MockObject $authenticator;
	protected IDBConnection $dbConnection;
	protected IConfig $serverConfig;
	protected ?Config $config = null;
	protected ?string $userId = null;
	protected ?ISecureRandom $secureRandom = null;
	protected ?IEventDispatcher $dispatcher = null;

	private ?CustomInputSignalingController $controller = null;

	public function setUp(): void {
		parent::setUp();

		$this->userId = 'testUser';
		$this->secureRandom = \OCP\Server::get(ISecureRandom::class);
		/** @var MockObject|IAppConfig $appConfig */
		$appConfig = $this->createMock(IAppConfig::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$this->serverConfig = \OCP\Server::get(IConfig::class);
		$this->serverConfig->setAppValue('spreed', 'signaling_servers', json_encode([
			'secret' => 'MySecretValue',
		]));
		$this->serverConfig->setAppValue('spreed', 'signaling_ticket_secret', 'the-app-ticket-secret');
		$this->serverConfig->setUserValue($this->userId, 'spreed', 'signaling_ticket_secret', 'the-user-ticket-secret');
		$this->userManager = $this->createMock(IUserManager::class);
		$this->dispatcher = \OCP\Server::get(IEventDispatcher::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = new Config($this->serverConfig, $appConfig, $this->secureRandom, $groupManager, $this->userManager, $urlGenerator, $timeFactory, $this->dispatcher);
		$this->session = $this->createMock(TalkSession::class);
		$this->dbConnection = \OCP\Server::get(IDBConnection::class);
		$this->signalingManager = $this->createMock(\OCA\Talk\Signaling\Manager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->messages = $this->createMock(Messages::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->banService = $this->createMock(BanService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->authenticator = $this->createMock(Authenticator::class);
		$this->recreateSignalingController();
	}

	private function recreateSignalingController() {
		$this->controller = new CustomInputSignalingController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->config,
			$this->signalingManager,
			$this->session,
			$this->manager,
			$this->participantService,
			$this->roomService,
			$this->sessionService,
			$this->dbConnection,
			$this->messages,
			$this->userManager,
			$this->dispatcher,
			$this->timeFactory,
			$this->clientService,
			$this->banService,
			$this->logger,
			$this->authenticator,
			$this->userId,
		);
	}

	public static function dataGetSettingsStunServer(): array {
		return [
			[
				[
					'stun.nextcloud.com:443',
				],
				[
					[
						'urls' => [
							'stun:stun.nextcloud.com:443',
						]
					],
				],
			],
			[
				[
					'stun.nextcloud.com:443',
					'stun.other.com:3478',
				],
				[
					[
						'urls' => [
							'stun:stun.nextcloud.com:443',
							'stun:stun.other.com:3478',
						],
					],
				],
			],
			[
				[
					'',
				],
				[
				],
			],
			[
				[
				],
				[
				],
			],
		];
	}

	#[DataProvider('dataGetSettingsStunServer')]
	public function testGetSettingsStunServer(array $stunServersConfig, array $expectedStunServers): void {
		$this->config = $this->createMock(Config::class);
		$this->recreateSignalingController();

		$this->config->expects($this->once())
			->method('getStunServers')
			->willReturn($stunServersConfig);

		$settings = $this->controller->getSettings()->getData();

		// "stunservers" is always returned, even if empty
		$this->assertArrayHasKey('stunservers', $settings);
		$this->assertSame($expectedStunServers, $settings['stunservers']);
	}

	public static function dataGetSettingsTurnServer(): array {
		return [
			[
				[
					[
						'schemes' => 'turn',
						'server' => 'turn.example.org:3478',
						'username' => 'theUserName',
						'password' => 'thePassword',
						'protocols' => 'udp',
					],
				],
				[
					[
						'urls' => [
							'turn:turn.example.org:3478?transport=udp',
						],
						'username' => 'theUserName',
						'credential' => 'thePassword',
					],
				],
			],
			[
				[
					[
						'schemes' => 'turn,turns',
						'server' => 'turn.example.org:3478',
						'username' => 'theUserName',
						'password' => 'thePassword',
						'protocols' => 'udp,tcp',
					],
				],
				[
					[
						'urls' => [
							'turn:turn.example.org:3478?transport=udp',
							'turn:turn.example.org:3478?transport=tcp',
							'turns:turn.example.org:3478?transport=udp',
							'turns:turn.example.org:3478?transport=tcp',
						],
						'username' => 'theUserName',
						'credential' => 'thePassword',
					],
				],
			],
			[
				[
					[
						'schemes' => 'turn',
						'server' => 'turn.example.org:3478',
						'username' => 'theUserName1',
						'password' => 'thePassword1',
						'protocols' => 'udp,tcp',
					],
					[
						'schemes' => 'turns',
						'server' => 'turn.other.org:443',
						'username' => 'theUserName2',
						'password' => 'thePassword2',
						'protocols' => 'tcp',
					],
					[
						'schemes' => 'turn,turns',
						'server' => 'turn.another.org:443',
						'username' => 'theUserName3',
						'password' => 'thePassword3',
						'protocols' => 'udp',
					],
				],
				[
					[
						'urls' => [
							'turn:turn.example.org:3478?transport=udp',
							'turn:turn.example.org:3478?transport=tcp',
						],
						'username' => 'theUserName1',
						'credential' => 'thePassword1',
					],
					[
						'urls' => [
							'turns:turn.other.org:443?transport=tcp',
						],
						'username' => 'theUserName2',
						'credential' => 'thePassword2',
					],
					[
						'urls' => [
							'turn:turn.another.org:443?transport=udp',
							'turns:turn.another.org:443?transport=udp',
						],
						'username' => 'theUserName3',
						'credential' => 'thePassword3',
					],
				],
			],
			[
				// This would never happen, as the scheme is forced by
				// "getTurnSettings" if empty, but it is added for completeness.
				[
					[
						'schemes' => '',
						'server' => 'turn.example.org:3478',
						'username' => 'theUserName',
						'password' => 'thePassword',
						'protocols' => 'udp',
					],
				],
				[
				],
			],
			[
				[
					[
						'schemes' => 'turn',
						'server' => '',
						'username' => 'theUserName',
						'password' => 'thePassword',
						'protocols' => 'udp',
					],
				],
				[
				],
			],
			[
				[
					[
						'schemes' => 'turn',
						'server' => 'turn.example.org:3478',
						'username' => 'theUserName',
						'password' => 'thePassword',
						'protocols' => '',
					],
				],
				[
				],
			],
			[
				[
				],
				[
				],
			],
		];
	}

	#[DataProvider('dataGetSettingsTurnServer')]
	public function testGetSettingsTurnServer(array $turnServersConfig, array $expectedTurnServers): void {
		$this->config = $this->createMock(Config::class);
		$this->recreateSignalingController();

		$this->config->expects($this->once())
			->method('getTurnSettings')
			->willReturn($turnServersConfig);

		$settings = $this->controller->getSettings()->getData();

		// "turnservers" is always returned, even if empty
		$this->assertArrayHasKey('turnservers', $settings);
		$this->assertSame($expectedTurnServers, $settings['turnservers']);
	}

	public static function dataIsTryingToPublishMedia(): array {
		// For simplicity the SDP contains only the relevant fields and it is
		// not a valid SDP
		return [
			// Audio publisher/receiver
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=sendrecv\n",
				true, false,
			],
			// Audio publisher/receiver with data channel
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				true, false,
			],
			// Video publisher
			[
				"m=video 42108 RTP/AVP 0\n"
				. "a=sendonly\n",
				false, true,
			],
			// Video publisher with data channel
			[
				"m=video 42108 RTP/AVP 0\n"
				. "a=sendonly\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				false, true,
			],
			// Audio and video publisher/receiver
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=sendrecv\n",
				true, true,
			],
			// Audio and video publisher/receiver with data channel
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				true, true,
			],
			// Audio and video receiver with data channel
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				false, false,
			],
			// Audio receiver and video inactive
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=inactive\n",
				false, false,
			],
			// Audio receiver with session publisher/receiver direction
			[
				"a=sendrecv\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "a=recvonly\n",
				false, false,
			],
			// Video inactive with session publisher direction and data channel
			[
				"a=sendonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=inactive\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				false, false,
			],
			// Audio and video with session publisher/receiver direction
			[
				"a=sendrecv\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "m=video 42108 RTP/AVP 0\n",
				true, true,
			],
			// Audio and video with session publisher direction and data channel
			[
				"a=sendonly\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				true, true,
			],
			// Audio and video with session receiver direction and data channel
			[
				"a=recvonly\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n"
				. "a=sendrecv\n",
				false, false,
			],
			// Audio and video with implicit publisher/receiver direction
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "m=video 42108 RTP/AVP 0\n",
				true, true,
			],
			// Audio and video with implicit publisher/receiver direction and
			// data channel
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "m=application 8 UDP/DTLS/SCTP webrtc-datachannel\n",
				true, true,
			],
			// No audio and video description with session publisher direction
			[
				"a=sendonly\n",
				false, false,
			],
			// Several audio and video with mixed directions
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=inactive\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=audio 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=recvonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=inactive\n",
				true, false,
			],
			// Several mixed directions in a single media description (not a
			// valid SDP, but just in case)
			[
				"m=audio 42108 RTP/AVP 0\n"
				. "a=inactive\n"
				. "a=sendrecv\n"
				. "a=recvonly\n"
				. "m=video 42108 RTP/AVP 0\n"
				. "a=sendrecv\n"
				. "a=recvonly\n"
				. "a=inactive\n",
				true, true,
			],
		];
	}

	#[DataProvider('dataIsTryingToPublishMedia')]
	public function testIsTryingToPublishMedia(string $sdp, bool $expectedAudioResult, bool $expectedVideoResult) {
		$this->assertSame($expectedAudioResult, self::invokePrivate($this->controller, 'isTryingToPublishMedia', [$sdp, 'audio']));
		$this->assertSame($expectedVideoResult, self::invokePrivate($this->controller, 'isTryingToPublishMedia', [$sdp, 'video']));
	}

	private function validateBackendRandom($data, $random, $checksum) {
		if (empty($random) || strlen($random) < 32) {
			return false;
		}
		if (empty($checksum)) {
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->config->getSignalingSecret());
		return hash_equals($hash, strtolower($checksum));
	}

	private function calculateBackendChecksum($data, $random) {
		if (empty($random) || strlen($random) < 32) {
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->config->getSignalingSecret());
		return $hash;
	}

	public function testBackendChecksums(): void {
		// Test checksum generation / validation with the example from the API documentation.
		$data = '{"type":"auth","auth":{"version":"1.0","params":{"hello":"world"}}}';
		$random = 'afb6b872ab03e3376b31bf0af601067222ff7990335ca02d327071b73c0119c6';
		$checksum = '3c4a69ff328299803ac2879614b707c807b4758cf19450755c60656cac46e3bc';
		$this->assertEquals($checksum, $this->calculateBackendChecksum($data, $random));
		$this->assertTrue($this->validateBackendRandom($data, $random, $checksum));
	}

	private function performBackendRequest($data) {
		if (!is_string($data)) {
			$data = json_encode($data);
		}
		$random = 'afb6b872ab03e3376b31bf0af601067222ff7990335ca02d327071b73c0119c6';
		$checksum = $this->calculateBackendChecksum($data, $random);
		$_SERVER['HTTP_SPREED_SIGNALING_RANDOM'] = $random;
		$_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'] = $checksum;
		$this->controller->setInputStream($data);
		return $this->controller->backend();
	}

	public function testBackendChecksumValidation(): void {
		$data = '{}';

		// Random and checksum missing.
		$this->controller->setInputStream($data);
		$result = $this->controller->backend();
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'invalid_request',
				'message' => 'The request could not be authenticated.',
			],
		], $result->getData());

		// Invalid checksum.
		$this->controller->setInputStream($data);
		$random = 'afb6b872ab03e3376b31bf0af601067222ff7990335ca02d327071b73c0119c6';
		$checksum = $this->calculateBackendChecksum('{"foo": "bar"}', $random);
		$_SERVER['HTTP_SPREED_SIGNALING_RANDOM'] = $random;
		$_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'] = $checksum;
		$result = $this->controller->backend();
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'invalid_request',
				'message' => 'The request could not be authenticated.',
			],
		], $result->getData());

		// Short random
		$this->controller->setInputStream($data);
		$random = '12345';
		$checksum = $this->calculateBackendChecksum($data, $random);
		$_SERVER['HTTP_SPREED_SIGNALING_RANDOM'] = $random;
		$_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'] = $checksum;
		$result = $this->controller->backend();
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'invalid_request',
				'message' => 'The request could not be authenticated.',
			],
		], $result->getData());
	}

	public function testBackendUnsupportedType(): void {
		$result = $this->performBackendRequest([
			'type' => 'unsupported-type',
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'unknown_type',
				'message' => 'The given type {"type":"unsupported-type"} is not supported.',
			],
		], $result->getData());
	}

	public function testBackendAuth(): void {
		// Check validating of tickets.
		$result = $this->performBackendRequest([
			'type' => 'auth',
			'auth' => [
				'params' => [
					'userid' => $this->userId,
					'ticket' => 'invalid-ticket',
				],
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'invalid_ticket',
				'message' => 'The given ticket is not valid for this user.',
			],
		], $result->getData());

		// Check validating ticket for passed user.
		$result = $this->performBackendRequest([
			'type' => 'auth',
			'auth' => [
				'params' => [
					'userid' => 'invalid-userid',
					'ticket' => $this->config->getSignalingTicket(Config::SIGNALING_TICKET_V1, $this->userId),
				],
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'invalid_ticket',
				'message' => 'The given ticket is not valid for this user.',
			],
		], $result->getData());

		// Check validating of existing users.
		$result = $this->performBackendRequest([
			'type' => 'auth',
			'auth' => [
				'params' => [
					'userid' => 'unknown-userid',
					'ticket' => $this->config->getSignalingTicket(Config::SIGNALING_TICKET_V1, 'unknown-userid'),
				],
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'no_such_user',
				'message' => 'The given user does not exist.',
			],
		], $result->getData());

		// Check successfull authentication of users.
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->once())
			->method('getDisplayName')
			->willReturn('Test User');
		$testUser->expects($this->once())
			->method('getUID')
			->willReturn($this->userId);
		$this->userManager->expects($this->once())
			->method('get')
			->with($this->userId)
			->willReturn($testUser);
		$result = $this->performBackendRequest([
			'type' => 'auth',
			'auth' => [
				'params' => [
					'userid' => $this->userId,
					'ticket' => $this->config->getSignalingTicket(Config::SIGNALING_TICKET_V1, $this->userId),
				],
			],
		]);
		$this->assertSame([
			'type' => 'auth',
			'auth' => [
				'version' => '1.0',
				'userid' => $this->userId,
				'user' => [
					'displayname' => 'Test User',
				],
			],
		], $result->getData());

		// Check successfull authentication of anonymous participants.
		$result = $this->performBackendRequest([
			'type' => 'auth',
			'auth' => [
				'params' => [
					'userid' => '',
					'ticket' => $this->config->getSignalingTicket(Config::SIGNALING_TICKET_V1, ''),
				],
			],
		]);
		$this->assertSame([
			'type' => 'auth',
			'auth' => [
				'version' => '1.0',
			],
		], $result->getData());
	}

	public function testBackendRoomUnknown(): void {
		$roomToken = 'the-room';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willThrowException(new RoomNotFoundException());

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'no_such_room',
				'message' => 'The user is not invited to this room.',
			],
		], $result->getData());
	}

	public function testBackendRoomInvited(): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, $this->userId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_ONE_TO_ONE,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_ONE_TO_ONE,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
				],
			],
		], $result->getData());
	}

	public function testBackendRoomUserPublic(): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, $this->userId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_PUBLIC,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_PUBLIC,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
				],
			],
		], $result->getData());
	}

	public function testBackendRoomModeratorPublic(): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$participant->expects($this->once())
			->method('hasModeratorPermissions')
			->with(false)
			->willReturn(true);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, $this->userId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_PUBLIC,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_PUBLIC,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
					'control',
				],
			],
		], $result->getData());
	}

	public function testBackendRoomAnonymousPublic(): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$this->participantService->expects($this->once())
			->method('getParticipantBySession')
			->with($room, $sessionId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with('')
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_PUBLIC,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => '',
				'sessionid' => $sessionId,
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_PUBLIC,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
				],
			],
		], $result->getData());
	}

	public function testBackendRoomInvitedPublic(): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$this->participantService->expects($this->once())
			->method('getParticipantBySession')
			->with($room, $sessionId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_PUBLIC,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => $sessionId,
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_PUBLIC,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
				],
			],
		], $result->getData());
	}

	public static function dataBackendRoomUserPublicPermissions(): array {
		return [
			[Attendee::PERMISSIONS_DEFAULT, []],
			[Attendee::PERMISSIONS_PUBLISH_AUDIO, ['publish-audio']],
			[Attendee::PERMISSIONS_PUBLISH_VIDEO, ['publish-video']],
			[Attendee::PERMISSIONS_PUBLISH_AUDIO | Attendee::PERMISSIONS_PUBLISH_VIDEO, ['publish-audio', 'publish-video']],
			[Attendee::PERMISSIONS_PUBLISH_SCREEN, ['publish-screen']],
			[Attendee::PERMISSIONS_PUBLISH_AUDIO | Attendee::PERMISSIONS_PUBLISH_SCREEN, ['publish-audio', 'publish-screen']],
			[Attendee::PERMISSIONS_PUBLISH_VIDEO | Attendee::PERMISSIONS_PUBLISH_SCREEN, ['publish-video', 'publish-screen']],
			[Attendee::PERMISSIONS_PUBLISH_AUDIO | Attendee::PERMISSIONS_PUBLISH_VIDEO | Attendee::PERMISSIONS_PUBLISH_SCREEN, ['publish-audio', 'publish-video', 'publish-screen']],
		];
	}

	#[DataProvider('dataBackendRoomUserPublicPermissions')]
	public function testBackendRoomUserPublicPermissions(int $permissions, array $expectedBackendPermissions): void {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => $permissions,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn($permissions);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, $this->userId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_PUBLIC,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_PUBLIC,
				],
				'permissions' => $expectedBackendPermissions,
			],
		], $result->getData());
	}

	public function testBackendRoomAnonymousOneToOne(): void {
		$roomToken = 'the-room';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$this->participantService->expects($this->once())
			->method('getParticipantBySession')
			->willThrowException(new ParticipantNotFoundException());

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => '',
				'sessionid' => $sessionId,
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'no_such_room',
				'message' => 'The user is not invited to this room.',
			],
		], $result->getData());
	}

	public function testBackendRoomSessionFromEvent(): void {
		$this->dispatcher->addListener(BeforeSignalingResponseSentEvent::class, static function (BeforeSignalingResponseSentEvent $event): void {
			$room = $event->getRoom();
			$event->setSession([
				'foo' => 'bar',
				'room' => $room->getToken(),
			]);
		});

		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$attendee = Attendee::fromRow([
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'actor_type' => Attendee::ACTOR_USERS,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$participant->expects($this->any())
			->method('getPermissions')
			->willReturn(Attendee::PERMISSIONS_MAX_CUSTOM);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, $this->userId)
			->willReturn($participant);
		$room->expects($this->atLeastOnce())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getPropertiesForSignaling')
			->with($this->userId)
			->willReturn([
				'name' => $roomName,
				'type' => Room::TYPE_ONE_TO_ONE,
			]);

		$result = $this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $roomToken,
				'userid' => $this->userId,
				'sessionid' => '',
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $roomToken,
				'properties' => [
					'name' => $roomName,
					'type' => Room::TYPE_ONE_TO_ONE,
				],
				'permissions' => [
					'publish-audio',
					'publish-video',
					'publish-screen',
				],
				'session' => [
					'foo' => 'bar',
					'room' => $roomToken,
				],
			],
		], $result->getData());
	}

	public function testBackendPingUser(): void {
		$sessionId = 'the-session';

		$this->timeFactory->method('getTime')
			->willReturn(123456);
		$this->sessionService->expects($this->once())
			->method('updateMultipleLastPings')
			->with([$sessionId], 123456);

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'entries' => [
					[
						'userid' => $this->userId,
						'sessionid' => $sessionId,
					],
				],
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
			],
		], $result->getData());
	}

	public function testBackendPingAnonymous(): void {
		$sessionId = 'the-session';

		$this->timeFactory->method('getTime')
			->willReturn(1234567);
		$this->sessionService->expects($this->once())
			->method('updateMultipleLastPings')
			->with([$sessionId], 1234567);

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'entries' => [
					[
						'userid' => '',
						'sessionid' => $sessionId,
					],
				],
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
			],
		], $result->getData());
	}

	public function testBackendPingMixedAndInactive(): void {
		$sessionId = 'the-session';

		$this->timeFactory->method('getTime')
			->willReturn(234567);
		$this->sessionService->expects($this->once())
			->method('updateMultipleLastPings')
			->with([$sessionId . '1', $sessionId . '2'], 234567);

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'entries' => [
					[
						'userid' => '',
						'sessionid' => $sessionId . '1',
					],
					[
						'userid' => $this->userId,
						'sessionid' => $sessionId . '2',
					],
					[
						'userid' => 'inactive',
						'sessionid' => '0',
					],
				],
			],
		]);
		$this->assertSame([
			'type' => 'room',
			'room' => [
				'version' => '1.0',
			],
		], $result->getData());
	}


	public function testLeaveRoomWithOldSession(): void {
		// Make sure that leaving a user with an old session id doesn't remove
		// the current user from the room if they re-joined in the meantime.
		$dbConnection = \OCP\Server::get(IDBConnection::class);
		$dispatcher = \OCP\Server::get(IEventDispatcher::class);
		/** @var ParticipantService $participantService */
		$participantService = \OCP\Server::get(ParticipantService::class);

		$this->manager = new Manager(
			$dbConnection,
			\OCP\Server::get(IConfig::class),
			$this->createMock(Config::class),
			\OCP\Server::get(IAppManager::class),
			\OCP\Server::get(AttendeeMapper::class),
			\OCP\Server::get(SessionMapper::class),
			$participantService,
			$this->secureRandom,
			$this->createMock(IUserManager::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(CommentsManager::class),
			$this->createMock(TalkSession::class),
			$dispatcher,
			$this->timeFactory,
			$this->createMock(IHasher::class),
			$this->createMock(IL10N::class),
			$this->authenticator,
		);
		$this->recreateSignalingController();

		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getDisplayName')
			->willReturn('Test User');
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn($this->userId);

		$room = $this->manager->createRoom(Room::TYPE_PUBLIC);
		$roomService = $this->createMock(RoomService::class);
		$roomService->method('verifyPassword')
			->willReturn(['result' => true, 'url' => '']);

		// The user joined the room.
		$oldParticipant = $participantService->joinRoom($roomService, $room, $testUser, '');
		$oldSessionId = $oldParticipant->getSession()->getSessionId();
		$this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $room->getToken(),
				'userid' => $this->userId,
				'sessionid' => $oldSessionId,
				'action' => 'join',
			],
		]);
		$participant = $participantService->getParticipant($room, $this->userId, $oldSessionId);
		$this->assertEquals($oldSessionId, $participant->getSession()->getSessionId());

		// The user is reloading the browser which will join them with another
		// session id.
		$newParticipant = $participantService->joinRoom($roomService, $room, $testUser, '');
		$newSessionId = $newParticipant->getSession()->getSessionId();
		$this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $room->getToken(),
				'userid' => $this->userId,
				'sessionid' => $newSessionId,
				'action' => 'join',
			],
		]);

		// Now the new session id is stored in the database.
		$participant = $participantService->getParticipant($room, $this->userId, $newSessionId);
		$this->assertEquals($newSessionId, $participant->getSession()->getSessionId());

		// Leaving the old session id...
		$this->performBackendRequest([
			'type' => 'room',
			'room' => [
				'roomid' => $room->getToken(),
				'userid' => $this->userId,
				'sessionid' => $oldSessionId,
				'action' => 'leave',
			],
		]);

		// ...will keep the new session id in the database.
		$participant = $participantService->getParticipant($room, $this->userId, $newSessionId);
		$this->assertEquals($newSessionId, $participant->getSession()->getSessionId());
	}
}
