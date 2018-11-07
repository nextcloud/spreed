<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Tests\php\Notifications;

use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Notification\Notifier;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\RichObjectStrings\Definitions;
use OCP\Share\IManager as ShareManager;
use PHPUnit\Framework\MockObject\MockObject;

class NotifierTest extends \Test\TestCase {

	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $lFactory;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var ShareManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $shareManager;
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;
	/** @var ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;
	/** @var MessageParser|\PHPUnit_Framework_MockObject_MockObject */
	protected $messageParser;
	/** @var Definitions|\PHPUnit_Framework_MockObject_MockObject */
	protected $definitions;
	/** @var Notifier */
	protected $notifier;

	public function setUp() {
		parent::setUp();

		$this->lFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->shareManager = $this->createMock(ShareManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->definitions = $this->createMock(Definitions::class);

		$this->notifier = new Notifier(
			$this->lFactory,
			$this->url,
			$this->userManager,
			$this->shareManager,
			$this->manager,
			$this->commentsManager,
			$this->messageParser,
			$this->definitions
		);
	}

	public function dataPrepareOne2One() {
		return [
			['admin', 'Admin', 'Admin invited you to a private conversation'],
			['test', 'Test user', 'Test user invited you to a private conversation'],
		];
	}

	/**
	 * @dataProvider dataPrepareOne2One
	 * @param string $uid
	 * @param string $displayName
	 * @param string $parsedSubject
	 */
	public function testPrepareOne2One($uid, $displayName, $parsedSubject) {
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getType')
			->willReturn(Room::ONE_TO_ONE_CALL);
		$room->expects($this->any())
			->method('getId')
			->willReturn(123);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$u = $this->createMock(IUser::class);
		$u->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn($displayName);
		$this->userManager->expects($this->once())
			->method('get')
			->with($uid)
			->willReturn($u);

		$n->expects($this->once())
			->method('setIcon')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setLink')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();
		$n->expects($this->once())
			->method('setRichSubject')
			->with('{user} invited you to a private conversation',[
				'user' => [
					'type' => 'user',
					'id' => $uid,
					'name' => $displayName,
				],
				'call' => [
					'type' => 'call',
					'id' => 123,
					'name' => 'a conversation',
					'call-type' => 'one2one'
				],
			])
			->willReturnSelf();

		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->expects($this->once())
			->method('getObjectType')
			->willReturn('room');

		$this->notifier->prepare($n, 'de');
	}

	public function dataPrepareGroup() {
		return [
			[Room::GROUP_CALL, 'admin', 'Admin', '', 'Admin invited you to a group conversation'],
			[Room::PUBLIC_CALL, 'test', 'Test user', 'Name', 'Test user invited you to a group conversation: Name'],
		];
	}

	/**
	 * @dataProvider dataPrepareGroup
	 * @param int $type
	 * @param string $uid
	 * @param string $displayName
	 * @param string $name
	 * @param string $parsedSubject
	 */
	public function testPrepareGroup($type, $uid, $displayName, $name, $parsedSubject) {
		$roomId = $type;
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->atLeastOnce())
			->method('getType')
			->willReturn($type);
		$room->expects($this->atLeastOnce())
			->method('getName')
			->willReturn($name);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$u = $this->createMock(IUser::class);
		$u->expects($this->exactly(2))
			->method('getDisplayName')
			->willReturn($displayName);
		$this->userManager->expects($this->once())
			->method('get')
			->with($uid)
			->willReturn($u);

		$n->expects($this->once())
			->method('setIcon')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setLink')
			->willReturnSelf();
		$n->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();

		$room->expects($this->once())
			->method('getId')
			->willReturn($roomId);

		if ($name === '') {
			$n->expects($this->once())
				->method('setRichSubject')
				->with('{user} invited you to a group conversation',[
					'user' => [
						'type' => 'user',
						'id' => $uid,
						'name' => $displayName,
					],
					'call' => [
						'type' => 'call',
						'id' => $roomId,
						'name' => 'a conversation',
						'call-type' => 'group',
					],
				])
				->willReturnSelf();
		} else {
			$n->expects($this->once())
				->method('setRichSubject')
				->with('{user} invited you to a group conversation: {call}', [
					'user' => [
						'type' => 'user',
						'id' => $uid,
						'name' => $displayName,
					],
					'call' => [
						'type' => 'call',
						'id' => $roomId,
						'name' => $name,
						'call-type' => 'public',
					],
				])
				->willReturnSelf();
		}

		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->expects($this->once())
			->method('getObjectType')
			->willReturn('room');

		$this->notifier->prepare($n, 'de');
	}

	public function dataPrepareChatMessage(): array {
		return [
			[
				$isMention = true, Room::ONE_TO_ONE_CALL, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user mentioned you in a private conversation',
				['{user} mentioned you in a private conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'one2one'],
					]
				],
			],
			// If the user is deleted in a one to one conversation the conversation is also
			// deleted, and that in turn would delete the pending notification.
			[
				$isMention = true, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user mentioned you in a conversation',
				['{user} mentioned you in a conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'group'],
					]
				],
			],
			[
				$isMention = true, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], null,        '',
				'A deleted user mentioned you in a conversation',
				['A deleted user mentioned you in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'group'],
					]
				],
				$deletedUser = true],
			[
				$isMention = true, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user mentioned you in conversation Room name',
				['{user} mentioned you in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'group']
					]
				],
			],
			[
				$isMention = true, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user mentioned you in conversation Room name',
				['A deleted user mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'group']
					]
				],
				$deletedUser = true],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user mentioned you in a conversation',
				['{user} mentioned you in a conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public'],
					]
				],
			],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], null,        '',
				'A deleted user mentioned you in a conversation',
				['A deleted user mentioned you in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public']
					]
				],
				$deletedUser = true],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        '',
				'A guest mentioned you in a conversation',
				['A guest mentioned you in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public']
					]
				],
			],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user mentioned you in conversation Room name',
				['{user} mentioned you in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']
					]
				],
			],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], null,    'Room name',
				'A deleted user mentioned you in conversation Room name',
				['A deleted user mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']
					]
				],
				$deletedUser = true],
			[
				$isMention = true, Room::PUBLIC_CALL,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'A guest mentioned you in conversation Room name',
				['A guest mentioned you in conversation {call}',
					['call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']]
				],
			],

			// Normal messages
			[
				$isMention = false, Room::ONE_TO_ONE_CALL, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user sent you a private message',
				['{user} sent you a private message',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'one2one'],
					]
				],
			],
			// If the user is deleted in a one to one conversation the conversation is also
			// deleted, and that in turn would delete the pending notification.
			[
				$isMention = false, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user sent a message in a conversation',
				['{user} sent a message in a conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'group'],
					]
				],
			],
			[
				$isMention = false, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], null,        '',
				'A deleted user sent a message in a conversation',
				['A deleted user sent a message in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'group'],
					]
				],
				$deletedUser = true],
			[
				$isMention = false, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user sent a message in conversation Room name',
				['{user} sent a message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'group']
					]
				],
			],
			[
				$isMention = false, Room::GROUP_CALL,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user sent a message in conversation Room name',
				['A deleted user sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'group']
					]
				],
				$deletedUser = true],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', '',
				'Test user sent a message in a conversation',
				['{user} sent a message in a conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public'],
					]
				],
			],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], null,        '',
				'A deleted user sent a message in a conversation',
				['A deleted user sent a message in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public']
					]
				],
				$deletedUser = true],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        '',
				'A guest sent a message in a conversation',
				['A guest sent a message in a conversation',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'a conversation', 'call-type' => 'public']
					]
				],
			],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user sent a message in conversation Room name',
				['{user} sent a message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']
					]
				],
			],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'users', 'userId' => 'testUser'], null,    'Room name',
				'A deleted user sent a message in conversation Room name',
				['A deleted user sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']
					]
				],
				$deletedUser = true],
			[
				$isMention = false, Room::PUBLIC_CALL,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'A guest sent a message in conversation Room name',
				['A guest sent a message in conversation {call}',
					['call' => ['type' => 'call', 'id' => 'testRoomId', 'name' => 'Room name', 'call-type' => 'public']]
				],
			]
		];
	}

	/**
	 * @dataProvider dataPrepareChatMessage
	 * @param bool $isMention
	 * @param int $roomType
	 * @param array $subjectParameters
	 * @param string $displayName
	 * @param string $roomName
	 * @param string $parsedSubject
	 * @param array $richSubject
	 * @param bool $deletedUser
	 */
	public function testPrepareChatMessage(bool $isMention, int $roomType, array $subjectParameters, $displayName, string $roomName, string $parsedSubject, array $richSubject, bool $deletedUser = false) {
		/** @var INotification|MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->atLeastOnce())
			->method('getType')
			->willReturn($roomType);
		$room->expects($this->any())
			->method('getId')
			->willReturn('testRoomId');
		$room->expects($this->atLeastOnce())
			->method('getName')
			->willReturn($roomName);
		if ($roomName !== '') {
			$room->expects($this->atLeastOnce())
				->method('getId')
				->willReturn('testRoomId');
		}
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$user = $this->createMock(IUser::class);
		if ($subjectParameters['userType'] === 'users' && !$deletedUser) {
			$user->expects($this->once())
				->method('getDisplayName')
				->willReturn($displayName);
			$this->userManager->expects($this->at(0))
				->method('get')
				->with($subjectParameters['userId'])
				->willReturn($user);
			$recipient = $this->createMock(IUser::class);
			$this->userManager->expects($this->at(1))
				->method('get')
				->with('recipient')
				->willReturn($recipient);
		} else if ($subjectParameters['userType'] === 'users' && $deletedUser) {
			$user->expects($this->never())
				->method('getDisplayName');
			$this->userManager->expects($this->at(0))
				->method('get')
				->with($subjectParameters['userId'])
				->willReturn(null);

			$recipient = $this->createMock(IUser::class);
			$this->userManager->expects($this->at(1))
				->method('get')
				->with('recipient')
				->willReturn($recipient);
		} else {
			$user->expects($this->never())
				->method('getDisplayName');

			$recipient = $this->createMock(IUser::class);
			$this->userManager->expects($this->once())
				->method('get')
				->with('recipient')
				->willReturn($recipient);
		}

		$comment = $this->createMock(IComment::class);
		$this->commentsManager->expects($this->once())
			->method('get')
			->with('23')
			->willReturn($comment);
		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($room, $comment)
			->willReturn(['Hi {mention-user1}', [
				'mention-user1' => [
					'type' => 'user',
					'id' => 'admin',
					'name' => 'Administrator',
				],
			]]);

		$notification->expects($this->once())
			->method('setIcon')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setLink')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($parsedSubject)
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setRichSubject')
			->with($richSubject[0], $richSubject[1])
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('Hi @Administrator')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('getUser')
			->willReturn('recipient');
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$notification->expects($this->exactly(2))
			->method('getSubject')
			->willReturn($isMention ? 'mention'  : 'chat');
		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn($subjectParameters);
		$notification->expects($this->once())
			->method('getObjectType')
			->willReturn('chat');
		$notification->expects($this->once())
			->method('getMessageParameters')
			->willReturn(['commentId' => '23']);

		$this->assertEquals($notification, $this->notifier->prepare($notification, 'de'));
	}

	public function dataPrepareThrows() {
		return [
			['Incorrect app', 'invalid-app', null, null, null, null],
			['Invalid room', 'spreed', false, null, null, null],
			['Unknown subject', 'spreed', true, 'invalid-subject', null, null],
			['Unknown object type', 'spreed', true, 'invitation', null, 'invalid-object-type'],
			['Calling user does not exist anymore', 'spreed', true, 'invitation', ['admin'], 'room'],
			['Unknown object type', 'spreed', true, 'mention', null, 'invalid-object-type'],
		];
	}

	/**
	 * @dataProvider dataPrepareThrows
	 *
	 * @expectedException \InvalidArgumentException
	 *
	 * @param string $message
	 * @param string $app
	 * @param bool|null $validRoom
	 * @param string|null $subject
	 * @param array|null $params
	 * @param string|null $objectType
	 */
	public function testPrepareThrows($message, $app, $validRoom, $subject, $params, $objectType) {
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);

		if ($validRoom === null) {
			$this->manager->expects($this->never())
				->method('getRoomByToken');
		} else if ($validRoom === true) {
			$room = $this->createMock(Room::class);
			$room->expects($this->never())
				->method('getType');
			$this->manager->expects($this->once())
				->method('getRoomByToken')
				->willReturn($room);
		} else if ($validRoom === false) {
			$this->manager->expects($this->once())
				->method('getRoomByToken')
				->willThrowException(new RoomNotFoundException());
			$this->manager->expects($this->once())
				->method('getRoomById')
				->willThrowException(new RoomNotFoundException());
		}

		$this->lFactory->expects($validRoom === null ? $this->never() : $this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$n->expects($validRoom !== true ? $this->never() : $this->once())
			->method('setIcon')
			->willReturnSelf();
		$n->expects($validRoom !== true ? $this->never() : $this->once())
			->method('setLink')
			->willReturnSelf();

		$n->expects($this->once())
			->method('getApp')
			->willReturn($app);
		$n->expects($subject === null ? $this->never() : $this->atLeastOnce())
			->method('getSubject')
			->willReturn($subject);
		$n->expects($params === null ? $this->never() : $this->once())
			->method('getSubjectParameters')
			->willReturn($params);
		$n->expects($objectType === null ? $this->never() : $this->once())
			->method('getObjectType')
			->willReturn($objectType);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->notifier->prepare($n, 'de');
	}
}
