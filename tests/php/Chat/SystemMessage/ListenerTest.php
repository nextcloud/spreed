<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat\SystemMessage;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\SystemMessage\Listener;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class ListenerTest extends TestCase {
	public const DUMMY_REFERENCE_ID = 'DUMMY_REFERENCE_ID';

	protected IRequest&MockObject $request;
	protected ChatManager&MockObject $chatManager;
	protected IUserSession&MockObject $userSession;
	protected ISession&MockObject $session;
	protected TalkSession&MockObject $talkSession;
	protected ITimeFactory&MockObject $timeFactory;
	protected IEventDispatcher&MockObject $eventDispatcher;
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected MessageParser&MockObject $messageParser;
	protected LoggerInterface&MockObject $logger;
	protected ?array $handlers = null;
	protected ?\DateTime $dummyTime = null;
	protected ?Listener $listener = null;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->request->expects($this->any())
			->method('getParam')
			->with('referenceId')
			->willReturn(self::DUMMY_REFERENCE_ID);

		$this->dummyTime = new \DateTime();

		$this->chatManager = $this->createMock(ChatManager::class);
		$this->session = $this->createMock(ISession::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn($this->dummyTime);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$l = $this->createMock(IL10N::class);
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});

		$this->handlers = [];

		$this->eventDispatcher->method('addListener')
			->will($this->returnCallback(function ($eventName, $handler): void {
				$this->handlers[$eventName] ??= [];
				$this->handlers[$eventName][] = $handler;
			}));

		$this->listener = new Listener(
			$this->request,
			$this->chatManager,
			$this->talkSession,
			$this->session,
			$this->userSession,
			$this->timeFactory,
			$this->manager,
			$this->participantService,
			$this->messageParser,
			$l,
			$this->logger,
		);
	}

	private function dispatch($eventName, $event) {
		$handlers = $this->handlers[$eventName];
		$this->assertCount(1, $handlers);

		$handlers[0]($event);
	}

	private function mockLoggedInUser($userId): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession
			->method('getUser')
			->willReturn($user);

		return $user;
	}

	public function testAfterUsersAddOneToOne(): void {
		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getType')
			->willReturn(Room::TYPE_ONE_TO_ONE);

		$participants = [[
			'actorType' => 'users',
			'actorId' => 'alice_actor',
			'participantType' => Participant::USER,
		]];
		$attendees = array_map(static fn (array $participant) => Attendee::fromParams($participant), $participants);
		$event = new AttendeesAddedEvent($room, $attendees);

		$this->chatManager->expects($this->never())
			->method('addSystemMessage');

		self::invokePrivate($this->listener, 'handle', [$event]);
	}

	public static function dataRoomTypes(): array {
		$expectedMessages = [
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'message' => ['message' => 'user_added', 'parameters' => ['user' => 'alice_actor']],
			],
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'message' => ['message' => 'user_added', 'parameters' => ['user' => 'bob']],
			],
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'message' => ['message' => 'user_added', 'parameters' => ['user' => 'carmen']],
			],
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'message' => ['message' => 'user_added', 'parameters' => ['user' => 'delta']],
			],
		];

		$allParticipants = [
			// guest will be ignored
			[
				'actorType' => 'guests'
			],
			// alice_actor adding self to listed channel
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'participantType' => Participant::USER,
			],
			// alice_actor added bob
			[
				'actorType' => 'users',
				'actorId' => 'bob',
				'participantType' => Participant::USER,
			],
			// empty participant type
			[
				'actorType' => 'users',
				'actorId' => 'carmen',
			],
			// alice_actor adding self-joined mode
			[
				'actorType' => 'users',
				'actorId' => 'delta',
				'participantType' => Participant::USER_SELF_JOINED,
			],
		];

		return [
			[Room::TYPE_GROUP, '', $allParticipants, $expectedMessages],
			[Room::TYPE_PUBLIC, '', $allParticipants, $expectedMessages],
			[Room::TYPE_ONE_TO_ONE, '', $allParticipants, []],
			[Room::TYPE_GROUP, 'file', $allParticipants, $expectedMessages],
			[Room::TYPE_PUBLIC, 'file', $allParticipants, $expectedMessages],
		];
	}

	/**
	 * @dataProvider dataRoomTypes
	 *
	 * @param int $roomType
	 */
	public function testAfterUsersAdd(int $roomType, string $objectType, array $participants, array $expectedMessages): void {
		$this->mockLoggedInUser('alice_actor');

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn($roomType);
		$room->method('getObjectType')->willReturn($objectType);

		$attendees = array_map(static fn (array $participant) => Attendee::fromParams($participant), $participants);

		// TODO: add all cases
		$event = new AttendeesAddedEvent($room, $attendees);

		$consecutive = [];
		foreach ($expectedMessages as $expectedMessage) {
			$consecutive[] = [
				$room,
				$expectedMessage['actorType'],
				$expectedMessage['actorId'],
				json_encode($expectedMessage['message']),
				$this->dummyTime,
				false,
				self::DUMMY_REFERENCE_ID,
				null,
				false,
				false,
			];
		}
		if (!empty($consecutive)) {
			$i = 0;
			$this->chatManager->expects($this->exactly(count($consecutive)))
				->method('addSystemMessage')
				->willReturnCallback(function () use ($consecutive, &$i) {
					$this->assertArrayHasKey($i, $consecutive);
					$this->assertSame($consecutive[$i], func_get_args());
					$i++;
					return $this->createMock(IComment::class);
				});
		} else {
			$this->chatManager->expects($this->never())
				->method('addSystemMessage');
		}

		self::invokePrivate($this->listener, 'handle', [$event]);
	}

	public static function dataParticipantTypeChange(): array {
		return [
			[
				Attendee::ACTOR_GROUPS,
				Participant::USER,
				Participant::MODERATOR,
				[],
			],
			[
				Attendee::ACTOR_USERS,
				Participant::USER,
				Participant::MODERATOR,
				[['message' => 'moderator_promoted', 'parameters' => ['user' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_USERS,
				Participant::MODERATOR,
				Participant::USER,
				[['message' => 'moderator_demoted', 'parameters' => ['user' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_GUESTS,
				Participant::GUEST,
				Participant::GUEST_MODERATOR,
				[['message' => 'guest_moderator_promoted', 'parameters' => ['type' => 'guests', 'id' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_GUESTS,
				Participant::GUEST_MODERATOR,
				Participant::GUEST,
				[['message' => 'guest_moderator_demoted', 'parameters' => ['type' => 'guests', 'id' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_EMAILS,
				Participant::GUEST,
				Participant::GUEST_MODERATOR,
				[['message' => 'guest_moderator_promoted', 'parameters' => ['type' => 'emails', 'id' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_EMAILS,
				Participant::GUEST_MODERATOR,
				Participant::GUEST,
				[['message' => 'guest_moderator_demoted', 'parameters' => ['type' => 'emails', 'id' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_USERS,
				Participant::USER_SELF_JOINED,
				Participant::USER,
				[['message' => 'user_added', 'parameters' => ['user' => 'bob_participant']]],
			],
		];
	}

	#[DataProvider('dataParticipantTypeChange')]
	public function testAfterParticipantTypeSet(string $actorType, int $oldParticipantType, int $newParticipantType, array $expectedMessages): void {
		$this->mockLoggedInUser('alice_actor');

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);

		$attendee = new Attendee();
		$attendee->setActorId('bob_participant');
		$attendee->setActorType($actorType);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);

		$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_TYPE, $newParticipantType, $oldParticipantType);

		foreach ($expectedMessages as $expectedMessage) {
			$consecutive[] = [
				$room,
				Attendee::ACTOR_USERS,
				'alice_actor',
				json_encode($expectedMessage),
				$this->dummyTime,
				false,
				self::DUMMY_REFERENCE_ID,
				null,
				false,
				false,
			];
		}
		if (isset($consecutive)) {
			$i = 0;
			$this->chatManager->expects($this->exactly(count($consecutive)))
				->method('addSystemMessage')
				->willReturnCallback(function () use ($consecutive, &$i) {
					$this->assertArrayHasKey($i, $consecutive);
					$this->assertSame($consecutive[$i], func_get_args());
					$i++;
					return $this->createMock(IComment::class);
				});
		} else {
			$this->chatManager->expects($this->never())
				->method('addSystemMessage');
		}

		self::invokePrivate($this->listener, 'handle', [$event]);
	}

	public static function dataCallRecordingChange(): array {
		return [
			[
				Room::RECORDING_VIDEO_STARTING,
				Room::RECORDING_NONE,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_VIDEO_STARTING,
				Room::RECORDING_NONE,
				Attendee::ACTOR_USERS,
				'alice',
				null,
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_VIDEO_STARTING,
				null,
				null,
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_VIDEO_STARTING,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_NONE,
				null,
				null,
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_NONE,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO_STARTING,
				Room::RECORDING_NONE,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_AUDIO_STARTING,
				Room::RECORDING_NONE,
				Attendee::ACTOR_USERS,
				'alice',
				null,
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_AUDIO_STARTING,
				null,
				null,
				['message' => 'audio_recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_AUDIO_STARTING,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'audio_recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_NONE,
				null,
				null,
				['message' => 'audio_recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_NONE,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'audio_recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_VIDEO_STARTING,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_VIDEO_STARTING,
				Attendee::ACTOR_USERS,
				'bob',
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_VIDEO,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_VIDEO,
				Attendee::ACTOR_USERS,
				'bob',
				['message' => 'recording_stopped', 'parameters' => []],
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_AUDIO_STARTING,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_AUDIO_STARTING,
				Attendee::ACTOR_USERS,
				'bob',
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_AUDIO,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_NONE,
				Room::RECORDING_AUDIO,
				Attendee::ACTOR_USERS,
				'bob',
				['message' => 'audio_recording_stopped', 'parameters' => []],
			],
			[
				Room::RECORDING_FAILED,
				Room::RECORDING_VIDEO_STARTING,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_FAILED,
				Room::RECORDING_AUDIO_STARTING,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_FAILED,
				Room::RECORDING_VIDEO,
				null,
				null,
				['message' => 'recording_failed', 'parameters' => []],
			],
			[
				Room::RECORDING_FAILED,
				Room::RECORDING_AUDIO,
				null,
				null,
				['message' => 'recording_failed', 'parameters' => []],
			],
			[
				Room::RECORDING_VIDEO_STARTING,
				Room::RECORDING_FAILED,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_VIDEO_STARTING,
				Room::RECORDING_FAILED,
				Attendee::ACTOR_USERS,
				'alice',
				null,
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_FAILED,
				null,
				null,
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_VIDEO,
				Room::RECORDING_FAILED,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO_STARTING,
				Room::RECORDING_FAILED,
				null,
				null,
				null,
			],
			[
				Room::RECORDING_AUDIO_STARTING,
				Room::RECORDING_FAILED,
				Attendee::ACTOR_USERS,
				'alice',
				null,
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_FAILED,
				null,
				null,
				['message' => 'audio_recording_started', 'parameters' => []],
			],
			[
				Room::RECORDING_AUDIO,
				Room::RECORDING_FAILED,
				Attendee::ACTOR_USERS,
				'alice',
				['message' => 'audio_recording_started', 'parameters' => []],
			],
		];
	}

	#[DataProvider('dataCallRecordingChange')]
	public function testAfterCallRecordingSet(int $newStatus, int $oldStatus, ?string $actorType, ?string $actorId, ?array $expectedMessage): void {
		$this->mockLoggedInUser('logged_in_user');

		$room = $this->createMock(Room::class);
		$room->expects($this->any())
			->method('getType')
			->willReturn(Room::TYPE_PUBLIC);

		if ($actorType !== null && $actorId !== null) {
			$attendee = new Attendee();
			$attendee->setActorType($actorType);
			$attendee->setActorId($actorId);

			$participant = $this->createMock(Participant::class);
			$participant->method('getAttendee')->willReturn($attendee);

			$expectedActorType = $actorType;
			$expectedActorId = $actorId;
		} else {
			$participant = null;

			$expectedActorType = Attendee::ACTOR_USERS;
			$expectedActorId = 'logged_in_user';
		}

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $newStatus, $oldStatus, $participant);

		if ($expectedMessage !== null) {
			$this->chatManager->expects($this->once())
				->method('addSystemMessage')
				->with(
					$room,
					$expectedActorType,
					$expectedActorId,
					json_encode($expectedMessage),
					$this->dummyTime,
					false,
					self::DUMMY_REFERENCE_ID,
					null,
					false
				);
		} else {
			$this->chatManager->expects($this->never())
				->method('addSystemMessage');
		}

		self::invokePrivate($this->listener, 'handle', [$event]);
	}
}
