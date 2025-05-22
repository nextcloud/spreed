<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat;

use OC\Comments\Comment;
use OCA\Talk\Chat\Notifier;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Files\Util;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class NotifierTest extends TestCase {
	protected INotificationManager&MockObject $notificationManager;
	protected IUserManager&MockObject $userManager;
	protected IGroupManager&MockObject $groupManager;
	protected ParticipantService&MockObject $participantService;
	protected IConfig&MockObject $config;
	protected ITimeFactory&MockObject $timeFactory;
	protected Util&MockObject $util;

	public function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(INotificationManager::class);

		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager
			->method('userExists')
			->willReturnCallback(function ($userId) {
				return $userId !== 'unknownUser';
			});
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->participantService = $this->createMock(ParticipantService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->util = $this->createMock(Util::class);
	}

	/**
	 * @param string[] $methods
	 * @return Notifier|MockObject
	 */
	protected function getNotifier(array $methods = []) {
		if (!empty($methods)) {
			return $this->getMockBuilder(Notifier::class)
				->setConstructorArgs([
					$this->notificationManager,
					$this->userManager,
					$this->groupManager,
					$this->participantService,
					$this->config,
					$this->timeFactory,
					$this->util,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new Notifier(
			$this->notificationManager,
			$this->userManager,
			$this->groupManager,
			$this->participantService,
			$this->config,
			$this->timeFactory,
			$this->util
		);
	}

	private function newComment($id, $actorType, $actorId, $creationDateTime, $message): IComment {
		$comment = new Comment([
			'id' => $id,
			'object_id' => '1234',
			'object_type' => 'chat',
			'actor_type' => $actorType,
			'actor_id' => $actorId,
			'creation_date_time' => $creationDateTime,
			'message' => $message,
			'verb' => 'comment',
		]);

		return $comment;
	}

	/**
	 * @return Room|MockObject
	 */
	private function getRoom($settings = []) {
		/** @var Room|MockObject */
		$room = $this->createMock(Room::class);

		$this->participantService->expects($this->any())
			->method('getParticipant')
			->willReturnCallback(function (Room $room, string $actorId) use ($settings): Participant {
				if ($actorId === 'userNotInOneToOneChat') {
					throw new ParticipantNotFoundException();
				}
				$attendeeRow = [
					'actor_type' => 'user',
					'actor_id' => $actorId,
				];
				if (isset($settings['attendee'][$actorId])) {
					$attendeeRow = array_merge($attendeeRow, $settings['attendee'][$actorId]);
				}
				$attendee = Attendee::fromRow($attendeeRow);
				return new Participant($room, $attendee, null);
			});

		return $room;
	}

	public static function dataNotifyMentionedUsers(): array {
		return [
			'no notifications' => [
				'No mentions', [], [], [],
			],
			'notify a mentioned user' => [
				'Mention @anotherUser', [], [['id' => 'anotherUser', 'type' => 'users', 'reason' => 'direct']], [['id' => 'anotherUser', 'type' => 'users', 'reason' => 'direct']],
			],
			'not notify mentioned user if already notified' => [
				'Mention @anotherUser', [['id' => 'anotherUser', 'type' => 'users', 'reason' => 'reply']], [], [['id' => 'anotherUser', 'type' => 'users', 'reason' => 'reply']],
			],
			'notify mentioned Users With Long Message Start Mention' => [
				'123456789 @anotherUserWithOddLengthName 123456789-123456789-123456789-123456789-123456789-123456789', [], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']],
			],
			'notify mentioned users with long message middle mention' => [
				'123456789-123456789-123456789-1234 @anotherUserWithOddLengthName 6789-123456789-123456789-123456789', [], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']],
			],
			'notify mentioned users with long message end mention' => [
				'123456789-123456789-123456789-123456789-123456789-123456789 @anotherUserWithOddLengthName 123456789', [], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']], [['id' => 'anotherUserWithOddLengthName', 'type' => 'users', 'reason' => 'direct']],
			],
			'mention herself' => [
				'Mention @testUser', [], [], [],
			],
			'not notify unknownuser' => [
				'Mention @unknownUser', [], [], [],
			],
			'notify mentioned users several mentions' => [
				'Mention @anotherUser, and @unknownUser, and @testUser, and @userAbleToJoin', [],
				[['id' => 'anotherUser', 'type' => 'users', 'reason' => 'direct'], ['id' => 'userAbleToJoin', 'type' => 'users', 'reason' => 'direct']],
				[['id' => 'anotherUser', 'type' => 'users', 'reason' => 'direct'], ['id' => 'userAbleToJoin', 'type' => 'users', 'reason' => 'direct']],
			],
			'notify mentioned users to user not invited to chat' => [
				'Mention @userNotInOneToOneChat', [], [], [],
			]
		];
	}

	/**
	 * @dataProvider dataNotifyMentionedUsers
	 */
	public function testNotifyMentionedUsers(string $message, array $alreadyNotifiedUsers, array $notify, array $expectedReturn): void {
		if (count($notify)) {
			$this->notificationManager->expects($this->exactly(count($notify)))
				->method('notify');
		}

		$room = $this->getRoom();
		$comment = $this->newComment('108', 'users', 'testUser', new \DateTime('@' . 1000000016), $message);
		$notifier = $this->getNotifier([]);
		$participant = $this->createMock(Participant::class);
		$actual = $notifier->notifyMentionedUsers($room, $comment, $alreadyNotifiedUsers, false, $participant);

		$this->assertEqualsCanonicalizing($expectedReturn, $actual);
	}

	public static function dataShouldParticipantBeNotified(): array {
		return [
			[Attendee::ACTOR_GROUPS, 'test1', null, Attendee::ACTOR_USERS, 'test1', [], false, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test1', [], false, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test2', [], false, Notifier::PRIORITY_NORMAL],
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test2', [['id' => 'test1', 'type' => Attendee::ACTOR_USERS]], false, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test2', [['id' => 'test1', 'type' => Attendee::ACTOR_FEDERATED_USERS]], false, Notifier::PRIORITY_NORMAL],
			[Attendee::ACTOR_USERS, 'test1', Session::SESSION_TIMEOUT - 5, Attendee::ACTOR_USERS, 'test2', [], false, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', Session::SESSION_TIMEOUT + 5, Attendee::ACTOR_USERS, 'test2', [], false, Notifier::PRIORITY_NORMAL],

			// Marked as important, still blocked by session and being the author, but otherwise with PRIORITY_IMPORTANT
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test1', [], true, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', null, Attendee::ACTOR_USERS, 'test2', [], true, Notifier::PRIORITY_IMPORTANT],
			[Attendee::ACTOR_USERS, 'test1', Session::SESSION_TIMEOUT - 5, Attendee::ACTOR_USERS, 'test2', [], true, Notifier::PRIORITY_NONE],
			[Attendee::ACTOR_USERS, 'test1', Session::SESSION_TIMEOUT + 5, Attendee::ACTOR_USERS, 'test2', [], true, Notifier::PRIORITY_IMPORTANT],
		];
	}

	/**
	 * @dataProvider dataShouldParticipantBeNotified
	 */
	public function testShouldParticipantBeNotified(string $actorType, string $actorId, ?int $sessionAge, string $commentActorType, string $commentActorId, array $alreadyNotifiedUsers, bool $isImportant, int $expected): void {
		$comment = $this->createMock(IComment::class);
		$comment->method('getActorType')
			->willReturn($commentActorType);
		$comment->method('getActorId')
			->willReturn($commentActorId);

		$room = $this->createMock(Room::class);
		$attendee = Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => $actorId,
			'important' => $isImportant,
		]);
		$session = null;
		if ($sessionAge !== null) {
			$current = 1234567;
			$this->timeFactory->method('getTime')
				->willReturn($current);

			$session = Session::fromRow([
				'last_ping' => $current - $sessionAge,
			]);
		}
		$participant = new Participant($room, $attendee, $session);

		self::assertSame($expected, self::invokePrivate($this->getNotifier(), 'shouldParticipantBeNotified', [$participant, $comment, $alreadyNotifiedUsers]));
	}

	public function testRemovePendingNotificationsForRoom(): void {
		$notification = $this->createMock(INotification::class);

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->atLeastOnce())
			->method('setObject')
			->with($this->anything(), 'Token123')
			->willReturnSelf();

		$this->notificationManager->expects($this->atLeastOnce())
			->method('markProcessed')
			->with($notification);

		$this->getNotifier()->removePendingNotificationsForRoom($room);
	}

	public function testRemovePendingNotificationsForChatOnly(): void {
		$notification = $this->createMock(INotification::class);

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getToken')
			->willReturn('Token123');

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);

		$notification->expects($this->once())
			->method('setApp')
			->with('spreed')
			->willReturnSelf();

		$notification->expects($this->atLeastOnce())
			->method('setObject')
			->with($this->anything(), 'Token123')
			->willReturnSelf();

		$this->notificationManager->expects($this->atLeastOnce())
			->method('markProcessed')
			->with($notification);

		$this->getNotifier()->removePendingNotificationsForRoom($room, true);
	}

	public static function dataAddMentionAllToList(): array {
		return [
			'not notify' => [
				[],
				[],
				0,
				true,
				[],
			],
			'preserve notify list and do not notify all' => [
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
				[],
				0,
				true,
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
			'mention all' => [
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
					['id' => 'all', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
				[
					Attendee::fromRow(['actor_id' => 'user1', 'actor_type' => Attendee::ACTOR_USERS]),
					Attendee::fromRow(['actor_id' => 'user2', 'actor_type' => Attendee::ACTOR_USERS]),
				],
				0,
				false,
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
					['id' => 'user2', 'type' => Attendee::ACTOR_USERS, 'reason' => 'all'],
				],
			],
			'prevent non-moderator to notify all' => [
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
					['id' => 'all', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
				[
					Attendee::fromRow(['actor_id' => 'user1', 'actor_type' => Attendee::ACTOR_USERS]),
					Attendee::fromRow(['actor_id' => 'user2', 'actor_type' => Attendee::ACTOR_USERS]),
				],
				1,
				false,
				[
					['id' => 'user1', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
		];
	}

	/**
	 * @dataProvider dataAddMentionAllToList
	 */
	public function testAddMentionAllToList(array $usersToNotify, array $participants, int $mentionPermissions, bool $moderatorPermissions, array $return): void {
		$room = $this->createMock(Room::class);
		$room->method('getMentionPermissions')
			->willReturn($mentionPermissions);

		$this->participantService
			->method('getActorsByType')
			->willReturn($participants);

		$participant = $this->createMock(Participant::class);
		$participant->method('hasModeratorPermissions')
			->willReturn($moderatorPermissions);

		$actual = self::invokePrivate($this->getNotifier(), 'addMentionAllToList', [$room, $usersToNotify, $participant]);
		$this->assertCount(count($return), $actual);
		foreach ($actual as $key => $value) {
			$this->assertIsArray($value);
			if (array_key_exists('attendee', $value)) {
				$this->assertInstanceOf(Attendee::class, $value['attendee']);
				unset($value['attendee']);
			}
			$this->assertEqualsCanonicalizing($return[$key], $value);
		}
	}

	public static function dataNotifyReacted(): array {
		return [
			'author react to own message' =>
				[0, Participant::NOTIFY_MENTION, Room::TYPE_GROUP, 'testUser'],
			'notify never' =>
				[0, Participant::NOTIFY_NEVER, Room::TYPE_GROUP, 'testUser2'],
			'notify default, not one to one' =>
				[0, Participant::NOTIFY_DEFAULT, Room::TYPE_GROUP, 'testUser2'],
			'notify default, one to one' =>
				[1, Participant::NOTIFY_DEFAULT, Room::TYPE_ONE_TO_ONE, 'testUser2'],
			'notify always' =>
				[1, Participant::NOTIFY_ALWAYS, Room::TYPE_GROUP, 'testUser2'],
		];
	}

	/**
	 * @dataProvider dataNotifyReacted
	 */
	public function testNotifyReacted(int $notify, int $notifyType, int $roomType, string $authorId): void {
		$this->notificationManager->expects($this->exactly($notify))
			->method('notify');

		$room = $this->getRoom([
			'attendee' => [
				'testUser' => [
					'notificationLevel' => $notifyType,
				]
			]
		]);
		$room->method('getType')
			->willReturn($roomType);
		$comment = $this->newComment('108', 'users', 'testUser', new \DateTime('@' . 1000000016), 'message');
		$reaction = $this->newComment('108', 'users', $authorId, new \DateTime('@' . 1000000016), 'message');

		$notifier = $this->getNotifier([]);
		$notifier->notifyReacted($room, $comment, $reaction);
	}

	public static function dataGetMentionedUsers(): array {
		return [
			'mention one user' => [
				'Mention @anotherUser',
				[
					['id' => 'anotherUser', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
			'mention two user' => [
				'Mention @anotherUser, and @unknownUser',
				[
					['id' => 'anotherUser', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
					['id' => 'unknownUser', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
			'mention all' => [
				'Mention @all',
				[
					['id' => 'all', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
			'mention user, all, guest and group' => [
				'mention @test, @all, @"guest/1" @"group/1"',
				[
					['id' => 'test', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
					['id' => 'all', 'type' => Attendee::ACTOR_USERS, 'reason' => 'direct'],
				],
			],
		];
	}

	/**
	 * @dataProvider dataGetMentionedUsers
	 */
	public function testGetMentionedUsers(string $message, array $expectedReturn): void {
		$comment = $this->newComment('108', 'users', 'testUser', new \DateTime('@' . 1000000016), $message);
		$actual = self::invokePrivate($this->getNotifier(), 'getMentionedUsers', [$comment]);
		$this->assertEqualsCanonicalizing($expectedReturn, $actual);
	}

	public static function dataGetMentionedUserIds(): array {
		$return = self::dataGetMentionedUsers();
		array_walk($return, function (array &$scenario) {
			array_walk($scenario[1], function (array &$params): void {
				$params = $params['id'];
			});
			return $scenario;
		});
		return $return;
	}

	/**
	 * @dataProvider dataGetMentionedUserIds
	 */
	public function testGetMentionedUserIds(string $message, array $expectedReturn): void {
		$comment = $this->newComment('108', 'users', 'testUser', new \DateTime('@' . 1000000016), $message);
		$actual = self::invokePrivate($this->getNotifier(), 'getMentionedUserIds', [$comment]);
		$this->assertEqualsCanonicalizing($expectedReturn, $actual);
	}
}
