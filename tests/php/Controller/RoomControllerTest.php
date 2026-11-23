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
use OCA\Talk\Exceptions\ParticipantProperty\ParticipantTypeException;
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
			// Added to the team as the moderator, and to the conversation right away
			'user' => ['users', 'added', Http::STATUS_OK, null],
			// Invite-only team: added by the resync after accepting
			'user of an invite-only team' => ['users', 'invited', Http::STATUS_OK, null],
			'team refused it' => ['users', 'refused', Http::STATUS_BAD_REQUEST, 'team'],
			'banned user' => ['users', 'banned', Http::STATUS_BAD_REQUEST, 'ban'],
			'email address' => ['emails', '', Http::STATUS_BAD_REQUEST, 'source'],
			'group' => ['groups', '', Http::STATUS_BAD_REQUEST, 'source'],
			'team' => ['teams', '', Http::STATUS_BAD_REQUEST, 'source'],
			'federated user' => ['federated_users', '', Http::STATUS_BAD_REQUEST, 'source'],
			'phone number' => ['phones', '', Http::STATUS_BAD_REQUEST, 'source'],
		];
	}

	#[DataProvider('dataAddToTeamRoom')]
	public function testAddingToTeamRoomsAddsToTheTeam(string $source, string $result, int $status, ?string $error): void {
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$bob = $this->makeUser('bob');
		$this->userManager->method('get')->with('bob')->willReturn($bob);
		$adding = $this->teamRoomSyncService->expects($source === 'users' ? $this->once() : $this->never())
			->method('addToTeamRoom')
			->with($this->room, $bob, 'alice');
		match ($result) {
			'added' => $adding->willReturn($this->createMock(Participant::class)),
			'invited' => $adding->willReturn(null),
			'refused' => $adding->willThrowException(new \Exception('Insufficient rights')),
			'banned' => $adding->willThrowException(new ForbiddenException('actor')),
			default => null,
		};
		$this->participantService->expects($this->never())->method('addUsers');
		$this->participantService->expects($this->never())->method('inviteEmailAddress');
		$this->participantService->expects($this->never())->method('addGroup');

		$response = $this->controller->addParticipantToRoom('bob', $source);

		self::assertSame($status, $response->getStatus());
		if ($error !== null) {
			self::assertSame(['error' => $error], $response->getData());
		}
	}

	public static function dataRemoveFromTeamRoom(): array {
		return [
			// Removed from the team as the moderator, Circles decides
			'member of the team' => [Attendee::ACTOR_USERS, 'bob', 'removeFromTeamRoom', Http::STATUS_OK],
			'federated member of the team' => [Attendee::ACTOR_FEDERATED_USERS, 'bob@remote.example', 'removeFederatedFromTeamRoom', Http::STATUS_OK],
			// Email guests are removed in the Teams app for now
			'email member of the team' => [Attendee::ACTOR_EMAILS, 'hash', null, Http::STATUS_BAD_REQUEST],
		];
	}

	#[DataProvider('dataRemoveFromTeamRoom')]
	public function testRemovingFromTeamRoomsRemovesFromTheTeam(string $actorType, string $actorId, ?string $teamMethod, int $status): void {
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$attendee = Attendee::fromRow(['id' => 2, 'actor_type' => $actorType, 'actor_id' => $actorId, 'participant_type' => Participant::USER]);
		$target = $this->createMock(Participant::class);
		$target->method('getAttendee')->willReturn($attendee);
		$target->method('isOwner')->willReturn(false);
		$this->participantService->method('getParticipantByAttendeeId')->with($this->room, 2)->willReturn($target);
		// One lookup in Circles only: users and federated users are checked when they are removed from the team
		$this->teamRoomSyncService->expects($teamMethod === null ? $this->once() : $this->never())
			->method('isMemberOfTeam')
			->with($this->room, $attendee)
			->willReturn(true);

		foreach (['removeFromTeamRoom', 'removeFederatedFromTeamRoom'] as $method) {
			$expectation = $this->teamRoomSyncService->expects($method === $teamMethod ? $this->once() : $this->never())->method($method);
			if ($method === $teamMethod) {
				// Also stale participants that are no member anymore: only removed from the room then
				$expectation->with($this->room, $actorId, 'alice');
			}
		}
		$this->participantService->expects($status === Http::STATUS_OK ? $this->once() : $this->never())->method('removeAttendee');

		self::assertSame($status, $this->controller->removeAttendeeFromRoom(2)->getStatus());
	}

	public function testFederatedModeratorsCanNotRemoveMembersOfTheTeam(): void {
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_FEDERATED_USERS, 'actor_id' => 'carol@remote.example']));
		$target = $this->createMock(Participant::class);
		$target->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 2, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'bob']));
		$this->participantService->method('getParticipantByAttendeeId')->willReturn($target);
		$this->teamRoomSyncService->method('isMemberOfTeam')->willReturn(true);

		$this->teamRoomSyncService->expects($this->never())->method('removeFromTeamRoom');
		$this->participantService->expects($this->never())->method('removeAttendee');

		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller->removeAttendeeFromRoom(2)->getStatus());
	}

	public function testTeamRefusingTheRemovalKeepsTheParticipant(): void {
		// E.g. a team moderator removing a team admin
		$this->inTeamRoom();
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$target = $this->createMock(Participant::class);
		$target->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 2, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'bob']));
		$this->participantService->method('getParticipantByAttendeeId')->willReturn($target);
		$this->teamRoomSyncService->method('isMemberOfTeam')->willReturn(true);
		$this->teamRoomSyncService->method('removeFromTeamRoom')->willThrowException(new \Exception('Insufficient rights'));

		$this->participantService->expects($this->never())->method('removeAttendee');

		$response = $this->controller->removeAttendeeFromRoom(2);
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'team'], $response->getData());
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

	private function mockTarget(string $actorType, string $actorId): Participant&MockObject {
		$target = $this->createMock(Participant::class);
		$target->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 2, 'actor_type' => $actorType, 'actor_id' => $actorId, 'participant_type' => Participant::USER]));
		$this->participantService->method('getParticipantByAttendeeId')->with($this->room, 2)->willReturn($target);
		return $target;
	}

	public function testRolesOfUsersAreChangedInTheTeam(): void {
		$this->inTeamRoom();
		$target = $this->mockTarget(Attendee::ACTOR_USERS, 'bob');
		$this->teamRoomSyncService->expects($this->once())
			->method('changeRoleInTeam')
			->with($this->room, $this->participant, $target, true, Participant::OWNER);
		$this->participantService->expects($this->never())->method('updateParticipantTypeByModerator');

		self::assertSame(Http::STATUS_OK, $this->controller->promoteModerator(2, Participant::OWNER)->getStatus());
	}

	public function testUnexpectedErrorsInOtherConversationsAreNotReportedAsTeamError(): void {
		$this->room->method('getObjectType')->willReturn('');
		$this->room->method('isTeamManaged')->willReturn(false);
		$this->mockTarget(Attendee::ACTOR_USERS, 'bob');
		$this->teamRoomSyncService->expects($this->never())->method('changeRoleInTeam');
		$this->participantService->method('updateParticipantTypeByModerator')->willThrowException(new \RuntimeException('Deadlock'));

		$this->expectException(\RuntimeException::class);
		$this->controller->promoteModerator(2);
	}

	public static function dataRoleChangeRefused(): array {
		return [
			'refused by the team' => [new \Exception('Insufficient rights'), Http::STATUS_BAD_REQUEST, 'team'],
			'refused by Talk' => [new ParticipantTypeException(ParticipantTypeException::REASON_MODERATOR), Http::STATUS_FORBIDDEN, 'moderator'],
			'no admin to take over the team' => [new ParticipantTypeException(ParticipantTypeException::REASON_LAST_MODERATOR), Http::STATUS_BAD_REQUEST, 'last-moderator'],
		];
	}

	#[DataProvider('dataRoleChangeRefused')]
	public function testRefusedRoleChange(\Exception $exception, int $status, string $error): void {
		$this->inTeamRoom();
		$this->mockTarget(Attendee::ACTOR_USERS, 'bob');
		$this->teamRoomSyncService->method('changeRoleInTeam')->willThrowException($exception);

		$response = $this->controller->demoteModerator(2);

		self::assertSame($status, $response->getStatus());
		self::assertSame(['error' => $error], $response->getData());
	}

	public function testRolesOfFederatedUsersStayInTalk(): void {
		$this->inTeamRoom();
		$this->mockTarget(Attendee::ACTOR_FEDERATED_USERS, 'bob@remote.example');
		$this->teamRoomSyncService->expects($this->never())->method('changeRoleInTeam');
		$this->participantService->expects($this->once())->method('updateParticipantTypeByModerator');

		$this->controller->promoteModerator(2);
	}

	public static function dataTeamSettings(): array {
		return [
			'rename' => ['renameRoom', ['New name']],
			'description' => ['setDescription', ['New description']],
			'listing' => ['setListable', [Room::LISTABLE_USERS]],
			'delete' => ['deleteRoom', []],
		];
	}

	#[DataProvider('dataTeamSettings')]
	public function testOnlyTeamAdminsChangeTeamSettings(string $method, array $arguments): void {
		$this->inTeamRoom();
		// A Talk moderator is a team moderator, not a team admin
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow(['id' => 1, 'actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice', 'participant_type' => Participant::MODERATOR]));

		$response = $this->controller->$method(...$arguments);

		self::assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		self::assertSame(['error' => 'team'], $response->getData());
	}
}
