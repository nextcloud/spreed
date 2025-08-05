<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\Notifier;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class ChatManagerTest extends TestCase {
	protected CommentsManager|ICommentsManager|MockObject $commentsManager;
	protected IEventDispatcher&MockObject $dispatcher;
	protected INotificationManager&MockObject $notificationManager;
	protected IManager&MockObject $shareManager;
	protected RoomShareProvider&MockObject $shareProvider;
	protected ParticipantService&MockObject $participantService;
	protected RoomService&MockObject $roomService;
	protected PollService&MockObject $pollService;
	protected Notifier&MockObject $notifier;
	protected ITimeFactory&MockObject $timeFactory;
	protected AttachmentService&MockObject $attachmentService;
	protected IReferenceManager&MockObject $referenceManager;
	protected ILimiter&MockObject $rateLimiter;
	protected IRequest&MockObject $request;
	protected LoggerInterface&MockObject $logger;
	protected IL10N&MockObject $l;
	protected ?ChatManager $chatManager = null;

	public function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->createMock(CommentsManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->shareProvider = $this->createMock(RoomShareProvider::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->pollService = $this->createMock(PollService::class);
		$this->notifier = $this->createMock(Notifier::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->referenceManager = $this->createMock(IReferenceManager::class);
		$this->rateLimiter = $this->createMock(ILimiter::class);
		$this->request = $this->createMock(IRequest::class);
		$this->l = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l->method('n')
			->willReturnCallback(function (string $singular, string $plural, int $count, array $parameters = []) {
				$text = $count === 1 ? $singular : $plural;
				return vsprintf(str_replace('%n', (string)$count, $text), $parameters);
			});

		$this->chatManager = $this->getManager();
	}

	/**
	 * @param string[] $methods
	 * @return ChatManager|MockObject
	 */
	protected function getManager(array $methods = []): ChatManager {
		$cacheFactory = $this->createMock(ICacheFactory::class);

		if (!empty($methods)) {
			return $this->getMockBuilder(ChatManager::class)
				->setConstructorArgs([
					$this->commentsManager,
					$this->dispatcher,
					\OCP\Server::get(IDBConnection::class),
					$this->notificationManager,
					$this->shareManager,
					$this->shareProvider,
					$this->participantService,
					$this->roomService,
					$this->pollService,
					$this->notifier,
					$cacheFactory,
					$this->timeFactory,
					$this->attachmentService,
					$this->referenceManager,
					$this->rateLimiter,
					$this->request,
					$this->l,
					$this->logger,
				])
				->onlyMethods($methods)
				->getMock();
		}

		return new ChatManager(
			$this->commentsManager,
			$this->dispatcher,
			\OCP\Server::get(IDBConnection::class),
			$this->notificationManager,
			$this->shareManager,
			$this->shareProvider,
			$this->participantService,
			$this->roomService,
			$this->pollService,
			$this->notifier,
			$cacheFactory,
			$this->timeFactory,
			$this->attachmentService,
			$this->referenceManager,
			$this->rateLimiter,
			$this->request,
			$this->l,
			$this->logger,
		);
	}

	private function newComment($id, string $actorType, string $actorId, \DateTime $creationDateTime, string $message): IComment {
		$comment = $this->createMock(IComment::class);

		$id = (string)$id;

		$comment->method('getId')->willReturn($id);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);

		return $comment;
	}

	/**
	 * @param array $data
	 * @return IComment|MockObject
	 */
	private function newCommentFromArray(array $data): IComment {
		$comment = $this->createMock(IComment::class);

		foreach ($data as $key => $value) {
			if ($key === 'id') {
				$value = (string)$value;
			}
			$comment->method('get' . ucfirst($key))->willReturn($value);
		}

		return $comment;
	}

	protected function assertCommentEquals(array $data, IComment $comment): void {
		if (isset($data['id'])) {
			$id = $data['id'];
			unset($data['id']);
			$this->assertEquals($id, $comment->getId());
		}

		$this->assertEquals($data, [
			'actorType' => $comment->getActorType(),
			'actorId' => $comment->getActorId(),
			'creationDateTime' => $comment->getCreationDateTime(),
			'message' => $comment->getMessage(),
			'referenceId' => $comment->getReferenceId(),
			'parentId' => $comment->getParentId(),
		]);
	}

	public static function dataSendMessage(): array {
		return [
			'simple message' => ['testUser1', 'testMessage1', '', '0'],
			'reference id' => ['testUser2', 'testMessage2', 'referenceId2', '0'],
			'as a reply' => ['testUser3', 'testMessage3', '', '23'],
			'reply w/ ref' => ['testUser4', 'testMessage4', 'referenceId4', '23'],
		];
	}

	#[DataProvider('dataSendMessage')]
	public function testSendMessage(string $userId, string $message, string $referenceId, string $parentId): void {
		$creationDateTime = new \DateTime();

		$commentExpected = [
			'actorType' => 'users',
			'actorId' => $userId,
			'creationDateTime' => $creationDateTime,
			'message' => $message,
			'referenceId' => $referenceId,
			'parentId' => $parentId,
		];

		$comment = $this->newCommentFromArray($commentExpected);

		if ($parentId !== '0') {
			$replyTo = $this->newCommentFromArray([
				'id' => $parentId,
			]);

			$comment->expects($this->once())
				->method('setParentId')
				->with($parentId);
		} else {
			$replyTo = null;
		}

		$this->commentsManager->expects($this->once())
			->method('create')
			->with('users', $userId, 'chat', 1234)
			->willReturn($comment);

		$comment->expects($this->once())
			->method('setMessage')
			->with($message);

		$comment->expects($this->once())
			->method('setCreationDateTime')
			->with($creationDateTime);

		$comment->expects($referenceId === '' ? $this->never() : $this->once())
			->method('setReferenceId')
			->with($referenceId);

		$comment->expects($this->once())
			->method('setVerb')
			->with('comment');

		$this->commentsManager->expects($this->once())
			->method('save')
			->with($comment);

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->notifier->expects($this->once())
			->method('notifyMentionedUsers')
			->with($chat, $comment);

		$participant = $this->createMock(Participant::class);

		$return = $this->chatManager->sendMessage($chat, $participant, 'users', $userId, $message, $creationDateTime, $replyTo, $referenceId, false);

		$this->assertCommentEquals($commentExpected, $return);
	}

	public function testGetHistory(): void {
		$offset = 1;
		$limit = 42;
		$expected = [
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1')
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->commentsManager->expects($this->once())
			->method('getForObjectSince')
			->with('chat', 1234, $offset, 'desc', $limit)
			->willReturn($expected);

		$comments = $this->chatManager->getHistory($chat, $offset, $limit, false);

		$this->assertEquals($expected, $comments);
	}

	public function testWaitForNewMessages(): void {
		$offset = 1;
		$limit = 42;
		$timeout = 23;
		$expected = [
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->commentsManager->expects($this->once())
			->method('getForObjectSince')
			->with('chat', 1234, $offset, 'asc', $limit)
			->willReturn($expected);

		$this->notifier->expects($this->once())
			->method('markMentionNotificationsRead')
			->with($chat, 'userId');

		/** @var IUser&MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('userId');

		$comments = $this->chatManager->waitForNewMessages($chat, $offset, $limit, $timeout, $user, false, true);

		$this->assertEquals($expected, $comments);
	}

	public function testWaitForNewMessagesWithWaiting(): void {
		$offset = 1;
		$limit = 42;
		$timeout = 23;
		$expected = [
			$this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage1'),
			$this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000023), 'testMessage2'),
			$this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000042), 'testMessage3'),
		];

		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->commentsManager->expects($this->exactly(2))
			->method('getForObjectSince')
			->with('chat', 1234, $offset, 'asc', $limit)
			->willReturnOnConsecutiveCalls(
				[],
				$expected
			);

		$this->notifier->expects($this->once())
			->method('markMentionNotificationsRead')
			->with($chat, 'userId');

		/** @var IUser&MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('userId');

		$comments = $this->chatManager->waitForNewMessages($chat, $offset, $limit, $timeout, $user, false, true);

		$this->assertEquals($expected, $comments);
	}

	public function testGetUnreadCount(): void {
		/** @var Room&MockObject $chat */
		$chat = $this->createMock(Room::class);
		$chat->expects($this->atLeastOnce())
			->method('getId')
			->willReturn(23);

		$this->commentsManager->expects($this->once())
			->method('getNumberOfCommentsWithVerbsForObjectSinceComment')
			->with('chat', 23, 42, ['comment', 'object_shared']);

		$this->chatManager->getUnreadCount($chat, 42);
	}

	public function testDeleteMessages(): void {
		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->commentsManager->expects($this->once())
			->method('deleteCommentsAtObject')
			->with('chat', 1234);

		$this->notifier->expects($this->once())
			->method('removePendingNotificationsForRoom')
			->with($chat);

		$this->chatManager->deleteMessages($chat);
	}

	public function testDeleteMessage(): void {
		$mapper = new AttendeeMapper(\OCP\Server::get(IDBConnection::class));
		$attendee = $mapper->createAttendeeFromRow([
			'a_id' => 1,
			'room_id' => 123,
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'user',
			'display_name' => 'user-display',
			'pin' => '',
			'participant_type' => Participant::USER,
			'favorite' => true,
			'notification_level' => Participant::NOTIFY_MENTION,
			'notification_calls' => Participant::NOTIFY_CALLS_ON,
			'last_joined_call' => 0,
			'last_read_message' => 0,
			'last_mention_message' => 0,
			'last_mention_direct' => 0,
			'read_privacy' => Participant::PRIVACY_PUBLIC,
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'access_token' => '',
			'remote_id' => '',
			'phone_number' => '',
			'call_id' => '',
			'invited_cloud_id' => '',
			'state' => Invitation::STATE_ACCEPTED,
			'unread_messages' => 0,
			'last_attendee_activity' => 0,
			'archived' => 0,
			'important' => 0,
			'sensitive' => 0,
		]);
		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$participant = new Participant($chat, $attendee, null);

		$date = new \DateTime();

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')
			->willReturn('123456');
		$comment->method('getVerb')
			->willReturn('comment');
		$comment->expects($this->once())
			->method('setMessage');
		$comment->expects($this->once())
			->method('setVerb')
			->with('comment_deleted');

		$this->commentsManager->expects($this->once())
			->method('save')
			->with($comment);

		$systemMessage = $this->createMock(IComment::class);

		$chatManager = $this->getManager(['addSystemMessage']);
		$chatManager->expects($this->once())
			->method('addSystemMessage')
			->with($chat, Attendee::ACTOR_USERS, 'user', $this->anything(), $this->anything(), false, null, $comment)
			->willReturn($systemMessage);

		$this->assertSame($systemMessage, $chatManager->deleteMessage($chat, $comment, $participant, $date));
	}

	public function testDeleteMessageFileShare(): void {
		$mapper = new AttendeeMapper(\OCP\Server::get(IDBConnection::class));
		$attendee = $mapper->createAttendeeFromRow([
			'a_id' => 1,
			'room_id' => 123,
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'user',
			'display_name' => 'user-display',
			'pin' => '',
			'participant_type' => Participant::USER,
			'favorite' => true,
			'notification_level' => Participant::NOTIFY_MENTION,
			'notification_calls' => Participant::NOTIFY_CALLS_ON,
			'last_joined_call' => 0,
			'last_read_message' => 0,
			'last_mention_message' => 0,
			'last_mention_direct' => 0,
			'read_privacy' => Participant::PRIVACY_PUBLIC,
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'access_token' => '',
			'remote_id' => '',
			'phone_number' => '',
			'call_id' => '',
			'invited_cloud_id' => '',
			'state' => Invitation::STATE_ACCEPTED,
			'unread_messages' => 0,
			'last_attendee_activity' => 0,
			'archived' => 0,
			'important' => 0,
			'sensitive' => 0,
		]);
		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$chat->expects($this->any())
			->method('getToken')
			->willReturn('T0k3N');
		$participant = new Participant($chat, $attendee, null);

		$date = new \DateTime();

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')
			->willReturn('123456');
		$comment->method('getVerb')
			->willReturn('object_shared');
		$comment->expects($this->once())
			->method('getMessage')
			->willReturn(json_encode(['message' => 'file_shared', 'parameters' => ['share' => '42']]));
		$comment->expects($this->once())
			->method('setMessage');
		$comment->expects($this->once())
			->method('setVerb')
			->with('comment_deleted');

		$share = $this->createMock(IShare::class);
		$share->method('getShareType')
			->willReturn(IShare::TYPE_ROOM);
		$share->method('getSharedWith')
			->willReturn('T0k3N');
		$share->method('getShareOwner')
			->willReturn('user');

		$this->shareManager->method('getShareById')
			->with('ocRoomShare:42')
			->willReturn($share);

		$this->shareManager->expects($this->once())
			->method('deleteShare')
			->willReturn($share);

		$this->commentsManager->expects($this->once())
			->method('save')
			->with($comment);

		$systemMessage = $this->createMock(IComment::class);

		$chatManager = $this->getManager(['addSystemMessage']);
		$chatManager->expects($this->once())
			->method('addSystemMessage')
			->with($chat, Attendee::ACTOR_USERS, 'user', $this->anything(), $this->anything(), false, null, $comment)
			->willReturn($systemMessage);

		$this->assertSame($systemMessage, $chatManager->deleteMessage($chat, $comment, $participant, $date));
	}

	public function testDeleteMessageFileShareNotFound(): void {
		$mapper = new AttendeeMapper(\OCP\Server::get(IDBConnection::class));
		$attendee = $mapper->createAttendeeFromRow([
			'a_id' => 1,
			'room_id' => 123,
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'user',
			'display_name' => 'user-display',
			'pin' => '',
			'participant_type' => Participant::USER,
			'favorite' => true,
			'notification_level' => Participant::NOTIFY_MENTION,
			'notification_calls' => Participant::NOTIFY_CALLS_ON,
			'last_joined_call' => 0,
			'last_read_message' => 0,
			'last_mention_message' => 0,
			'last_mention_direct' => 0,
			'read_privacy' => Participant::PRIVACY_PUBLIC,
			'permissions' => Attendee::PERMISSIONS_DEFAULT,
			'access_token' => '',
			'remote_id' => '',
			'phone_number' => '',
			'call_id' => '',
			'invited_cloud_id' => '',
			'state' => Invitation::STATE_ACCEPTED,
			'unread_messages' => 0,
			'last_attendee_activity' => 0,
			'archived' => 0,
			'important' => 0,
			'sensitive' => 0,
		]);
		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$participant = new Participant($chat, $attendee, null);

		$date = new \DateTime();

		$comment = $this->createMock(IComment::class);
		$comment->method('getId')
			->willReturn('123456');
		$comment->method('getVerb')
			->willReturn('object_shared');
		$comment->expects($this->once())
			->method('getMessage')
			->willReturn(json_encode(['message' => 'file_shared', 'parameters' => ['share' => '42']]));

		$this->shareManager->method('getShareById')
			->with('ocRoomShare:42')
			->willThrowException(new ShareNotFound());

		$this->commentsManager->expects($this->never())
			->method('save');

		$systemMessage = $this->createMock(IComment::class);

		$chatManager = $this->getManager(['addSystemMessage']);
		$chatManager->expects($this->never())
			->method('addSystemMessage');

		$this->expectException(ShareNotFound::class);
		$this->assertSame($systemMessage, $chatManager->deleteMessage($chat, $comment, $participant, $date));
	}

	public function testClearHistory(): void {
		$chat = $this->createMock(Room::class);
		$chat->expects($this->any())
			->method('getId')
			->willReturn(1234);
		$chat->expects($this->any())
			->method('getToken')
			->willReturn('t0k3n');

		$this->commentsManager->expects($this->once())
			->method('deleteCommentsAtObject')
			->with('chat', 1234);

		$this->shareProvider->expects($this->once())
			->method('deleteInRoom')
			->with('t0k3n');

		$this->notifier->expects($this->once())
			->method('removePendingNotificationsForRoom')
			->with($chat, true);

		$this->participantService->expects($this->once())
			->method('resetChatDetails')
			->with($chat);

		$date = new \DateTime();
		$this->timeFactory->method('getDateTime')
			->willReturn($date);

		$manager = $this->getManager(['addSystemMessage']);
		$manager->expects($this->once())
			->method('addSystemMessage')
			->with(
				$chat,
				'users',
				'admin',
				json_encode(['message' => 'history_cleared', 'parameters' => []]),
				$date,
				false
			);
		$manager->clearHistory($chat, 'users', 'admin');
	}

	public static function dataSearchIsPartOfConversationNameOrAtAll(): array {
		return [
			'found a in all' => [
				'a', 'room', true
			],
			'found h in here' => [
				'h', 'room', true
			],
			'case sensitive, not found A in all' => [
				'A', 'room', false
			],
			'case sensitive, not found H in here' => [
				'H', 'room', false
			],
			'non case sensitive, found r in room' => [
				'R', 'room', true
			],
			'found r in begin of room' => [
				'r', 'room', true
			],
			'found o in middle of room' => [
				'o', 'room', true
			],
			'not found all in middle of text' => [
				'notbeginingall', 'room', false
			],
			'not found here in middle of text' => [
				'notbegininghere', 'room', false
			],
			'not found room in middle of text' => [
				'notbeginingroom', 'room', false
			],
		];
	}

	#[DataProvider('dataSearchIsPartOfConversationNameOrAtAll')]
	public function testSearchIsPartOfConversationNameOrAtAll(string $search, string $roomDisplayName, bool $expected): void {
		$actual = self::invokePrivate($this->chatManager, 'searchIsPartOfConversationNameOrAtAll', [$search, $roomDisplayName]);
		$this->assertEquals($expected, $actual);
	}

	public static function dataAddConversationNotify(): array {
		return [
			[
				'',
				['getType' => Room::TYPE_ONE_TO_ONE],
				[],
				null,
				[],
			],
			[
				'',
				['getDisplayName' => 'test', 'getMentionPermissions' => 0],
				['getAttendee' => Attendee::fromRow([
					'actor_type' => Attendee::ACTOR_USERS,
					'actor_id' => 'user',
				])],
				324,
				[['id' => 'all', 'label' => 'test', 'source' => 'calls', 'mentionId' => 'all', 'details' => 'All 324 participants']]
			],
			[
				'',
				['getMentionPermissions' => 1],
				['hasModeratorPermissions' => false],
				null,
				[],
			],
			[
				'all',
				['getDisplayName' => 'test', 'getMentionPermissions' => 0],
				['getAttendee' => Attendee::fromRow([
					'actor_type' => Attendee::ACTOR_USERS,
					'actor_id' => 'user',
				])],
				1,
				[['id' => 'all', 'label' => 'test', 'source' => 'calls', 'mentionId' => 'all']],
			],
			[
				'all',
				['getDisplayName' => 'test', 'getMentionPermissions' => 1],
				[
					'getAttendee' => Attendee::fromRow([
						'actor_type' => Attendee::ACTOR_USERS,
						'actor_id' => 'user',
					]),
					'hasModeratorPermissions' => true,
				],
				8,
				[['id' => 'all', 'label' => 'test', 'source' => 'calls', 'mentionId' => 'all', 'details' => 'All 8 participants']],
			],
			[
				'here',
				['getDisplayName' => 'test', 'getMentionPermissions' => 0],
				['getAttendee' => Attendee::fromRow([
					'actor_type' => Attendee::ACTOR_GUESTS,
					'actor_id' => 'guest',
				])],
				12,
				[['id' => 'all', 'label' => 'test', 'source' => 'calls', 'mentionId' => 'all', 'details' => 'All 12 participants']],
			],
		];
	}

	#[DataProvider('dataAddConversationNotify')]
	public function testAddConversationNotify(string $search, array $roomMocks, array $participantMocks, ?int $totalCount, array $expected): void {
		$room = $this->createMock(Room::class);
		foreach ($roomMocks as $method => $return) {
			$room->expects($this->once())
				->method($method)
				->willReturn($return);
		}

		$participant = $this->createMock(Participant::class);
		foreach ($participantMocks as $method => $return) {
			$participant->expects($this->once())
				->method($method)
				->willReturn($return);
		}

		if ($totalCount !== null) {
			$this->participantService->method('getNumberOfUsers')
				->willReturn($totalCount);
		}

		$actual = $this->chatManager->addConversationNotify([], $search, $room, $participant);
		$this->assertEquals($expected, $actual);
	}

	#[DataProvider('dataIsSharedFile')]
	public function testIsSharedFile(string $message, bool $expected): void {
		$actual = $this->chatManager->isSharedFile($message);
		$this->assertEquals($expected, $actual);
	}

	public static function dataIsSharedFile(): array {
		return [
			['', false],
			[json_encode([]), false],
			[json_encode(['parameters' => '']), false],
			[json_encode(['parameters' => []]), false],
			[json_encode(['parameters' => ['share' => null]]), false],
			[json_encode(['parameters' => ['share' => '']]), false],
			[json_encode(['parameters' => ['share' => []]]), false],
			[json_encode(['parameters' => ['share' => 0]]), false],
			[json_encode(['parameters' => ['share' => 1]]), true],
		];
	}

	#[DataProvider('dataFilterCommentsWithNonExistingFiles')]
	public function testFilterCommentsWithNonExistingFiles(array $list, int $expectedCount): void {
		// Transform text messages in instance of comment and mock with the message
		foreach ($list as $key => $message) {
			$list[$key] = $this->createMock(IComment::class);
			$list[$key]->method('getMessage')
				->willReturn($message);
			$messageDecoded = json_decode($message, true);
			if (isset($messageDecoded['parameters']['share']) && $messageDecoded['parameters']['share'] === 'notExists') {
				$this->shareProvider->expects($this->once())
					->method('getShareById')
					->with('notExists')
					->willThrowException(new ShareNotFound());
			}
		}
		if (count($list) !== $expectedCount) {
		}
		$result = $this->chatManager->filterCommentsWithNonExistingFiles($list);
		$this->assertCount($expectedCount, $result);
	}

	public static function dataFilterCommentsWithNonExistingFiles(): array {
		return [
			[[], 0],
			[[json_encode(['parameters' => ['not a shared file']])], 1],
			[[json_encode(['parameters' => ['share' => 'notExists']])], 0],
			[[json_encode(['parameters' => ['share' => 1]])], 1],
		];
	}
}
