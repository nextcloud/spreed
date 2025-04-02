<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Chat\AutoComplete\SearchPlugin;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\Notifier;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Controller\ChatController;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\GuestManager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\SessionService;
use OCA\Talk\Share\Helper\FilesMetadataCache;
use OCA\Talk\Share\RoomShareProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\RichObjectStrings\IRichTextFormatter;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\ITrustedDomainHelper;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\UserStatus\IManager as IUserStatusManager;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ChatControllerTest extends TestCase {
	private ?string $userId = null;
	protected IUserManager&MockObject $userManager;
	protected IAppManager&MockObject $appManager;
	protected ChatManager&MockObject $chatManager;
	private RoomFormatter&MockObject $roomFormatter;
	protected ReactionManager&MockObject $reactionManager;
	protected ParticipantService&MockObject $participantService;
	protected SessionService&MockObject $sessionService;
	protected AttachmentService&MockObject $attachmentService;
	protected AvatarService&MockObject $avatarService;
	protected ReminderService&MockObject $reminderService;
	protected GuestManager&MockObject $guestManager;
	protected MessageParser&MockObject $messageParser;
	protected RoomShareProvider&MockObject $roomShareProvider;
	protected FilesMetadataCache&MockObject $filesMetadataCache;
	protected IManager&MockObject $autoCompleteManager;
	protected IUserStatusManager&MockObject $statusManager;
	protected MatterbridgeManager&MockObject $matterbridgeManager;
	protected BotService&MockObject $botService;
	protected SearchPlugin&MockObject $searchPlugin;
	protected ISearchResult&MockObject $searchResult;
	protected IEventDispatcher&MockObject $eventDispatcher;
	protected ITimeFactory&MockObject $timeFactory;
	protected IValidator&MockObject $richObjectValidator;
	protected ITrustedDomainHelper&MockObject $trustedDomainHelper;
	protected IL10N&MockObject $l;
	private Authenticator&MockObject $federationAuthenticator;
	private ProxyCacheMessageService&MockObject $pcmService;
	private Notifier&MockObject $notifier;
	private IRichTextFormatter&MockObject $richTextFormatter;
	private ITaskProcessingManager&MockObject $taskProcessingManager;
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;

	protected Room&MockObject $room;

	private ?ChatController $controller = null;

	/** @var Callback */
	private $newMessageDateTimeConstraint;

	public function setUp(): void {
		parent::setUp();

		$this->userId = 'testUser';
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->roomFormatter = $this->createMock(RoomFormatter::class);
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
		$this->botService = $this->createMock(BotService::class);
		$this->searchPlugin = $this->createMock(SearchPlugin::class);
		$this->searchResult = $this->createMock(ISearchResult::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->richObjectValidator = $this->createMock(IValidator::class);
		$this->trustedDomainHelper = $this->createMock(ITrustedDomainHelper::class);
		$this->l = $this->createMock(IL10N::class);
		$this->federationAuthenticator = $this->createMock(Authenticator::class);
		$this->pcmService = $this->createMock(ProxyCacheMessageService::class);
		$this->notifier = $this->createMock(Notifier::class);
		$this->richTextFormatter = $this->createMock(IRichTextFormatter::class);
		$this->taskProcessingManager = $this->createMock(ITaskProcessingManager::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

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
			$this->roomFormatter,
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
			$this->botService,
			$this->searchPlugin,
			$this->searchResult,
			$this->timeFactory,
			$this->eventDispatcher,
			$this->richObjectValidator,
			$this->trustedDomainHelper,
			$this->l,
			$this->federationAuthenticator,
			$this->pcmService,
			$this->notifier,
			$this->richTextFormatter,
			$this->taskProcessingManager,
			$this->appConfig,
			$this->logger,
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

	public function testSendMessageByUser(): void {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment&MockObject $comment */
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

	public function testSendMessageByUserWithReferenceId(): void {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment&MockObject $comment */
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

	public function testSendReplyByUser(): void {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);

		/** @var IComment&MockObject $comment */
		$parent = $this->newComment(23, 'users', $this->userId . '2', $date, 'testMessage original');

		/** @var IComment&MockObject $comment */
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
			[$parentMessage, false],
			[$chatMessage, false],
		];
		$this->messageParser->expects($this->exactly(2))
			->method('parseMessage')
			->willReturnCallback(function () use ($expectedCalls, &$i): void {
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

	public function testSendReplyByUserToNotReplyable(): void {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		/** @var IComment&MockObject $comment */
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
		$expected = new DataResponse(['error' => 'reply-to'], Http::STATUS_BAD_REQUEST);

		$this->assertEquals($expected, $response);
	}

	public function testSendMessageByUserNotJoinedButInRoom(): void {
		$participant = $this->createMock(Participant::class);

		$date = new \DateTime();
		$this->timeFactory->expects($this->once())
			->method('getDateTime')
			->willReturn($date);
		/** @var IComment&MockObject $comment */
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

	public function testSendMessageByGuest(): void {
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
		/** @var IComment&MockObject $comment */
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
		], Http::STATUS_CREATED, [
			'X-Chat-Last-Common-Read' => '0',
		]);

		$this->assertEquals($expected, $response);
	}

	public function testShareObjectToChatByUser(): void {
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
		/** @var IComment&MockObject $comment */
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

	public function testReceiveHistoryByUser(): void {
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

	public function testReceiveMessagesByUserNotJoinedButInRoom(): void {
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

	public function testReceiveMessagesByGuest(): void {
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

	public function testWaitForNewMessagesByUser(): void {
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

	public function testWaitForNewMessagesTimeoutExpired(): void {
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
		$expected = new DataResponse(null, Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public function testWaitForNewMessagesTimeoutTooLarge(): void {
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
		$expected = new DataResponse(null, Http::STATUS_NOT_MODIFIED);

		$this->assertEquals($expected, $response);
	}

	public static function dataMentions(): array {
		return [
			['tes', 10, ['exact' => []], []],
			[
				'foo',
				20,
				[
					'exact' => [
						'users' => [
							['label' => 'Foo Bar', 'value' => ['shareWith' => 'foo', 'shareType' => 'user']],
						]
					],
					'users' => [
						['label' => 'FooBar', 'value' => ['shareWith' => 'foobar', 'shareType' => 'user']],
					],
					'federated_users' => [
						['label' => 'Fedi User', 'value' => ['shareWith' => 'foobar@example.tld', 'shareType' => 'federated_user']],
					]
				],
				[
					['id' => 'foo', 'label' => 'Foo Bar', 'source' => 'users', 'mentionId' => 'foo'],
					['id' => 'foobar', 'label' => 'FooBar', 'source' => 'users', 'mentionId' => 'foobar'],
					['id' => 'foobar@example.tld', 'label' => 'Fedi User', 'source' => 'federated_users', 'mentionId' => 'federated_user/foobar@example.tld'],
				]
			],
		];
	}

	/**
	 * @dataProvider dataMentions
	 */
	public function testMentions(string $search, int $limit, array $result, array $expected): void {
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
