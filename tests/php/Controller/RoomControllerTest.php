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
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\InvitationList;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\InvitationService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomControllerTest extends TestCase {
	private ParticipantService&MockObject $participantService;
	private InvitationService&MockObject $invitationService;
	private BanService&MockObject $banService;
	private IUserManager&MockObject $userManager;
	private IAppManager&MockObject $appManager;
	private SendService&MockObject $sendService;
	private Config&MockObject $talkConfig;
	private IEventDispatcher&MockObject $dispatcher;
	private Room&MockObject $room;
	private Participant&MockObject $participant;
	private IUser&MockObject $user;
	private RoomController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->invitationService = $this->createMock(InvitationService::class);
		$this->banService = $this->createMock(BanService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->sendService = $this->createMock(SendService::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->room = $this->createMock(Room::class);
		$this->participant = $this->createMock(Participant::class);
		$this->user = $this->createMock(IUser::class);
		$this->userManager->method('get')->willReturn($this->user);

		$arguments = [];
		foreach ((new \ReflectionClass(RoomController::class))->getConstructor()->getParameters() as $parameter) {
			$type = (string)$parameter->getType();
			$arguments[] = match ($parameter->getName()) {
				'appName' => 'spreed',
				'userId' => 'alice',
				default => match ($type) {
					ParticipantService::class => $this->participantService,
					InvitationService::class => $this->invitationService,
					BanService::class => $this->banService,
					IUserManager::class => $this->userManager,
					IAppManager::class => $this->appManager,
					SendService::class => $this->sendService,
					Config::class => $this->talkConfig,
					IEventDispatcher::class => $this->dispatcher,
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
		$this->participantService->expects(self::once())->method('updateNotificationLevel')->with($this->participant, Participant::NOTIFY_NEVER);
		$this->sendService->expects(self::once())
			->method('setNotificationLevel')
			->with($this->room, $this->participant, Participant::NOTIFY_NEVER)
			->willThrowException(new SendException('matrix', Http::STATUS_BAD_GATEWAY));

		self::assertSame(Http::STATUS_OK, $this->controller->setNotificationLevel(Participant::NOTIFY_NEVER)->getStatus());
	}

	public function testInvalidNotificationLevelIsNotSentToMatrix(): void {
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$this->participantService->method('updateNotificationLevel')->willThrowException(new \InvalidArgumentException('level'));
		$this->sendService->expects(self::never())->method('setNotificationLevel');

		self::assertSame(Http::STATUS_BAD_REQUEST, $this->controller->setNotificationLevel(Participant::NOTIFY_DEFAULT)->getStatus());
	}

	public function testExternalCallPolicyRemovalIsLogged(): void {
		$this->talkConfig->method('isExternalCallServiceConfigured')->willReturn(true);
		$this->talkConfig->method('getExternalCallService')->willReturn('https://example.com/{meetingId}');
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_EXTERNAL_CALL);
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

	public function testAddParticipantToRoomWithUnsupportedSource(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');
		$this->invitationService->expects(self::never())->method('validateInvitations');
		$this->participantService->expects(self::never())->method('addInvitationList');

		$response = $this->controller->addParticipantToRoom('bob', 'invalid-source');
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'source'], $response->getData());
	}

	public function testAddParticipantToRoomAddsValidatedUser(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');

		$invitedUser = $this->createMock(IUser::class);
		$invitedUser->method('getUID')->willReturn('bob');
		$invitationList = new InvitationList();
		$invitationList->setUserResults(['bob' => $invitedUser], []);

		$this->invitationService->expects(self::once())
			->method('validateInvitations')
			->with(['users' => ['bob']], $this->user, $this->room)
			->willReturn($invitationList);
		$this->participantService->expects(self::once())
			->method('addInvitationList')
			->with($this->room, $invitationList, $this->user);

		$response = $this->controller->addParticipantToRoom('bob');
		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testAddParticipantToRoomWithInvalidUser(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');

		$invitationList = new InvitationList();
		$invitationList->setUserResults([], ['bob']);
		$this->invitationService->method('validateInvitations')->willReturn($invitationList);
		$this->participantService->expects(self::never())->method('addInvitationList');
		$this->banService->expects(self::never())->method('isActorBanned');

		$response = $this->controller->addParticipantToRoom('bob');
		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		self::assertSame(['error' => 'new-participant'], $response->getData());
	}

	public function testAddParticipantToRoomWithBannedUser(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');

		$invitedUser = $this->createMock(IUser::class);
		$invitedUser->method('getUID')->willReturn('bob');
		$invitationList = new InvitationList();
		$invitationList->setUserResults(['bob' => $invitedUser], []);
		$this->invitationService->method('validateInvitations')->willReturn($invitationList);

		$this->banService->expects(self::once())
			->method('isActorBanned')
			->with($this->room, Attendee::ACTOR_USERS, 'bob')
			->willReturn(true);
		$this->participantService->expects(self::never())->method('addInvitationList');

		$response = $this->controller->addParticipantToRoom('bob');
		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['error' => 'ban'], $response->getData());
	}

	public function testAddParticipantToRoomPromotesSelfJoinedUser(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');

		$invitedUser = $this->createMock(IUser::class);
		$invitedUser->method('getUID')->willReturn('bob');
		$invitationList = new InvitationList();
		$invitationList->setUserResults(['bob' => $invitedUser], []);
		$this->invitationService->method('validateInvitations')->willReturn($invitationList);

		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('bob');
		$attendee->setParticipantType(Participant::USER_SELF_JOINED);
		$selfJoined = $this->createMock(Participant::class);
		$selfJoined->method('getAttendee')->willReturn($attendee);
		$this->participantService->method('getParticipantsForRoom')->willReturn([$selfJoined]);

		$this->participantService->expects(self::once())
			->method('updateParticipantType')
			->with($this->room, $selfJoined, Participant::USER);
		// The already existing user is filtered from the invitation list
		$this->participantService->expects(self::never())->method('addInvitationList');

		$response = $this->controller->addParticipantToRoom('bob');
		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testAddParticipantToRoomMapsCirclesToTeams(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');
		$this->appManager->method('isEnabledForUser')->with('circles')->willReturn(true);

		$this->invitationService->expects(self::once())
			->method('validateInvitations')
			->with(['teams' => ['team-id']], $this->user, $this->room)
			->willReturn(new InvitationList());
		$this->participantService->expects(self::never())->method('addInvitationList');

		$response = $this->controller->addParticipantToRoom('team-id', 'circles');
		self::assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testAddParticipantToRoomPhonesWithoutSip(): void {
		$this->room->method('getType')->willReturn(Room::TYPE_GROUP);
		$this->room->method('getObjectType')->willReturn('');
		$this->room->method('isClassified')->willReturn(false);
		$this->talkConfig->method('isSIPConfigured')->willReturn(false);

		$this->invitationService->expects(self::never())->method('validateInvitations');
		$this->participantService->expects(self::never())->method('addInvitationList');

		$response = $this->controller->addParticipantToRoom('+491601231212', 'phones');
		self::assertSame(Http::STATUS_NOT_IMPLEMENTED, $response->getStatus());
		self::assertSame(['error' => 'sip'], $response->getData());
	}
}
