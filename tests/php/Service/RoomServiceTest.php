<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Tests\php\Service;

use InvalidArgumentException;
use OC\EventDispatcher\EventDispatcher;
use OCA\Talk\Config;
use OCA\Talk\Events\RoomPasswordVerifyEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class RoomServiceTest extends TestCase {
	/** @var Manager|MockObject */
	protected $manager;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IShareManager|MockObject */
	protected $shareManager;
	/** @var Config|MockObject */
	protected $config;
	/** @var IHasher|MockObject */
	protected $hasher;
	/** @var IEventDispatcher|MockObject */
	protected $dispatcher;
	private ?RoomService $service = null;
	/** @var IJobList|MockObject */
	private IJobList $jobList;

	public function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->config = $this->createMock(Config::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->service = new RoomService(
			$this->manager,
			$this->participantService,
			\OC::$server->get(IDBConnection::class),
			$this->timeFactory,
			$this->shareManager,
			$this->config,
			$this->hasher,
			$this->dispatcher,
			$this->jobList
		);
	}

	public function testCreateOneToOneConversationWithSameUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('uid');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('invalid_invitee');
		$this->service->createOneToOneConversation($user, $user);
	}

	public function testCreateOneToOneConversationWithNotCurrentUserCanEnumerateTargetUser(): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('uid1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('uid2');

		$this->expectException(RoomNotFoundException::class);
		$this->shareManager
			->expects($this->once())
			->method('currentUserCanEnumerateTargetUser')
			->willReturn(false);
		$this->manager
			->method('getOne2OneRoom')
			->willThrowException(new RoomNotFoundException());
		$this->service->createOneToOneConversation($user1, $user2);
	}

	public function testCreateOneToOneConversationAlreadyExists(): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('uid1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('uid2');

		$room = $this->createMock(Room::class);
		$this->participantService->expects($this->once())
			->method('ensureOneToOneRoomIsFilled')
			->with($room);

		$this->manager->expects($this->once())
			->method('getOne2OneRoom')
			->with('uid1', 'uid2')
			->willReturn($room);

		$this->assertSame($room, $this->service->createOneToOneConversation($user1, $user2));
	}

	public function testCreateOneToOneConversationCreated(): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('uid1');
		$user1->method('getDisplayName')
			->willReturn('display-1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('uid2');
		$user2->method('getDisplayName')
			->willReturn('display-2');

		$this->shareManager
			->expects($this->once())
			->method('currentUserCanEnumerateTargetUser')
			->willReturn(true);

		$room = $this->createMock(Room::class);
		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($room, [[
				'actorType' => 'users',
				'actorId' => 'uid1',
				'displayName' => 'display-1',
				'participantType' => Participant::OWNER,
			], [
				'actorType' => 'users',
				'actorId' => 'uid2',
				'displayName' => 'display-2',
				'participantType' => Participant::OWNER,
			]]);

		$this->participantService->expects($this->never())
			->method('ensureOneToOneRoomIsFilled')
			->with($room);

		$this->manager->expects($this->once())
			->method('getOne2OneRoom')
			->with('uid1', 'uid2')
			->willThrowException(new RoomNotFoundException());

		$this->manager->expects($this->once())
			->method('createRoom')
			->with(Room::TYPE_ONE_TO_ONE)
			->willReturn($room);

		$this->assertSame($room, $this->service->createOneToOneConversation($user1, $user2));
	}

	public static function dataCreateConversationInvalidNames(): array {
		return [
			[''],
			['        '],
			[str_repeat('a', 256)],
			// Isn't a multibyte emoji
			[str_repeat('😃', 256)],
			// This is a multibyte emoji and need 2 chars in database
			// 256 / 2 = 128
			[str_repeat('‍💻', 128)],
		];
	}

	/**
	 * @dataProvider dataCreateConversationInvalidNames
	 * @param string $name
	 */
	public function testCreateConversationInvalidNames(string $name): void {
		$this->manager->expects($this->never())
			->method('createRoom');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('name');
		$this->service->createConversation(Room::TYPE_GROUP, $name);
	}

	public static function dataCreateConversationInvalidTypes(): array {
		return [
			[Room::TYPE_ONE_TO_ONE],
			[Room::TYPE_UNKNOWN],
			[Room::TYPE_ONE_TO_ONE_FORMER],
			[7],
		];
	}

	/**
	 * @dataProvider dataCreateConversationInvalidTypes
	 * @param int $type
	 */
	public function testCreateConversationInvalidTypes(int $type): void {
		$this->manager->expects($this->never())
			->method('createRoom');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('type');
		$this->service->createConversation($type, 'abc');
	}

	public static function dataCreateConversationInvalidObjects(): array {
		return [
			[str_repeat('a', 65), 'a', 'object_type'],
			['a', str_repeat('a', 65), 'object_id'],
			['a', '', 'object'],
			['', 'b', 'object'],
		];
	}

	/**
	 * @dataProvider dataCreateConversationInvalidObjects
	 * @param string $type
	 * @param string $id
	 * @param string $exception
	 */
	public function testCreateConversationInvalidObjects(string $type, string $id, string $exception): void {
		$this->manager->expects($this->never())
			->method('createRoom');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($exception);
		$this->service->createConversation(Room::TYPE_PUBLIC, 'a', null, $type, $id);
	}

	public static function dataCreateConversation(): array {
		return [
			[Room::TYPE_GROUP, 'Group conversation', 'admin', '', ''],
			[Room::TYPE_PUBLIC, 'Public conversation', '', 'files', '123456'],
			[Room::TYPE_CHANGELOG, 'Talk updates ✅', 'test1', 'changelog', 'conversation'],
		];
	}

	/**
	 * @dataProvider dataCreateConversation
	 * @param int $type
	 * @param string $name
	 * @param string $ownerId
	 * @param string $objectType
	 * @param string $objectId
	 */
	public function testCreateConversation(int $type, string $name, string $ownerId, string $objectType, string $objectId): void {
		$room = $this->createMock(Room::class);

		if ($ownerId !== '') {
			$owner = $this->createMock(IUser::class);
			$owner->method('getUID')
				->willReturn($ownerId);
			$owner->method('getDisplayName')
				->willReturn($ownerId . '-display');

			$this->participantService->expects($this->once())
				->method('addUsers')
				->with($room, [[
					'actorType' => 'users',
					'actorId' => $ownerId,
					'displayName' => $ownerId . '-display',
					'participantType' => Participant::OWNER,
				]]);
		} else {
			$owner = null;
			$this->participantService->expects($this->never())
				->method('addUsers');
		}

		$this->manager->expects($this->once())
			->method('createRoom')
			->with($type, $name, $objectType, $objectId)
			->willReturn($room);

		$this->assertSame($room, $this->service->createConversation($type, $name, $owner, $objectType, $objectId));
	}

	public static function dataPrepareConversationName(): array {
		return [
			['', ''],
			['    ', ''],
			['A    ', 'A'],
			['    B', 'B'],
			['  C  ', 'C'],
			['A' . str_repeat(' ', 100) . 'B', 'A'],
			['A' . str_repeat(' ', 32) . 'B', 'A' . str_repeat(' ', 32) . 'B'],
			['Лорем ипсум долор сит амет, но антиопам алияуандо витуперата еам, мел те цонгуе хомеро адолесценс.', 'Лорем ипсум долор сит амет, но антиопам алияуандо витуперата еам'],
		];
	}

	/**
	 * @dataProvider dataPrepareConversationName
	 * @param string $input
	 * @param string $expected
	 */
	public function testPrepareConversationName(string $input, string $expected): void {
		$this->assertSame($expected, $this->service->prepareConversationName($input));
	}

	public function testVerifyPassword(): void {
		$dispatcher = new EventDispatcher(
			new \Symfony\Component\EventDispatcher\EventDispatcher(),
			\OC::$server,
			$this->createMock(LoggerInterface::class)
		);
		$dispatcher->addListener(RoomPasswordVerifyEvent::class, static function (RoomPasswordVerifyEvent $event) {
			$password = $event->getPassword();

			if ($password === '1234') {
				$event->setIsPasswordValid(true);
				$event->setRedirectUrl('');
			} else {
				$event->setIsPasswordValid(false);
				$event->setRedirectUrl('https://test');
			}
		});

		$service = new RoomService(
			$this->manager,
			$this->participantService,
			\OC::$server->get(IDBConnection::class),
			$this->timeFactory,
			$this->shareManager,
			$this->config,
			$this->hasher,
			$dispatcher,
			$this->jobList
		);

		$room = new Room(
			$this->createMock(Manager::class),
			$this->createMock(IDBConnection::class),
			$dispatcher,
			$this->createMock(ITimeFactory::class),
			1,
			Room::TYPE_PUBLIC,
			Room::READ_WRITE,
			Room::LISTABLE_NONE,
			0,
			Webinary::LOBBY_NONE,
			Webinary::SIP_DISABLED,
			null,
			'foobar',
			'Test',
			'description',
			'passy',
			'',
			'',
			'',
			0,
			Attendee::PERMISSIONS_DEFAULT,
			Attendee::PERMISSIONS_DEFAULT,
			Participant::FLAG_DISCONNECTED,
			null,
			null,
			0,
			null,
			null,
			'',
			'',
			BreakoutRoom::MODE_NOT_CONFIGURED,
			BreakoutRoom::STATUS_STOPPED,
			Room::RECORDING_NONE,
			RecordingService::CONSENT_REQUIRED_NO,
			Room::HAS_FEDERATION_NONE,
		);

		$verificationResult = $service->verifyPassword($room, '1234');
		$this->assertSame($verificationResult, ['result' => true, 'url' => '']);
		$verificationResult = $service->verifyPassword($room, '4321');
		$this->assertSame($verificationResult, ['result' => false, 'url' => 'https://test']);
		$this->assertSame('passy', $room->getPassword());
	}
}
