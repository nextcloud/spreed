<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OCA\Talk\Config;
use OCA\Talk\Controller\RoomController;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomControllerTest extends TestCase {
	private ParticipantService&MockObject $participantService;
	private SendService&MockObject $sendService;
	private Config&MockObject $talkConfig;
	private IEventDispatcher&MockObject $dispatcher;
	private TeamRoomSyncService&MockObject $teamRoomSyncService;
	private IUserManager&MockObject $userManager;
	private Room&MockObject $room;
	private Participant&MockObject $participant;
	private RoomController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->sendService = $this->createMock(SendService::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->teamRoomSyncService = $this->createMock(TeamRoomSyncService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->room = $this->createMock(Room::class);
		$this->participant = $this->createMock(Participant::class);

		$arguments = [];
		foreach ((new \ReflectionClass(RoomController::class))->getConstructor()->getParameters() as $parameter) {
			$type = (string)$parameter->getType();
			$arguments[] = match ($parameter->getName()) {
				'appName' => 'spreed',
				'userId' => 'alice',
				default => match ($type) {
					ParticipantService::class => $this->participantService,
					SendService::class => $this->sendService,
					Config::class => $this->talkConfig,
					IEventDispatcher::class => $this->dispatcher,
					TeamRoomSyncService::class => $this->teamRoomSyncService,
					IUserManager::class => $this->userManager,
					default => $this->createMock($type),
				},
			};
		}
		$this->controller = new RoomController(...$arguments);
		$this->controller->setRoom($this->room);
		$this->controller->setParticipant($this->participant);
	}

	public function testNotificationLevelOfMatrixConversation(): void {
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$this->room->method('isTeamManaged')->willReturn(false);
		$this->participantService->expects(self::once())->method('updateNotificationLevel')->with($this->participant, Participant::NOTIFY_NEVER);
		$this->sendService->expects(self::once())
			->method('setNotificationLevel')
			->with($this->room, $this->participant, Participant::NOTIFY_NEVER)
			->willThrowException(new SendException('matrix', Http::STATUS_BAD_GATEWAY));

		self::assertSame(Http::STATUS_OK, $this->controller->setNotificationLevel(Participant::NOTIFY_NEVER)->getStatus());
	}

	public function testInvalidNotificationLevelIsNotSentToMatrix(): void {
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$this->room->method('isTeamManaged')->willReturn(false);
		$this->participantService->method('updateNotificationLevel')->willThrowException(new \InvalidArgumentException('level'));
		$this->sendService->expects(self::never())->method('setNotificationLevel');

		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller->setNotificationLevel(Participant::NOTIFY_DEFAULT)->getStatus());
	}

	public function testExternalCallPolicyRemovalIsLogged(): void {
		$this->talkConfig->method('isExternalCallServiceConfigured')->willReturn(true);
		$this->talkConfig->method('getExternalCallService')->willReturn('https://example.com/{meetingId}');
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EXTERNAL_CALL);
		$this->room->method('isTeamManaged')->willReturn(false);
		$this->room->method('getObjectId')->willReturn('meeting');
		$this->room->method('getToken')->willReturn('abcdef');
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('alice');
		$this->participant->method('getAttendee')->willReturn($attendee);

		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(new ClientException('forbidden', new Request('GET', 'https://example.com/meeting'), new Response(Http::STATUS_FORBIDDEN)));
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$this->overwriteService(IClientService::class, $clientService);

		$this->participantService->expects(self::once())->method('removeAttendee');
		$this->dispatcher->expects(self::once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.external_call_policy.participant_removed'
				&& $event->getParameters() === ['actorType' => 'users', 'actorId' => 'alice', 'token' => 'abcdef', 'objectId' => 'meeting']));

		try {
			self::assertSame(Http::STATUS_FORBIDDEN, $this->controller->getExternalCallUrl()->getStatus());
		} finally {
			$this->restoreService(IClientService::class);
		}
	}

	private function inTeamRoom(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_TEAM);
		$this->room->method('isTeamManaged')->willReturn(true);
		$this->room->method('getToken')->willReturn('teamroom');
	}

	private function makeUser(string $userId): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		return $user;
	}

	public static function dataAddToTeamRoom(): array {
		return [
			'member of the team' => ['users', true, Http::STATUS_OK, null],
			'no member of the team' => ['users', false, Http::STATUS_BAD_REQUEST, 'team'],
			'email address' => ['emails', true, Http::STATUS_BAD_REQUEST, 'source'],
			'group' => ['groups', true, Http::STATUS_BAD_REQUEST, 'source'],
			'team' => ['teams', true, Http::STATUS_BAD_REQUEST, 'source'],
			'federated user' => ['federated_users', true, Http::STATUS_BAD_REQUEST, 'source'],
			'phone number' => ['phones', true, Http::STATUS_BAD_REQUEST, 'source'],
		];
	}

	#[DataProvider('dataAddToTeamRoom')]
	public function testOnlyMembersOfTheTeamAreAddedToTeamRooms(string $source, bool $isMember, int $status, ?string $error): void {
		$this->inTeamRoom();
		$bob = $this->makeUser('bob');
		$this->userManager->method('get')->with('bob')->willReturn($bob);
		$this->teamRoomSyncService->expects($source === 'users' ? $this->once() : $this->never())
			->method('syncUserInTeamRoom')
			->with($this->room, $bob)
			->willReturn($isMember ? $this->createMock(Participant::class) : null);
		$this->participantService->expects($this->never())->method('addUsers');
		$this->participantService->expects($this->never())->method('inviteEmailAddress');
		$this->participantService->expects($this->never())->method('addGroup');

		$response = $this->controller->addParticipantToRoom('bob', $source);

		self::assertSame($status, $response->getStatus());
		if ($error !== null) {
			self::assertSame(['error' => $error], $response->getData());
		}
	}

	public function testBannedMemberIsNotAddedToTeamRoom(): void {
		$this->inTeamRoom();
		$this->userManager->method('get')->willReturn($this->makeUser('bob'));
		$this->teamRoomSyncService->method('syncUserInTeamRoom')->willThrowException(new ForbiddenException('actor'));

		$response = $this->controller->addParticipantToRoom('bob');

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'ban'], $response->getData());
	}

	public static function dataRemoveFromTeamRoom(): array {
		return [
			// Removed again by the resync otherwise: removing from the team is done in the Teams app
			'still a member of the team' => [true, Http::STATUS_BAD_REQUEST],
			// E.g. a participant the resync did not remove yet
			'no member of the team anymore' => [false, Http::STATUS_OK],
		];
	}

	#[DataProvider('dataRemoveFromTeamRoom')]
	public function testOnlyFormerTeamMembersAreRemovedFromTeamRooms(bool $isMember, int $status): void {
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$attendee = Attendee::fromRow(['id' => 2, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'bob', 'participant_type' => Participant::USER]);
		$target = $this->createMock(Participant::class);
		$target->method('getAttendee')->willReturn($attendee);
		$target->method('isOwner')->willReturn(false);
		$this->participantService->method('getParticipantByAttendeeId')->with($this->room, 2)->willReturn($target);
		$this->teamRoomSyncService->method('isMemberOfTeam')->with($this->room, $attendee)->willReturn($isMember);

		$this->participantService->expects($isMember ? $this->never() : $this->once())->method('removeAttendee');

		self::assertSame($status, $this->controller->removeAttendeeFromRoom(2)->getStatus());
	}

	public function testLeavingTheTeamRoomLeavesTheTeam(): void {
		$this->inTeamRoom();
		$attendee = Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice', 'participant_type' => Participant::OWNER]);
		$this->participant->method('getAttendee')->willReturn($attendee);
		$this->participant->method('hasModeratorPermissions')->willReturn(true);
		$alice = $this->makeUser('alice');
		$this->userManager->method('get')->willReturn($alice);
		// The only owner can leave: Circles picks the new owner of the team
		$this->participantService->method('getNumberOfUsers')->willReturn(3);
		$this->participantService->method('getNumberOfModerators')->willReturn(1);

		$this->teamRoomSyncService->expects($this->once())->method('leaveTeamOfRoom')->with($this->room, 'alice');
		$this->participantService->expects($this->once())->method('removeUser')->with($this->room, $alice, 'leave');

		self::assertSame(Http::STATUS_OK, $this->controller->removeSelfFromRoom()->getStatus());
	}

	public function testLastUserLeavingDoesNotDeleteTheTeamRoom(): void {
		// e.g. the owner leaves before the resync added the other members of the team
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice', 'participant_type' => Participant::OWNER]));
		$alice = $this->makeUser('alice');
		$this->userManager->method('get')->willReturn($alice);
		$this->participantService->method('getNumberOfUsers')->willReturn(1);

		$this->roomService->expects($this->never())->method('deleteRoom');
		$this->participantService->expects($this->once())->method('removeUser')->with($this->room, $alice, 'leave');

		self::assertSame(Http::STATUS_OK, $this->controller->removeSelfFromRoom()->getStatus());
	}

	public function testTeamRefusingTheLeaveKeepsTheParticipant(): void {
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice', 'participant_type' => Participant::USER]));
		$this->teamRoomSyncService->method('leaveTeamOfRoom')->willThrowException(new \Exception('Circles App unavailable'));

		$this->participantService->expects($this->never())->method('removeUser');

		$response = $this->controller->removeSelfFromRoom();

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'team'], $response->getData());
	}
}
