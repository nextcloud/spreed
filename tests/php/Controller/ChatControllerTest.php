<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Controller\ChatController;
use OCA\Talk\GuestManager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Share\Helper\FilesMetadataCache;
use OCA\Talk\Share\RoomShareProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\ITrustedDomainHelper;
use OCP\UserStatus\IManager as IUserStatusManager;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ChatControllerTest extends TestCase {
	private ?string $userId = null;
	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IAppManager|MockObject */
	private $appManager;
	/** @var ChatManager|MockObject */
	protected $chatManager;
	/** @var ReactionManager|MockObject */
	protected $reactionManager;
	/** @var ParticipantService|MockObject */
	protected $participantService;
	/** @var SessionService|MockObject */
	protected $sessionService;
	/** @var AttachmentService|MockObject */
	protected $attachmentService;
	/** @var AvatarService|MockObject */
	protected $avatarService;
	/** @var ReminderService|MockObject */
	protected $reminderService;
	/** @var GuestManager|MockObject */
	protected $guestManager;
	/** @var MessageParser|MockObject */
	protected $messageParser;
	/** @var RoomShareProvider|MockObject */
	protected $roomShareProvider;
	/** @var FilesMetadataCache|MockObject */
	protected $filesMetadataCache;
	/** @var IManager|MockObject */
	protected $autoCompleteManager;
	/** @var IUserStatusManager|MockObject */
	protected $statusManager;
	/** @var MatterbridgeManager|MockObject */
	protected $matterbridgeManager;
	/** @var SearchPlugin|MockObject */
	protected $searchPlugin;
	/** @var ISearchResult|MockObject */
	protected $searchResult;
	/** @var IEventDispatcher|MockObject */
	protected $eventDispatcher;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IValidator|MockObject */
	protected $richObjectValidator;
	/** @var ITrustedDomainHelper|MockObject */
	protected $trustedDomainHelper;
	/** @var IL10N|MockObject */
	private $l;

	/** @var Room|MockObject */
	protected $room;

	private ?ChatController $controller = null;

	/** @var Callback */
	private $newMessageDateTimeConstraint;

	public function setUp(): void {
		parent::setUp();

		$this->userId = 'testUser';
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->reactionManager = $this->createMock(ReactionManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->sessionService = $this->createMock(SessionService::class);
		$this->attachmentService = $this->createMock(AttachmentService::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->reminderService = $this->createMock(ReminderService::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->roomShareProvider = $this->createMock(RoomShareProvider::class);
		$this->filesMetadataCache = $this->createMock(FilesMetadataCache::class);
		$this->autoCompleteManager = $this->createMock(IManager::class);
		$this->statusManager = $this->createMock(IUserStatusManager::class);
		$this->matterbridgeManager = $this->createMock(MatterbridgeManager::class);
		$this->searchPlugin = $this->createMock(SearchPlugin::class);
		$this->searchResult = $this->createMock(ISearchResult::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->richObjectValidator = $this->createMock(IValidator::class);
		$this->trustedDomainHelper = $this->createMock(ITrustedDomainHelper::class);
		$this->l = $this->createMock(IL10N::class);

		$this->room = $this->createMock(Room::class);

		$this->recreateChatController();

		// Verifies that the difference of the given DateTime and now is at most
		// five seconds, and that it uses the UTC time zone.
		$this->newMessageDateTimeConstraint = $this->callback(function (\DateTime $dateTime) {
			return abs((new \DateTime())->getTimestamp() - $dateTime->getTimestamp()) <= 5 &&
				(new \DateTimeZone('UTC'))->getName() === $dateTime->getTimezone()->getName();
		});
	}

	private function recreateChatController() {
		$this->controller = new ChatController(
			'spreed',
			$this->userId,
			$this->createMock(IRequest::class),
			$this->userManager,
			$this->appManager,
			$this->chatManager,
			$this->reactionManager,
			$this->participantService,
			$this->sessionService,
			$this->attachmentService,
			$this->avatarService,
			$this->reminderService,
			$this->guestManager,
			$this->messageParser,
			$this->roomShareProvider,
			$this->filesMetadataCache,
			$this->autoCompleteManager,
			$this->statusManager,
			$this->matterbridgeManager,
			$this->searchPlugin,
			$this->searchResult,
			$this->timeFactory,
			$this->eventDispatcher,
			$this->richObjectValidator,
			$this->trustedDomainHelper,
			$this->l
		);
	}

	private function newComment($id, $actorType, $actorId, $creationDateTime, $message) {
		$comment = $this->createMock(IComment::class);

		$comment->method('getId')->willReturn($id);
		$comment->method('getActorType')->willReturn($actorType);
		$comment->method('getActorId')->willReturn($actorId);
		$comment->method('getCreationDateTime')->willReturn($creationDateTime);
		$comment->method('getMessage')->willReturn($message);
		$comment->method('getParentId')->willReturn('0');

		return $comment;
	}

	public function testSendMessageByUser() {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(42, 'user', $this->userId, $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
				$participant,
				'users',
				$this->userId,
				'testMessage',
				$this->newMessageDateTimeConstraint
			)
			->willReturn($comment);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 42,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId,
				'actorDisplayName' => 'displayName',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage',
				'messageParameters' => ['arg' => 'uments'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => '',
			]);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $comment, $this->l)
			->willReturn($chatMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage');
		$expected = new DataResponse([
			'id' => 42,
			'token' => 'testToken',
			'actorType' => 'users',
			'actorId' => $this->userId,
			'actorDisplayName' => 'displayName',
			'timestamp' => $date->getTimestamp(),
			'message' => 'parsedMessage',
			'messageParameters' => ['arg' => 'uments'],
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
			'referenceId' => '',
		], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByUserWithReferenceId() {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(42, 'user', $this->userId, $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
				$participant,
				'users',
				$this->userId,
				'testMessage',
				$this->newMessageDateTimeConstraint
			)
			->willReturn($comment);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 42,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId,
				'actorDisplayName' => 'displayName',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage',
				'messageParameters' => ['arg' => 'uments'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => sha1('ref'),
			]);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $comment, $this->l)
			->willReturn($chatMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage', '', sha1('ref'));
		$expected = new DataResponse([
			'id' => 42,
			'token' => 'testToken',
			'actorType' => 'users',
			'actorId' => $this->userId,
			'actorDisplayName' => 'displayName',
			'timestamp' => $date->getTimestamp(),
			'message' => 'parsedMessage',
			'messageParameters' => ['arg' => 'uments'],
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
			'referenceId' => sha1('ref'),
		], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendReplyByUser() {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);

		/** @var IComment|MockObject $comment */
		$parent = $this->newComment(23, 'users', $this->userId . '2', $date, 'testMessage original');

		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(42, 'users', $this->userId, $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
				$participant,
				'users',
				$this->userId,
				'testMessage',
				$this->newMessageDateTimeConstraint,
				$parent
			)
			->willReturn($comment);
		$this->chatManager->expects($this->once())
			->method('getParentComment')
			->with($this->room, 23)
			->willReturn($parent);

		$parentMessage = $this->createMock(Message::class);
		$parentMessage->expects($this->once())
			->method('isReplyable')
			->willReturn(true);
		$parentMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 23,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId . '2',
				'actorDisplayName' => 'displayName2',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage2',
				'messageParameters' => ['arg' => 'uments2'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => '',
			]);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 42,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId,
				'actorDisplayName' => 'displayName',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage',
				'messageParameters' => ['arg' => 'uments'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => '',
			]);

		$this->messageParser->expects($this->exactly(2))
			->method('createMessage')
			->willReturnMap([
				[$this->room, $participant, $parent, $this->l, $parentMessage],
				[$this->room, $participant, $comment, $this->l, $chatMessage],
			]);

		$i = 0;
		$expectedCalls = [
			[$parentMessage],
			[$chatMessage],
		];
		$this->messageParser->expects($this->exactly(2))
			->method('parseMessage')
			->willReturnCallback(function () use ($expectedCalls, &$i) {
				Assert::assertArrayHasKey($i, $expectedCalls);
				Assert::assertSame($expectedCalls[$i], func_get_args());
				$i++;
			});

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage', '', '', 23);
		$expected = new DataResponse([
			'id' => 42,
			'token' => 'testToken',
			'actorType' => 'users',
			'actorId' => $this->userId,
			'actorDisplayName' => 'displayName',
			'timestamp' => $date->getTimestamp(),
			'message' => 'parsedMessage',
			'messageParameters' => ['arg' => 'uments'],
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
			'referenceId' => '',
			'parent' => [
				'id' => 23,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId . '2',
				'actorDisplayName' => 'displayName2',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage2',
				'messageParameters' => ['arg' => 'uments2'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => '',
			],
		], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendReplyByUserToNotReplyable() {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		/** @var IComment|MockObject $comment */
		$parent = $this->newComment(23, 'user', $this->userId . '2', $date, 'testMessage original');

		$this->chatManager->expects($this->never())
			->method('sendMessage');
		$this->chatManager->expects($this->once())
			->method('getParentComment')
			->with($this->room, 23)
			->willReturn($parent);

		$parentMessage = $this->createMock(Message::class);
		$parentMessage->expects($this->once())
			->method('isReplyable')
			->willReturn(false);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $parent, $this->l)
			->willReturn($parentMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($parentMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage', '', '', 23);
		$expected = new DataResponse([], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByUserNotJoinedButInRoom() {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(23, 'user', $this->userId, $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
				$participant,
				'users',
				$this->userId,
				'testMessage',
				$this->newMessageDateTimeConstraint
			)
			->willReturn($comment);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 23,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId,
				'actorDisplayName' => 'displayName',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage2',
				'messageParameters' => ['arg' => 'uments2'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
			]);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $comment, $this->l)
			->willReturn($chatMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage');
		$expected = new DataResponse([
			'id' => 23,
			'token' => 'testToken',
			'actorType' => 'users',
			'actorId' => $this->userId,
			'actorDisplayName' => 'displayName',
			'timestamp' => $date->getTimestamp(),
			'message' => 'parsedMessage2',
			'messageParameters' => ['arg' => 'uments2'],
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
		], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByGuest() {
		$this->userId = null;
		$this->recreateChatController();

		$attendee = Attendee::fromRow([
			'actor_type' => 'guests',
			'actor_id' => 'actorId',
			'participant_type' => Participant::GUEST,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')
			->willReturn($attendee);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(64, 'guest', sha1('testSpreedSession'), $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('sendMessage')
			->with($this->room,
				$participant,
				'guests',
				'actorId',
				'testMessage',
				$this->newMessageDateTimeConstraint
			)
			->willReturn($comment);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 64,
				'token' => 'testToken',
				'actorType' => 'guests',
				'actorId' => sha1('testSpreedSession'),
				'actorDisplayName' => 'guest name',
				'timestamp' => $date->getTimestamp(),
				'message' => 'parsedMessage3',
				'messageParameters' => ['arg' => 'uments3'],
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
			]);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $comment, $this->l)
			->willReturn($chatMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->sendMessage('testMessage');
		$expected = new DataResponse([
			'id' => 64,
			'token' => 'testToken',
			'actorType' => 'guests',
			'actorId' => $comment->getActorId(),
			'actorDisplayName' => 'guest name',
			'timestamp' => $date->getTimestamp(),
			'message' => 'parsedMessage3',
			'messageParameters' => ['arg' => 'uments3'],
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
		], Http::STATUS_CREATED);

		$this->assertEquals($expected, $response);
	}

	public function testShareObjectToChatByUser() {
		$participant = $this->createMock(Participant::class);

		$this->avatarService->method('getAvatarUrl')
			->with($this->room)
			->willReturn('getAvatarUrl');

		$richData = [
			'call-type' => 'one2one',
			'type' => 'call',
			'id' => 'R4nd0mToken',
			'icon-url' => '',
		];

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment|MockObject $comment */
		$comment = $this->newComment(42, 'user', $this->userId, $date, 'testMessage');
		$this->chatManager->expects($this->once())
			->method('addSystemMessage')
			->with($this->room,
				'users',
				$this->userId,
				json_encode([
					'message' => 'object_shared',
					'parameters' => [
						'objectType' => 'call',
						'objectId' => 'R4nd0mToken',
						'metaData' => [
							'call-type' => 'one2one',
							'type' => 'call',
							'id' => 'R4nd0mToken',
							'icon-url' => 'getAvatarUrl',
						],
					],
				]),
				$this->newMessageDateTimeConstraint
			)
			->willReturn($comment);

		$chatMessage = $this->createMock(Message::class);
		$chatMessage->expects($this->once())
			->method('getVisibility')
			->willReturn(true);
		$chatMessage->expects($this->once())
			->method('toArray')
			->willReturn([
				'id' => 42,
				'token' => 'testToken',
				'actorType' => 'users',
				'actorId' => $this->userId,
				'actorDisplayName' => 'displayName',
				'timestamp' => $date->getTimestamp(),
				'message' => '{object}',
				'messageParameters' => $richData,
				'systemMessage' => '',
				'messageType' => 'comment',
				'isReplyable' => true,
				'referenceId' => '',
			]);

		$this->messageParser->expects($this->once())
			->method('createMessage')
			->with($this->room, $participant, $comment, $this->l)
			->willReturn($chatMessage);

		$this->messageParser->expects($this->once())
			->method('parseMessage')
			->with($chatMessage);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->shareObjectToChat($richData['type'], $richData['id'], json_encode(['call-type' => $richData['call-type']]));
		$expected = new DataResponse([
			'id' => 42,
			'token' => 'testToken',
			'actorType' => 'users',
			'actorId' => $this->userId,
			'actorDisplayName' => 'displayName',
			'timestamp' => $date->getTimestamp(),
			'message' => '{object}',
			'messageParameters' => $richData,
			'systemMessage' => '',
			'messageType' => 'comment',
			'isReplyable' => true,
			'referenceId' => '',
		], Http::STATUS_CREATED);

		$this->assertEquals($expected->getStatus(), $response->getStatus());
		$this->assertEquals($expected->getData(), $response->getData());
	}

	public function testReceiveHistoryByUser() {
		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$participant = $this->createMock(Participant::class);

		$i = 4;
		$expectedCalls = [
			[$this->room, $participant, $comment4, $this->l],
			[$this->room, $participant, $comment3, $this->l],
			[$this->room, $participant, $comment2, $this->l],
			[$this->room, $participant, $comment1, $this->l],
		];
		$this->messageParser->expects($this->exactly(4))
			->method('createMessage')
			->willReturnCallback(function ($room, $participant, IComment $comment, $l) use ($expectedCalls, &$i) {
				Assert::assertArrayHasKey(4 - $i, $expectedCalls);
				Assert::assertSame($expectedCalls[4 - $i], func_get_args());

				$chatMessage = $this->createMock(Message::class);
				$chatMessage->expects($this->once())
					->method('getVisibility')
					->willReturn(true);
				$chatMessage->expects($this->once())
					->method('toArray')
					->willReturn([
						'id' => $comment->getId(),
						'token' => 'testToken',
						'actorType' => $comment->getActorType(),
						'actorId' => $comment->getActorId(),
						'actorDisplayName' => 'User' . $i,
						'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
						'message' => 'testMessage' . $i,
						'messageParameters' => ['testMessageParameters' . $i],
						'systemMessage' => '',
						'messageType' => 'comment',
						'isReplyable' => true,
					]);

				$i--;
				return $chatMessage;
			});

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage');

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(0, $limit, $offset);
		$expected = new DataResponse([
			['id' => 111, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User4', 'timestamp' => 1000000016, 'message' => 'testMessage4', 'messageParameters' => ['testMessageParameters4'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 110, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUnknownUser', 'actorDisplayName' => 'User3', 'timestamp' => 1000000015, 'message' => 'testMessage3', 'messageParameters' => ['testMessageParameters3'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 109, 'token' => 'testToken', 'actorType' => 'guests', 'actorId' => 'testSpreedSession', 'actorDisplayName' => 'User2', 'timestamp' => 1000000008, 'message' => 'testMessage2', 'messageParameters' => ['testMessageParameters2'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 108, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User1', 'timestamp' => 1000000004, 'message' => 'testMessage1', 'messageParameters' => ['testMessageParameters1'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true]
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByUserNotJoinedButInRoom() {
		$participant = $this->createMock(Participant::class);

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$i = 4;
		$expectedCalls = [
			[$this->room, $participant, $comment4, $this->l],
			[$this->room, $participant, $comment3, $this->l],
			[$this->room, $participant, $comment2, $this->l],
			[$this->room, $participant, $comment1, $this->l],
		];
		$this->messageParser->expects($this->exactly(4))
			->method('createMessage')
			->willReturnCallback(function ($room, $participant, IComment $comment, $l) use ($expectedCalls, &$i) {
				Assert::assertArrayHasKey(4 - $i, $expectedCalls);
				Assert::assertSame($expectedCalls[4 - $i], func_get_args());

				$chatMessage = $this->createMock(Message::class);
				$chatMessage->expects($this->once())
					->method('getVisibility')
					->willReturn(true);
				$chatMessage->expects($this->once())
					->method('toArray')
					->willReturn([
						'id' => $comment->getId(),
						'token' => 'testToken',
						'actorType' => $comment->getActorType(),
						'actorId' => $comment->getActorId(),
						'actorDisplayName' => 'User' . $i,
						'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
						'message' => 'testMessage' . $i,
						'messageParameters' => ['testMessageParameters' . $i],
						'systemMessage' => '',
						'messageType' => 'comment',
						'isReplyable' => true,
					]);

				$i--;
				return $chatMessage;
			});

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage');

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(0, $limit, $offset);
		$expected = new DataResponse([
			['id' => 111, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User4', 'timestamp' => 1000000016, 'message' => 'testMessage4', 'messageParameters' => ['testMessageParameters4'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 110, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUnknownUser', 'actorDisplayName' => 'User3', 'timestamp' => 1000000015, 'message' => 'testMessage3', 'messageParameters' => ['testMessageParameters3'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 109, 'token' => 'testToken', 'actorType' => 'guests', 'actorId' => 'testSpreedSession', 'actorDisplayName' => 'User2', 'timestamp' => 1000000008, 'message' => 'testMessage2', 'messageParameters' => ['testMessageParameters2'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 108, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User1', 'timestamp' => 1000000004, 'message' => 'testMessage1', 'messageParameters' => ['testMessageParameters1'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true]
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testReceiveMessagesByGuest() {
		$this->userId = null;
		$this->recreateChatController();

		$participant = $this->createMock(Participant::class);

		$offset = 23;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('getHistory')
			->with($this->room, $offset, $limit)
			->willReturn([
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1')
			]);

		$i = 4;
		$expectedCalls = [
			[$this->room, $participant, $comment4, $this->l],
			[$this->room, $participant, $comment3, $this->l],
			[$this->room, $participant, $comment2, $this->l],
			[$this->room, $participant, $comment1, $this->l],
		];
		$this->messageParser->expects($this->exactly(4))
			->method('createMessage')
			->willReturnCallback(function ($room, $participant, IComment $comment, $l) use ($expectedCalls, &$i) {
				Assert::assertArrayHasKey(4 - $i, $expectedCalls);
				Assert::assertSame($expectedCalls[4 - $i], func_get_args());

				$chatMessage = $this->createMock(Message::class);
				$chatMessage->expects($this->once())
					->method('getVisibility')
					->willReturn(true);
				$chatMessage->expects($this->once())
					->method('toArray')
					->willReturn([
						'id' => $comment->getId(),
						'token' => 'testToken',
						'actorType' => $comment->getActorType(),
						'actorId' => $comment->getActorId(),
						'actorDisplayName' => 'User' . $i,
						'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
						'message' => 'testMessage' . $i,
						'messageParameters' => ['testMessageParameters' . $i],
						'systemMessage' => '',
						'messageType' => 'comment',
						'isReplyable' => true,
					]);

				$i--;
				return $chatMessage;
			});

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage');

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(0, $limit, $offset);
		$expected = new DataResponse([
			['id' => 111, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User4', 'timestamp' => 1000000016, 'message' => 'testMessage4', 'messageParameters' => ['testMessageParameters4'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 110, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUnknownUser', 'actorDisplayName' => 'User3', 'timestamp' => 1000000015, 'message' => 'testMessage3', 'messageParameters' => ['testMessageParameters3'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 109, 'token' => 'testToken', 'actorType' => 'guests', 'actorId' => 'testSpreedSession', 'actorDisplayName' => 'User2', 'timestamp' => 1000000008, 'message' => 'testMessage2', 'messageParameters' => ['testMessageParameters2'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 108, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User1', 'timestamp' => 1000000004, 'message' => 'testMessage1', 'messageParameters' => ['testMessageParameters1'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true]
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 108);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesByUser() {
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');

		$participant = $this->createMock(Participant::class);

		$offset = 23;
		$limit = 4;
		$timeout = 10;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $timeout, $testUser)
			->willReturn([
				$comment1 = $this->newComment(108, 'users', 'testUser', new \DateTime('@' . 1000000004), 'testMessage1'),
				$comment2 = $this->newComment(109, 'guests', 'testSpreedSession', new \DateTime('@' . 1000000008), 'testMessage2'),
				$comment3 = $this->newComment(110, 'users', 'testUnknownUser', new \DateTime('@' . 1000000015), 'testMessage3'),
				$comment4 = $this->newComment(111, 'users', 'testUser', new \DateTime('@' . 1000000016), 'testMessage4'),
			]);

		$this->userManager->expects($this->once())
			->method('get')
			->with('testUser')
			->willReturn($testUser);

		$i = 1;
		$expectedCalls = [
			[$this->room, $participant, $comment1, $this->l],
			[$this->room, $participant, $comment2, $this->l],
			[$this->room, $participant, $comment3, $this->l],
			[$this->room, $participant, $comment4, $this->l],
		];
		$this->messageParser->expects($this->exactly(count($expectedCalls)))
			->method('createMessage')
			->willReturnCallback(function ($room, $participant, IComment $comment, $l) use ($expectedCalls, &$i) {
				Assert::assertArrayHasKey($i - 1, $expectedCalls);
				Assert::assertSame($expectedCalls[$i - 1], func_get_args());

				$chatMessage = $this->createMock(Message::class);
				$chatMessage->expects($this->once())
					->method('getVisibility')
					->willReturn(true);
				$chatMessage->expects($this->once())
					->method('toArray')
					->willReturn([
						'id' => $comment->getId(),
						'token' => 'testToken',
						'actorType' => $comment->getActorType(),
						'actorId' => $comment->getActorId(),
						'actorDisplayName' => 'User' . $i,
						'timestamp' => $comment->getCreationDateTime()->getTimestamp(),
						'message' => 'testMessage' . $i,
						'messageParameters' => ['testMessageParameters' . $i],
						'systemMessage' => '',
						'messageType' => 'comment',
						'isReplyable' => true,
					]);

				$i++;
				return $chatMessage;
			});

		$this->messageParser->expects($this->exactly(4))
			->method('parseMessage');

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(1, $limit, $offset, 0, $timeout);
		$expected = new DataResponse([
			['id' => 108, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User1', 'timestamp' => 1000000004, 'message' => 'testMessage1', 'messageParameters' => ['testMessageParameters1'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 109, 'token' => 'testToken', 'actorType' => 'guests', 'actorId' => 'testSpreedSession', 'actorDisplayName' => 'User2', 'timestamp' => 1000000008, 'message' => 'testMessage2', 'messageParameters' => ['testMessageParameters2'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 110, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUnknownUser', 'actorDisplayName' => 'User3', 'timestamp' => 1000000015, 'message' => 'testMessage3', 'messageParameters' => ['testMessageParameters3'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
			['id' => 111, 'token' => 'testToken', 'actorType' => 'users', 'actorId' => 'testUser', 'actorDisplayName' => 'User4', 'timestamp' => 1000000016, 'message' => 'testMessage4', 'messageParameters' => ['testMessageParameters4'], 'systemMessage' => '', 'messageType' => 'comment', 'isReplyable' => true],
		], Http::STATUS_OK);
		$expected->addHeader('X-Chat-Last-Given', 111);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesTimeoutExpired() {
		$participant = $this->createMock(Participant::class);
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');

		$offset = 23;
		$limit = 4;
		$timeout = 3;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $timeout, $testUser)
			->willReturn([]);

		$this->userManager->expects($this->any())
			->method('get')
			->with('testUser')
			->willReturn($testUser);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(1, $limit, $offset, 0, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesTimeoutTooLarge() {
		$participant = $this->createMock(Participant::class);
		$testUser = $this->createMock(IUser::class);
		$testUser->expects($this->any())
			->method('getUID')
			->willReturn('testUser');

		$offset = 23;
		$timeout = 100000;
		$maximumTimeout = 30;
		$limit = 4;
		$this->chatManager->expects($this->once())
			->method('waitForNewMessages')
			->with($this->room, $offset, $limit, $maximumTimeout, $testUser)
			->willReturn([]);

		$this->userManager->expects($this->any())
			->method('get')
			->with('testUser')
			->willReturn($testUser);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->receiveMessages(1, $limit, $offset, $timeout);
		$expected = new DataResponse([], Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public static function dataMentions() {
		return [
			['tes', 10, ['exact' => []], []],
			['foo', 20, [
				'exact' => [
					'users' => [
						['label' => 'Foo Bar', 'value' => ['shareWith' => 'foo', 'shareType' => 'user']],
					]
				],
				'users' => [
					['label' => 'FooBar', 'value' => ['shareWith' => 'foobar', 'shareType' => 'user']],
				]], [
					['id' => 'foo', 'label' => 'Foo Bar', 'source' => 'users'],
					['id' => 'foobar', 'label' => 'FooBar', 'source' => 'users'],
				]],
		];
	}

	/**
	 * @dataProvider dataMentions
	 */
	public function testMentions($search, $limit, $result, $expected) {
		$participant = $this->createMock(Participant::class);
		$this->room->expects($this->any())
			->method('getId')
			->willReturn(1234);

		$this->searchPlugin->expects($this->once())
			->method('setContext')
			->with([
				'itemType' => 'chat',
				'itemId' => $this->room->getId(),
				'room' => $this->room,
			]);

		$this->searchResult->expects($this->once())
			->method('asArray')
			->willReturn($result);

		$this->chatManager->expects($this->once())
			->method('addConversationNotify')
			->willReturnArgument(0);

		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($participant);
		$response = $this->controller->mentions($search, $limit);
		$expected = new DataResponse($expected, Http::STATUS_OK);

		$this->assertEquals($expected, $response);
	}
}
