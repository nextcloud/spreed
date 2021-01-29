<?php
/**
 * @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Talk\Tests\php\Chat\SystemMessage;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\SystemMessage\Listener;
use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ListenerTest extends TestCase {
	public const DUMMY_REFERENCE_ID = 'DUMMY_REFERENCE_ID';

	/** @var Listener */
	protected $listener;

	/** @var IRequest|MockObject */
	protected $request;
	/** @var ChatManager|MockObject */
	protected $chatManager;
	/** @var IUserSession|MockObject */
	protected $userSession;
	/** @var TalkSession|MockObject */
	protected $talkSession;
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var IEventDispatcher|MockObject */
	protected $eventDispatcher;
	/** @var array */
	protected $handlers;
	/** @var \DateTime */
	protected $dummyTime;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->request->expects($this->any())
			->method('getParam')
			->with('referenceId')
			->willReturn(self::DUMMY_REFERENCE_ID);

		$this->dummyTime = new \DateTime();

		$this->chatManager = $this->createMock(ChatManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->talkSession = $this->createMock(TalkSession::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getDateTime')->willReturn($this->dummyTime);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);


		$this->handlers = [];

		$this->eventDispatcher->method('addListener')
			->will($this->returnCallback(function ($eventName, $handler) {
				$this->handlers[$eventName][] = $handler;
			}));

		$this->listener = new Listener(
			$this->request,
			$this->chatManager,
			$this->talkSession,
			$this->userSession,
			$this->timeFactory,
		 );

		$this->overwriteService(Listener::class, $this->listener);
		$this->listener->register($this->eventDispatcher);
	}

	public function tearDown(): void {
		$this->restoreService(Listener::class);
		$this->logout();

		parent::tearDown();
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
			->willReturn(Room::ONE_TO_ONE_CALL);

		$participants = [[
			'actorType' => 'users',
			'actorId' => 'alice_actor',
			'participantType' => Participant::USER,
		]];
		$event = new AddParticipantsEvent($room, $participants);

		$this->chatManager->expects($this->never())
			->method('addSystemMessage');

		$this->dispatch(Room::EVENT_AFTER_USERS_ADD, $event);
	}

	public function roomTypesProvider() {
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
			]
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
			// alice_actor adding self-joined mode
			[
				'actorType' => 'users',
				'actorId' => 'alice_actor',
				'participantType' => Participant::USER_SELF_JOINED,
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
				'actorId' => 'alice_actor',
			],
		];

		return [
			[Room::GROUP_CALL, '', $allParticipants, $expectedMessages],
			[Room::PUBLIC_CALL, '', $allParticipants, $expectedMessages],
			[Room::ONE_TO_ONE_CALL, '', $allParticipants, []],
			[Room::GROUP_CALL, 'file', $allParticipants, $expectedMessages],
			[Room::PUBLIC_CALL, 'file', $allParticipants, $expectedMessages],
		];
	}

	/**
	 * @dataProvider roomTypesProvider
	 *
	 * @param int $roomType
	 */
	public function testAfterUsersAdd(int $roomType, string $objectType, array $participants, array $expectedMessages): void {
		$this->mockLoggedInUser('alice_actor');

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn($roomType);
		$room->method('getObjectType')->willReturn($objectType);

		// TODO: add all cases
		$event = new AddParticipantsEvent($room, $participants);

		foreach ($expectedMessages as $index => $expectedMessage) {
			$this->chatManager->expects($this->at($index))
				->method('addSystemMessage')
				->with(
					$room,
					$expectedMessage['actorType'],
					$expectedMessage['actorId'],
					json_encode($expectedMessage['message']),
					$this->dummyTime,
					false,
					self::DUMMY_REFERENCE_ID,
				);
		}

		$this->dispatch(Room::EVENT_AFTER_USERS_ADD, $event);
	}

	public function participantTypeChangeProvider() {
		return [
			[
				Attendee::ACTOR_EMAILS,
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
				[['message' => 'guest_moderator_promoted', 'parameters' => ['session' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_GUESTS,
				Participant::GUEST_MODERATOR,
				Participant::GUEST,
				[['message' => 'guest_moderator_demoted', 'parameters' => ['session' => 'bob_participant']]],
			],
			[
				Attendee::ACTOR_USERS,
				Participant::USER_SELF_JOINED,
				Participant::USER,
				[['message' => 'user_added', 'parameters' => ['user' => 'bob_participant']]],
			],
		];
	}

	/**
	 * @dataProvider participantTypeChangeProvider
	 *
	 * @param int $actorType
	 * @param int $oldParticipantType
	 * @param int $newParticipantType
	 * @param array $expectedMessages
	 */
	public function testAfterParticipantTypeSet(string $actorType, int $oldParticipantType, int $newParticipantType, array $expectedMessages): void {
		$this->mockLoggedInUser('alice_actor');

		$room = $this->createMock(Room::class);
		$room->method('getType')->willReturn(Room::GROUP_CALL);

		$attendee = new Attendee();
		$attendee->setActorId('bob_participant');
		$attendee->setActorType($actorType);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);

		$event = new ModifyParticipantEvent($room, $participant, '', $newParticipantType, $oldParticipantType);

		foreach ($expectedMessages as $index => $expectedMessage) {
			$this->chatManager->expects($this->at($index))
				->method('addSystemMessage')
				->with(
					$room,
					Attendee::ACTOR_USERS,
					'alice_actor',
					json_encode($expectedMessage),
					$this->dummyTime,
					false,
					self::DUMMY_REFERENCE_ID,
				);
		}

		$this->dispatch(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, $event);
	}
}
