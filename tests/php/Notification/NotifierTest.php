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

namespace OCA\Talk\Tests\php\Notification;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Notification\Notifier;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Federation\ICloudIdManager;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\RichObjectStrings\Definitions;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class NotifierTest extends TestCase {
	/** @var IFactory|MockObject */
	protected $lFactory;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var Config|MockObject */
	protected $config;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var IShareManager|MockObject */
	protected $shareManager;
	/** @var Manager|MockObject */
	protected $manager;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var AvatarService|MockObject */
	protected $avatarService;
	/** @var INotificationManager|MockObject */
	protected $notificationManager;
	/** @var CommentsManager|MockObject */
	protected $commentsManager;
	protected ProxyCacheMessageMapper|MockObject $proxyCacheMessageMapper;
	/** @var MessageParser|MockObject */
	protected $messageParser;
	/** @var IRootFolder|MockObject */
	protected $rootFolder;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var Definitions|MockObject */
	protected $definitions;
	protected ?Notifier $notifier = null;
	/** @var AddressHandler|MockObject */
	protected $addressHandler;
	/** @var BotServerMapper|MockObject */
	protected $botServerMapper;
	/** @var FederationManager|MockObject */
	protected $federationManager;
	protected LoggerInterface|MockObject $logger;
	protected ICloudIdManager|MockObject $cloudIdManager;

	public function setUp(): void {
		parent::setUp();

		$this->lFactory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(Config::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->proxyCacheMessageMapper = $this->createMock(ProxyCacheMessageMapper::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->definitions = $this->createMock(Definitions::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->botServerMapper = $this->createMock(BotServerMapper::class);
		$this->federationManager = $this->createMock(FederationManager::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->notifier = new Notifier(
			$this->lFactory,
			$this->url,
			$this->config,
			$this->userManager,
			$this->groupManager,
			$this->guestManager,
			$this->shareManager,
			$this->manager,
			$this->participantService,
			$this->avatarService,
			$this->notificationManager,
			$this->commentsManager,
			$this->proxyCacheMessageMapper,
			$this->messageParser,
			$this->rootFolder,
			$this->timeFactory,
			$this->definitions,
			$this->addressHandler,
			$this->botServerMapper,
			$this->federationManager,
			$this->cloudIdManager,
			$this->logger,
		);
	}

	public static function dataPrepareOne2One(): array {
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
	public function testPrepareOne2One(string $uid, string $displayName, string $parsedSubject): void {
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getType')
			->willReturn(Room::TYPE_ONE_TO_ONE);
		$room->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$room->expects($this->any())
			->method('getDisplayName')
			->with('recipient')
			->willReturn($displayName);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with('roomToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$recipient = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('recipient')
			->willReturn($recipient);

		$this->userManager->expects($this->once())
			->method('getDisplayName')
			->with($uid)
			->willReturn($displayName);

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
			->with('{user} invited you to a private conversation', [
				'user' => [
					'type' => 'user',
					'id' => $uid,
					'name' => $displayName,
				],
				'call' => [
					'type' => 'call',
					'id' => 1234,
					'name' => $displayName,
					'call-type' => 'one2one',
					'icon-url' => '',
				],
			])
			->willReturnSelf();

		$n->expects($this->exactly(2))
			->method('getUser')
			->willReturn('recipient');
		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->method('getObjectType')
			->willReturn('room');
		$n->method('getObjectId')
			->willReturn('roomToken');

		$this->notifier->prepare($n, 'de');
	}

	/**
	 * @dataProvider dataPrepareOne2One
	 * @param string $uid
	 * @param string $displayName
	 * @param string $parsedSubject
	 */
	public function testPreparingMultipleTimesOnlyGetsTheRoomOnce($uid, $displayName, $parsedSubject) {
		$numNotifications = 4;

		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getType')
			->willReturn(Room::TYPE_ONE_TO_ONE);
		$room->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$room->expects($this->any())
			->method('getDisplayName')
			->with('recipient')
			->willReturn($displayName);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with('roomToken')
			->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'recipient')
			->willReturn($participant);

		$this->lFactory->expects($this->exactly($numNotifications))
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$recipient = $this->createMock(IUser::class);
		$this->userManager->expects($this->any())
			->method('get')
			->with('recipient')
			->willReturn($recipient);

		$this->userManager->expects($this->any())
			->method('getDisplayName')
			->with($uid)
			->willReturn($displayName);

		$n = $this->getNotificationMock($parsedSubject, $uid, $displayName);
		$this->notifier->prepare($n, 'de');
		$n = $this->getNotificationMock($parsedSubject, $uid, $displayName);
		$this->notifier->prepare($n, 'de');
		$n = $this->getNotificationMock($parsedSubject, $uid, $displayName);
		$this->notifier->prepare($n, 'de');
		$n = $this->getNotificationMock($parsedSubject, $uid, $displayName);
		$this->notifier->prepare($n, 'de');
	}

	public function getNotificationMock(string $parsedSubject, string $uid, string $displayName) {
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
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
			->with('{user} invited you to a private conversation', [
				'user' => [
					'type' => 'user',
					'id' => $uid,
					'name' => $displayName,
				],
				'call' => [
					'type' => 'call',
					'id' => 1234,
					'name' => $displayName,
					'call-type' => 'one2one',
					'icon-url' => '',
				],
			])
			->willReturnSelf();

		$n->expects($this->exactly(2))
			->method('getUser')
			->willReturn('recipient');
		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->method('getObjectType')
			->willReturn('room');
		$n->method('getObjectId')
			->willReturn('roomToken');

		return $n;
	}

	public static function dataPrepareGroup() {
		return [
			[Room::TYPE_GROUP, 'admin', 'Admin', 'Group', 'Admin invited you to a group conversation: Group'],
			[Room::TYPE_PUBLIC, 'test', 'Test user', 'Public', 'Test user invited you to a group conversation: Public'],
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
			->will($this->returnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$room = $this->createMock(Room::class);
		$room->expects($this->atLeastOnce())
			->method('getType')
			->willReturn($type);
		$room->expects($this->atLeastOnce())
			->method('getDisplayName')
			->with('recipient')
			->willReturn($name);
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with('roomToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$recipient = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('recipient')
			->willReturn($recipient);

		$this->userManager->expects($this->once())
			->method('getDisplayName')
			->with($uid)
			->willReturn($displayName);

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

		$room->expects($this->exactly(2))
			->method('getId')
			->willReturn($roomId);

		$this->avatarService->method('getAvatarUrl')
			->with($room)
			->willReturn('getAvatarUrl');

		if ($type === Room::TYPE_GROUP) {
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
						'call-type' => 'group',
						'icon-url' => 'getAvatarUrl',
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
						'icon-url' => 'getAvatarUrl',
					],
				])
				->willReturnSelf();
		}

		$n->expects($this->exactly(2))
			->method('getUser')
			->willReturn('recipient');
		$n->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$n->expects($this->once())
			->method('getSubject')
			->willReturn('invitation');
		$n->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$uid]);
		$n->method('getObjectType')
			->willReturn('room');
		$n->method('getObjectId')
			->willReturn('roomToken');

		$this->notifier->prepare($n, 'de');
	}

	public static function dataPrepareChatMessage(): array {
		return [
			'one-to-one mention' => [
				$subject = 'mention', Room::TYPE_ONE_TO_ONE, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Test user',
				'Test user mentioned you in a private conversation',
				[
					'{user} mentioned you in a private conversation',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Test user', 'call-type' => 'one2one', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'user mention' => [
				$subject = 'mention', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user mentioned you in conversation Room name',
				[
					'{user} mentioned you in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'deleted user mention' => [
				$subject = 'mention', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user mentioned you in conversation Room name',
				[
					'A deleted user mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true,
			],
			'user mention public' => [
				$subject = 'mention', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user mentioned you in conversation Room name',
				[
					'{user} mentioned you in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'deleted user mention public' => [
				$subject = 'mention', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user mentioned you in conversation Room name',
				[
					'A deleted user mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true,
			],
			'guest mention' => [
				$subject = 'mention', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        'Room name',
				'A guest mentioned you in conversation Room name',
				[
					'A guest mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = null,
			],
			'named guest mention' => [
				$subject = 'mention', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'MyNameIs (guest) mentioned you in conversation Room name',
				[
					'{guest} (guest) mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'guest' => ['type' => 'guest', 'id' => 'random-hash', 'name' => 'MyNameIs'],
					]
				],
				$deletedUser = false, $guestName = 'MyNameIs',
			],
			'empty named guest mention' => [
				$subject = 'mention', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'A guest mentioned you in conversation Room name',
				[
					'A guest mentioned you in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = '',
			],

			// Normal messages
			'one-to-one message' => [
				$subject = 'chat', Room::TYPE_ONE_TO_ONE, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Test user',
				'Test user sent you a private message',
				[
					'{user} sent you a private message',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Test user', 'call-type' => 'one2one', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'user message' => [
				$subject = 'chat', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user sent a message in conversation Room name',
				[
					'{user} sent a message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'deleted user message' => [
				$subject = 'chat', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user sent a message in conversation Room name',
				[
					'A deleted user sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true,
			],
			'user message public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user sent a message in conversation Room name',
				[
					'{user} sent a message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					]
				],
			],
			'deleted user message public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user sent a message in conversation Room name',
				[
					'A deleted user sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true
			],
			'guest message' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        'Room name',
				'A guest sent a message in conversation Room name',
				['A guest sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = null,
			],
			'named guest message' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'MyNameIs (guest) sent a message in conversation Room name',
				[
					'{guest} (guest) sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'guest' => ['type' => 'guest', 'id' => 'random-hash', 'name' => 'MyNameIs'],
					],
				],
				$deletedUser = false, $guestName = 'MyNameIs',
			],
			'empty named guest message' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'A guest sent a message in conversation Room name',
				[
					'A guest sent a message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = '',
			],

			// Reply
			'one-to-one reply' => [
				$subject = 'reply', Room::TYPE_ONE_TO_ONE, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Test user',
				'Test user replied to your private message',
				[
					'{user} replied to your private message',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Test user', 'call-type' => 'one2one', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'user reply' => [
				$subject = 'reply', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user replied to your message in conversation Room name',
				[
					'{user} replied to your message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
			],
			'deleted user reply' => [
				$subject = 'reply', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user replied to your message in conversation Room name',
				[
					'A deleted user replied to your message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true,
			],
			'user message reply' => [
				$subject = 'reply', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user replied to your message in conversation Room name',
				[
					'{user} replied to your message in conversation {call}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					]
				],
			],
			'deleted user message reply' => [
				$subject = 'reply', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'A deleted user replied to your message in conversation Room name',
				[
					'A deleted user replied to your message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = true
			],
			'guest reply' => [
				$subject = 'reply', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        'Room name',
				'A guest replied to your message in conversation Room name',
				['A guest replied to your message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = null,
			],
			'named guest reply' => [
				$subject = 'reply', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'MyNameIs (guest) replied to your message in conversation Room name',
				[
					'{guest} (guest) replied to your message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'guest' => ['type' => 'guest', 'id' => 'random-hash', 'name' => 'MyNameIs'],
					],
				],
				$deletedUser = false, $guestName = 'MyNameIs',
			],
			'empty named guest reply' => [
				$subject = 'reply', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'A guest replied to your message in conversation Room name',
				[
					'A guest replied to your message in conversation {call}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
					],
				],
				$deletedUser = false, $guestName = '',
			],

			// Push messages
			'one-to-one push' => [
				$subject = 'chat', Room::TYPE_ONE_TO_ONE, ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Test user',
				'Test user' . "\n" . 'Hi @Administrator',
				[
					'{user}' . "\n" . '{message}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Test user', 'call-type' => 'one2one', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = null, $isPushNotification = true,
			],
			'user push' => [
				$subject = 'chat', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user in Room name' . "\n" . 'Hi @Administrator',
				[
					'{user} in {call}' . "\n" . '{message}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = null, $isPushNotification = true,
			],
			'deleted user push' => [
				$subject = 'chat', Room::TYPE_GROUP,      ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'Deleted user in Room name' . "\n" . 'Hi @Administrator',
				[
					'Deleted user in {call}' . "\n" . '{message}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'group', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = true, $guestName = null, $isPushNotification = true,
			],
			'user push public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], 'Test user', 'Room name',
				'Test user in Room name' . "\n" . 'Hi @Administrator',
				[
					'{user} in {call}' . "\n" . '{message}',
					[
						'user' => ['type' => 'user', 'id' => 'testUser', 'name' => 'Test user'],
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = null, $isPushNotification = true,
			],
			'deleted user push public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'users', 'userId' => 'testUser'], null,        'Room name',
				'Deleted user in Room name' . "\n" . 'Hi @Administrator',
				[
					'Deleted user in {call}' . "\n" . '{message}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = true, $guestName = null, $isPushNotification = true,
			],
			'guest push public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,        'Room name',
				'Guest in Room name' . "\n" . 'Hi @Administrator',
				[
					'Guest in {call}' . "\n" . '{message}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = null, $isPushNotification = true,
			],
			'named guest push public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'MyNameIs (guest) in Room name' . "\n" . 'Hi @Administrator',
				[
					'{guest} (guest) in {call}' . "\n" . '{message}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'guest' => ['type' => 'guest', 'id' => 'random-hash', 'name' => 'MyNameIs'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = 'MyNameIs', $isPushNotification = true,
			],
			'empty named guest push public' => [
				$subject = 'chat', Room::TYPE_PUBLIC,     ['userType' => 'guests', 'userId' => 'testSpreedSession'], null,    'Room name',
				'Guest in Room name' . "\n" . 'Hi @Administrator',
				[
					'Guest in {call}' . "\n" . '{message}',
					[
						'call' => ['type' => 'call', 'id' => 1234, 'name' => 'Room name', 'call-type' => 'public', 'icon-url' => 'getAvatarUrl'],
						'message' => ['type' => 'highlight', 'id' => '123456789', 'name' => 'Hi @Administrator'],
					],
				],
				$deletedUser = false, $guestName = '', $isPushNotification = true,
			],
		];
	}

	/**
	 * @dataProvider dataPrepareChatMessage
	 * @param string $subject
	 * @param int $roomType
	 * @param array $subjectParameters
	 * @param string $displayName
	 * @param string $roomName
	 * @param string $parsedSubject
	 * @param array $richSubject
	 * @param bool $deletedUser
	 * @param null|string $guestName
	 */
	public function testPrepareChatMessage(string $subject, int $roomType, array $subjectParameters, $displayName, string $roomName, string $parsedSubject, array $richSubject, bool $deletedUser = false, ?string $guestName = null, bool $isPushNotification = false) {
		/** @var INotification|MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->will($this->returnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			}));

		$this->notificationManager->method('isPreparingPushNotification')
			->willReturn($isPushNotification);

		$room = $this->createMock(Room::class);
		$room->expects($this->atLeastOnce())
			->method('getType')
			->willReturn($roomType);
		$room->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$room->expects($this->atLeastOnce())
			->method('getDisplayName')
			->with('recipient')
			->willReturn($roomName);

		$this->avatarService->method('getAvatarUrl')
			->with($room)
			->willReturn('getAvatarUrl');

		$participant = $this->createMock(Participant::class);
		$this->participantService->expects($this->once())
			->method('getParticipant')
			->with($room, 'recipient')
			->willReturn($participant);

		if ($roomName !== '') {
			$room->expects($this->atLeastOnce())
				->method('getId')
				->willReturn(1234);
		}
		$this->manager->expects($this->once())
			->method('getRoomByToken')
			->with('roomToken')
			->willReturn($room);

		$this->lFactory->expects($this->once())
			->method('get')
			->with('spreed', 'de')
			->willReturn($l);

		$recipient = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('recipient')
			->willReturn($recipient);

		$userManagerGet = [
			'with' => [],
			'willReturn' => [],
		];
		if ($subjectParameters['userType'] === 'users' && !$deletedUser) {
			$userManagerGet['with'][] = [$subjectParameters['userId']];
			$userManagerGet['willReturn'][] = $displayName;
		} elseif ($subjectParameters['userType'] === 'users' && $deletedUser) {
			$userManagerGet['with'][] = [$subjectParameters['userId']];
			$userManagerGet['willReturn'][] = null;
		}
		$i = 0;
		$this->userManager->expects($this->exactly(count($userManagerGet['with'])))
			->method('getDisplayName')
			->willReturnCallback(function () use ($userManagerGet, &$i) {
				Assert::assertArrayHasKey($i, $userManagerGet['with']);
				Assert::assertSame($userManagerGet['with'][$i], func_get_args());
				$i++;
				return $userManagerGet['willReturn'][$i - 1];
			});

		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getActorId')
			->willReturn('random-hash');
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('chat');
		$comment->expects($this->any())
			->method('getObjectId')
			->willReturn('1234');
		$this->commentsManager->expects($this->once())
			->method('get')
			->with('23')
			->willReturn($comment);

		if (is_string($guestName)) {
			$this->participantService->method('getParticipantByActor')
				->with($room, Attendee::ACTOR_GUESTS, 'random-hash')
				->willReturn($participant);

			$attendee = Attendee::fromRow([
				'actor_type' => 'guests',
				'actor_id' => 'random-hash',
				'display_name' => $guestName,
			]);
			$participant->method('getAttendee')
				->willReturn($attendee);
		} else {
			$this->participantService->method('getParticipantByActor')
				->with($room, Attendee::ACTOR_GUESTS, 'random-hash')
				->willThrowException(new ParticipantNotFoundException());
		}

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->atLeastOnce())
			->method('getMessage')
			->willReturn('Hi {mention-user1}');
		$chatMessage->expects($this->atLeastOnce())
			->method('getMessageParameters')
			->willReturn([
				'mention-user1' => [
					'type' => 'user',
					'id' => 'admin',
					'name' => 'Administrator',
				],
			]);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->method('getComment')
			->willReturn($comment);
		$chatMessage->expects($this->any())
			->method('getMessageId')
			->willReturn(123456789);
		$chatMessage->expects($this->any())
			->method('getActorId')
			->willReturn('random-hash');

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($room, $participant, $comment, $l)
			->willReturn($chatMessage);
		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$notification->expects($this->once())
			->method('setIcon')
			->willReturnSelf();
		$notification->expects($this->exactly(2))
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
		if ($isPushNotification) {
			$notification->expects($this->never())
				->method('setParsedMessage');
		} else {
			$notification->expects($this->once())
				->method('setParsedMessage')
				->with('Hi @Administrator')
				->willReturnSelf();
		}

		$notification->expects($this->exactly(2))
			->method('getUser')
			->willReturn('recipient');
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('spreed');
		$notification->expects($this->atLeast(2))
			->method('getSubject')
			->willReturn($subject);
		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn($subjectParameters);
		$notification->method('getObjectType')
			->willReturn('chat');
		$notification->method('getObjectId')
			->willReturn('roomToken');
		$notification->expects($this->once())
			->method('getMessageParameters')
			->willReturn(['commentId' => '23']);

		$this->assertEquals($notification, $this->notifier->prepare($notification, 'de'));
	}

	public static function dataPrepareThrows() {
		return [
			['Incorrect app', 'invalid-app', null, null, null, null, null],
			'User can not use Talk' => [AlreadyProcessedException::class, 'spreed', true, null, null, null, null],
			'Invalid room' => [AlreadyProcessedException::class, 'spreed', false, false, null, null, null, '12345'],
			'Invalid room without token' => [AlreadyProcessedException::class, 'spreed', false, false, null, null, null],
			['Unknown subject', 'spreed', false, true, 'invalid-subject', null, null],
			['Unknown object type', 'spreed', false, true, 'invitation', null, 'invalid-object-type'],
			'Calling user does not exist anymore' => [AlreadyProcessedException::class, 'spreed', false, true, 'invitation', ['admin'], 'room'],
			['Unknown object type', 'spreed', false, true, 'mention', null, 'invalid-object-type'],
		];
	}

	/**
	 * @dataProvider dataPrepareThrows
	 *
	 * @param string $message
	 * @param string $app
	 * @param bool|null $isDisabledForUser
	 * @param bool|null $validRoom
	 * @param string|null $subject
	 * @param array|null $params
	 * @param string|null $objectType
	 * @param string $token
	 */
	public function testPrepareThrows($message, $app, $isDisabledForUser, $validRoom, $subject, $params, $objectType, $token = 'roomToken') {
		/** @var INotification|MockObject $n */
		$n = $this->createMock(INotification::class);
		$l = $this->createMock(IL10N::class);

		if ($validRoom === null) {
			$this->manager->expects($this->never())
				->method('getRoomByToken');
		} elseif ($validRoom === true) {
			$room = $this->createMock(Room::class);
			$room->expects($this->never())
				->method('getType');
			$n->method('getObjectId')
				->willReturn($token);
			$this->manager->expects($this->once())
				->method('getRoomByToken')
				->with($token)
				->willReturn($room);
		} elseif ($validRoom === false) {
			$n->method('getObjectId')
				->willReturn($token);
			$this->manager->expects($this->once())
				->method('getRoomByToken')
				->with($token)
				->willThrowException(new RoomNotFoundException());
			$this->manager->expects($token !== 'roomToken' ? $this->once() : $this->never())
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

		if ($isDisabledForUser === null) {
			$n->expects($this->never())
				->method('getUser');
		} else {
			$n->expects($this->once())
				->method('getUser')
				->willReturn('recipient');
			$r = $this->createMock(IUser::class);
			$this->userManager->expects($this->atLeastOnce())
				->method('get')
				->willReturnMap([
					['recipient', $r],
					['admin', null],
				]);

			$this->config->expects($this->once())
				->method('isDisabledForUser')
				->willReturn($isDisabledForUser);
		}

		$n->expects($this->once())
			->method('getApp')
			->willReturn($app);
		if ($subject === null) {
			$n->expects($this->never())
				->method('getSubject');
		} else {
			$n->expects($this->once())
				->method('getSubject')
				->willReturn($subject);
		}
		if ($params === null) {
			$n->expects($this->never())
				->method('getSubjectParameters');
		} else {
			$n->expects($this->once())
				->method('getSubjectParameters')
				->willReturn($params);
		}
		if (($objectType === null && $app !== 'spreed') || $isDisabledForUser) {
			$n->expects($this->never())
				->method('getObjectType');
		} elseif ($objectType === null && $app === 'spreed') {
			$n->method('getObjectType')
				->willReturn('');
		} else {
			$n->expects($this->any())
				->method('getObjectType')
				->willReturn($objectType);
		}

		if ($message === AlreadyProcessedException::class) {
			$this->expectException(AlreadyProcessedException::class);
		} else {
			$this->expectException(\InvalidArgumentException::class);
			$this->expectExceptionMessage($message);
		}
		$this->notifier->prepare($n, 'de');
	}
}
