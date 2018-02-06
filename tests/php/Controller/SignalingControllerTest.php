<?php
/**
 *
 * @copyright Copyright (c) 2018, Joachim Bauch (bauch@struktur.de)
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

namespace OCA\Spreed\Tests\php\Controller;

use OCA\Spreed\Config;
use OCA\Spreed\Controller\SignalingController;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\Signaling\Messages;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

class CustomInputSignalingController extends SignalingController {

	private $inputStream;

	public function setInputStream($data) {
		$this->inputStream = $data;
	}

	protected function getInputStream() {
		return $this->inputStream;
	}

}

/**
 * @group DB
 */
class SignalingControllerTest extends \Test\TestCase {

	/** @var OCA\Spreed\Config */
	private $config;

	/** @var \OCP\ISession|\PHPUnit_Framework_MockObject_MockObject */
	private $session;

	/** @var \OCA\Spreed\Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;

	/** @var \OCP\IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	protected $dbConnection;

	/** @var \OCA\Spreed\Signaling\Messages|\PHPUnit_Framework_MockObject_MockObject */
	protected $messages;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var string */
	private $userId;

	/** @var CustomInputSignalingController */
	private $controller;

	public function setUp() {
		parent::setUp();

		$this->userId = 'testUser';
		$secureRandom = \OC::$server->getSecureRandom();
		$timeFactory = $this->createMock(ITimeFactory::class);
		$config = \OC::$server->getConfig();
		$config->setAppValue('spreed', 'signaling_servers', json_encode([
			'secret' => 'MySecretValue',
		]));
		$config->setAppValue('spreed', 'signaling_ticket_secret', 'the-app-ticket-secret');
		$config->setUserValue($this->userId, 'spreed', 'signaling_ticket_secret', 'the-user-ticket-secret');
		$this->config = new Config($config, $secureRandom, $timeFactory);
		$this->session = $this->createMock(ISession::class);
		$this->dbConnection = \OC::$server->getDatabaseConnection();
		$this->manager = $this->createMock(Manager::class);
		$this->messages = $this->createMock(Messages::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->recreateSignalingController();
	}

	private function recreateSignalingController() {
		$this->controller = new CustomInputSignalingController(
			'spreed',
			$this->createMock(\OCP\IRequest::class),
			$this->config,
			$this->session,
			$this->manager,
			$this->dbConnection,
			$this->messages,
			$this->userManager,
			$this->userId
		);
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

	public function testBackendChecksums() {
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

	public function testBackendChecksumValidation() {
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

	public function testBackendUnsupportedType() {
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

	public function testBackendAuth() {
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
					'ticket' => $this->config->getSignalingTicket($this->userId),
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
					'ticket' => $this->config->getSignalingTicket('unknown-userid'),
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
					'ticket' => $this->config->getSignalingTicket($this->userId),
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
					'ticket' => $this->config->getSignalingTicket(''),
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

	public function testBackendRoomUnknown() {
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

	public function testBackendRoomInvited() {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$room->expects($this->once())
			->method('getName')
			->willReturn($roomName);
		$room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getType')
			->willReturn(Room::ONE_TO_ONE_CALL);

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
					'type' => Room::ONE_TO_ONE_CALL,
				],
			],
		], $result->getData());
	}

	public function testBackendRoomAnonymousPublic() {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$room->expects($this->once())
			->method('getName')
			->willReturn($roomName);
		$room->expects($this->once())
			->method('getParticipantBySession')
			->with($sessionId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getType')
			->willReturn(Room::PUBLIC_CALL);

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
					'type' => Room::PUBLIC_CALL,
				],
			],
		], $result->getData());
	}

	public function testBackendRoomInvitedPublic() {
		$roomToken = 'the-room';
		$roomName = 'the-room-name';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$room->expects($this->once())
			->method('getName')
			->willReturn($roomName);
		$room->expects($this->once())
			->method('getParticipant')
			->with($this->userId)
			->willThrowException(new ParticipantNotFoundException());
		$room->expects($this->once())
			->method('getParticipantBySession')
			->with($sessionId)
			->willReturn($participant);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('getType')
			->willReturn(Room::PUBLIC_CALL);

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
					'type' => Room::PUBLIC_CALL,
				],
			],
		], $result->getData());
	}

	public function testBackendRoomAnonymousOneToOne() {
		$roomToken = 'the-room';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$room->expects($this->once())
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

	public function testBackendPingUnknownRoom() {
		$roomToken = 'the-room';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willThrowException(new RoomNotFoundException());

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'roomid' => $roomToken,
				'entries' => [
					[
						'userid' => $this->userId,
					],
				],
			],
		]);
		$this->assertSame([
			'type' => 'error',
			'error' => [
				'code' => 'no_such_room',
				'message' => 'No such room.',
			],
		], $result->getData());
	}

	public function testBackendPingUser() {
		$roomToken = 'the-room';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('ping')
			->with($this->userId, $sessionId);

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'roomid' => $roomToken,
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
				'roomid' => $roomToken,
			],
		], $result->getData());
	}

	public function testBackendPingAnonymous() {
		$roomToken = 'the-room';
		$sessionId = 'the-session';
		$room = $this->createMock(Room::class);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with($roomToken)
			->willReturn($room);
		$room->expects($this->once())
			->method('getToken')
			->willReturn($roomToken);
		$room->expects($this->once())
			->method('ping')
			->with('', $sessionId);

		$result = $this->performBackendRequest([
			'type' => 'ping',
			'ping' => [
				'roomid' => $roomToken,
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
				'roomid' => $roomToken,
			],
		], $result->getData());
	}

}
