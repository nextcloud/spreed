<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberEditedEvent;
use OCA\Circles\Events\EditingCircleMemberEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\FederatedEvent;
use OCA\Circles\Model\Member;
use OCA\Circles\Tools\Model\SimpleDataStore;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Listener\TeamRoomListener;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamRoomListenerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected TeamRoomSyncService&MockObject $syncService;
	protected LoggerInterface&MockObject $logger;
	protected TeamRoomListener $listener;
	protected Room&MockObject $room;
	protected Member&MockObject $member;
	protected ISession&MockObject $session;
	protected IUserSession&MockObject $userSession;
	/** @var array<string, mixed> Values of the session */
	protected array $sessionValues = [];

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->syncService = $this->createMock(TeamRoomSyncService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->session = $this->createMock(ISession::class);
		$this->session->method('get')->willReturnCallback(fn (string $key): mixed => $this->sessionValues[$key] ?? null);
		$this->session->method('set')->willReturnCallback(function (string $key, mixed $value): void {
			$this->sessionValues[$key] = $value;
		});
		$this->session->method('remove')->willReturnCallback(function (string $key): void {
			unset($this->sessionValues[$key]);
		});
		$this->userSession = $this->createMock(IUserSession::class);
		$this->listener = new TeamRoomListener($this->manager, $this->syncService, $this->logger, $this->session, $this->userSession);

		$this->room = $this->createMock(Room::class);
		$this->room->method('getToken')->willReturn('teamroom');
		// A user of this server: applied right away
		$this->member = $this->createMock(Member::class);
		$this->member->method('getUserType')->willReturn(Member::TYPE_USER);
		$this->member->method('getUserId')->willReturn('bob');
		$this->member->method('isLocal')->willReturn(true);
	}

	private function makeCircle(): Circle&MockObject {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team1');
		return $circle;
	}

	private function makeRemovingEvent(int $type = CircleGenericEvent::REMOVED, bool $destroyingTeam = false): RemovingCircleMemberEvent&MockObject {
		$data = new SimpleDataStore();
		if ($destroyingTeam) {
			$data->sBool('destroyCircle', true);
		}
		$federatedEvent = $this->createMock(FederatedEvent::class);
		$federatedEvent->method('getData')->willReturn($data);

		$event = $this->createMock(RemovingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($this->member);
		$event->method('getType')->willReturn($type);
		$event->method('getFederatedEvent')->willReturn($federatedEvent);
		return $event;
	}

	private function withRoom(): void {
		$this->manager->method('getTeamRoom')->with('team1')->willReturn($this->room);
	}

	public static function dataRemovedRightAway(): array {
		return [
			'removed' => [CircleGenericEvent::REMOVED, false],
			'left' => [CircleGenericEvent::LEFT, true],
		];
	}

	#[DataProvider('dataRemovedRightAway')]
	public function testRemovedRightAwayAndResynced(int $type, bool $left): void {
		$this->withRoom();
		$this->syncService->expects($this->once())->method('removeFormerTeamMember')->with($this->room, $this->member, $left);
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeRemovingEvent($type));
	}

	public function testDeletedTeamKeepsItsParticipants(): void {
		$this->withRoom();
		$this->syncService->expects($this->never())->method('removeFormerTeamMember');

		$this->listener->handle($this->makeRemovingEvent(CircleGenericEvent::LEFT, true));
	}

	public function testTeamWithoutConversation(): void {
		$this->manager->method('getTeamRoom')->willReturn(null);
		$this->syncService->expects($this->never())->method('removeFormerTeamMember');
		$this->syncService->expects($this->never())->method('queueResync');

		$this->listener->handle($this->makeRemovingEvent());
	}

	public function testFailureDoesNotBreakTheTeamChange(): void {
		$this->withRoom();
		$this->syncService->method('removeFormerTeamMember')->willThrowException(new \RuntimeException('database gone'));
		$this->logger->expects($this->once())->method('warning');
		// The resync removes them later
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeRemovingEvent());
	}

	private function makeAddingEvent(): AddingCircleMemberEvent&MockObject {
		$event = $this->createMock(AddingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($this->member);
		return $event;
	}

	public function testAddedMemberIsAppliedRightAwayAndResynced(): void {
		$this->withRoom();
		$this->syncService->expects($this->never())->method('removeFormerTeamMember');
		$this->syncService->expects($this->once())->method('applyTeamMember')->with($this->room, $this->member);
		// Members of other servers and email guests are invited by the resync
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeAddingEvent());
	}

	public static function dataEditedMember(): array {
		return [
			'level changed' => [CircleGenericEvent::LEVEL, 1],
			'display name changed' => [CircleGenericEvent::NAME, 0],
		];
	}

	#[DataProvider('dataEditedMember')]
	public function testOnlyLevelChangesAreAppliedAndResynced(int $type, int $times): void {
		$this->withRoom();
		$event = $this->createMock(EditingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($this->member);
		$event->method('getType')->willReturn($type);

		$this->syncService->expects($this->exactly($times))->method('applyTeamMember')->with($this->room, $this->member);
		$this->syncService->expects($this->exactly($times))->method('queueResync')->with($this->room);

		$this->listener->handle($event);
	}

	public function testChangeMadeByTalkIsNotAppliedAgain(): void {
		$this->withRoom();
		$this->syncService->method('isChangingTeamFromTalk')->willReturn(true);
		$this->syncService->expects($this->never())->method('applyTeamMember');
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeAddingEvent());
	}

	public function testBannedMemberStaysOutWithoutWarning(): void {
		$this->withRoom();
		$this->syncService->method('applyTeamMember')->willThrowException(new ForbiddenException());
		$this->logger->expects($this->never())->method('warning');
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeAddingEvent());
	}

	public function testFailureToApplyDoesNotBreakTheTeamChange(): void {
		$this->withRoom();
		$this->syncService->method('applyTeamMember')->willThrowException(new \RuntimeException('database gone'));
		$this->logger->expects($this->once())->method('warning');
		// The resync applies it later
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeAddingEvent());
	}

	public static function dataCreatedRoom(): array {
		return [
			'team conversation' => [Room::OBJECT_TYPE_TEAM, 1],
			'other conversation' => ['', 0],
		];
	}

	#[DataProvider('dataCreatedRoom')]
	public function testCreatedTeamRoomIsResyncedForAllMembers(string $objectType, int $resyncs): void {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('isTeamManaged')->willReturn($objectType === Room::OBJECT_TYPE_TEAM);
		$this->syncService->expects($this->exactly($resyncs))->method('queueResync')->with($room);

		$this->listener->handle(new RoomCreatedEvent($room));
	}

	public function testRemovalMadeByTalkIsNotRepeated(): void {
		$this->manager->method('getTeamRoom')->with('team1')->willReturn($this->room);
		$this->syncService->method('isChangingTeamFromTalk')->willReturn(true);
		$this->syncService->expects($this->never())->method('removeFormerTeamMember');
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($this->makeRemovingEvent(CircleGenericEvent::LEFT));
	}

	private function makeInitiator(int $userType, string $userId, bool $local = true): Member&MockObject {
		$initiator = $this->createMock(Member::class);
		$initiator->method('getUserType')->willReturn($userType);
		$initiator->method('getUserId')->willReturn($userId);
		$initiator->method('getDisplayName')->willReturn(ucfirst($userId));
		$initiator->method('isLocal')->willReturn($local);
		return $initiator;
	}

	/**
	 * @return array<string, mixed> Session values while the change was applied
	 */
	private function applyAddedMemberBy(Member $initiator): array {
		$circle = $this->makeCircle();
		$circle->method('hasInitiator')->willReturn(true);
		$circle->method('getInitiator')->willReturn($initiator);
		$event = $this->createMock(AddingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($circle);
		$event->method('getMember')->willReturn($this->member);
		$this->withRoom();

		$during = null;
		$this->syncService->method('applyTeamMember')->willReturnCallback(function () use (&$during): void {
			$during = $this->sessionValues;
		});

		$this->listener->handle($event);
		return $during ?? [];
	}

	public function testSystemMessagesNameTheUserOfTheTeamsApp(): void {
		// Circles adds members in a separate request without a user session
		$during = $this->applyAddedMemberBy($this->makeInitiator(Member::TYPE_USER, 'alice'));

		$this->assertSame(['talk-overwrite-actor-id' => 'alice', 'talk-overwrite-actor-displayname' => 'Alice'], $during);
		$this->assertSame([], $this->sessionValues);
	}

	public function testSystemMessagesOfOccNameTheAdministrator(): void {
		$during = $this->applyAddedMemberBy($this->makeInitiator(Member::TYPE_APP, 'occ'));

		$this->assertSame(['talk-overwrite-actor-cli' => 'cli'], $during);
		$this->assertSame([], $this->sessionValues);
	}

	public function testUserOfAnotherServerIsNoLocalActor(): void {
		$during = $this->applyAddedMemberBy($this->makeInitiator(Member::TYPE_USER, 'alice', false));

		$this->assertSame([], $during);
	}

	public function testActorOfTheCallerIsRestored(): void {
		// E.g. Circles runs the change while another listener set its actor
		$this->sessionValues = ['talk-overwrite-actor-id' => 'bob', 'talk-overwrite-actor-displayname' => 'Bob'];

		$during = $this->applyAddedMemberBy($this->makeInitiator(Member::TYPE_USER, 'alice'));

		$this->assertSame('alice', $during['talk-overwrite-actor-id']);
		$this->assertSame(['talk-overwrite-actor-id' => 'bob', 'talk-overwrite-actor-displayname' => 'Bob'], $this->sessionValues);
	}

	public function testRemovalsNameTheUserOfTheTeamsApp(): void {
		$this->withRoom();
		$circle = $this->makeCircle();
		$circle->method('hasInitiator')->willReturn(true);
		$circle->method('getInitiator')->willReturn($this->makeInitiator(Member::TYPE_USER, 'alice'));
		$federatedEvent = $this->createMock(FederatedEvent::class);
		$federatedEvent->method('getData')->willReturn(new SimpleDataStore());
		$event = $this->createMock(RemovingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($circle);
		$event->method('getMember')->willReturn($this->member);
		$event->method('getType')->willReturn(CircleGenericEvent::REMOVED);
		$event->method('getFederatedEvent')->willReturn($federatedEvent);

		$during = null;
		$this->syncService->method('removeFormerTeamMember')->willReturnCallback(function () use (&$during): void {
			$during = $this->sessionValues['talk-overwrite-actor-id'] ?? null;
		});

		$this->listener->handle($event);

		$this->assertSame('alice', $during);
		$this->assertSame([], $this->sessionValues);
	}

	public function testDeletedTeamArchivesItsRoomAsTheUserWhoDeletedIt(): void {
		$this->withRoom();
		$circle = $this->makeCircle();
		$circle->method('hasInitiator')->willReturn(true);
		$circle->method('getInitiator')->willReturn($this->makeInitiator(Member::TYPE_USER, 'alice'));
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn($circle);

		$during = null;
		$this->syncService->expects($this->once())->method('archiveForDeletedTeam')->with($this->room)
			->willReturnCallback(function () use (&$during): void {
				$during = $this->sessionValues['talk-overwrite-actor-id'] ?? null;
			});

		$this->listener->handle($event);

		$this->assertSame('alice', $during);
		$this->assertSame([], $this->sessionValues);
	}

	public function testFailingArchiveDoesNotBreakTheDeletionOfTheTeam(): void {
		$this->withRoom();
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$this->syncService->method('archiveForDeletedTeam')->willThrowException(new \RuntimeException('Deadlock'));

		// The resync archives it: the team is not found anymore
		$this->logger->expects($this->once())->method('warning');

		$this->listener->handle($event);
	}

	private function makeRemoteMember(): Member&MockObject {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_USER);
		$member->method('getUserId')->willReturn('carol');
		$member->method('isLocal')->willReturn(false);
		return $member;
	}

	public function testMemberOfAnotherServerIsNotInvitedWhileCirclesAppliesTheChange(): void {
		// A slow or failing invitation must not keep Circles from finishing the change
		$this->withRoom();
		$event = $this->createMock(AddingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($this->makeRemoteMember());

		$this->syncService->expects($this->never())->method('applyTeamMember');
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($event);
	}

	public function testMemberOfAnotherServerIsInvitedOnceCirclesFinishedTheChange(): void {
		$this->withRoom();
		$member = $this->makeRemoteMember();
		$event = $this->createMock(CircleMemberAddedEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($member);

		$this->syncService->expects($this->once())->method('applyTeamMember')->with($this->room, $member);

		$this->listener->handle($event);
	}

	public static function dataInvitedOnceCirclesFinishedTheChange(): array {
		return [
			'request of a user' => [true, false, 1, 0],
			'command line' => [false, true, 1, 0],
			// Loopback request of Circles: wrong address for links and signatures
			'request without a user' => [false, false, 0, 1],
		];
	}

	#[DataProvider('dataInvitedOnceCirclesFinishedTheChange')]
	public function testMemberOfAnotherServerIsOnlyInvitedWhereTheAddressOfThisServerIsKnown(bool $withUser, bool $cli, int $invited, int $resyncs): void {
		$this->withRoom();
		$this->userSession->method('getUser')->willReturn($withUser ? $this->createMock(IUser::class) : null);
		$listener = $this->getMockBuilder(TeamRoomListener::class)
			->setConstructorArgs([$this->manager, $this->syncService, $this->logger, $this->session, $this->userSession])
			->onlyMethods(['isCli'])
			->getMock();
		$listener->method('isCli')->willReturn($cli);
		$member = $this->makeRemoteMember();
		$event = $this->createMock(CircleMemberAddedEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($member);

		$this->syncService->expects($this->exactly($invited))->method('applyTeamMember')->with($this->room, $member);
		$this->syncService->expects($this->exactly($resyncs))->method('queueResync')->with($this->room);

		$listener->handle($event);
	}

	public function testUsersOfThisServerAreNotAppliedTwice(): void {
		// Already applied while Circles applied the change
		$this->withRoom();
		$event = $this->createMock(CircleMemberAddedEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($this->member);

		$this->syncService->expects($this->never())->method('applyTeamMember');

		$this->listener->handle($event);
	}

	public static function dataEditedResult(): array {
		return [
			'level changed' => [CircleGenericEvent::LEVEL, 1],
			'display name changed' => [CircleGenericEvent::NAME, 0],
		];
	}

	#[DataProvider('dataEditedResult')]
	public function testOnlyLevelChangesOfMembersOfOtherServersAreApplied(int $type, int $times): void {
		$this->withRoom();
		$member = $this->makeRemoteMember();
		$event = $this->createMock(CircleMemberEditedEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getMember')->willReturn($member);
		$event->method('getType')->willReturn($type);

		$this->syncService->expects($this->exactly($times))->method('applyTeamMember')->with($this->room, $member);

		$this->listener->handle($event);
	}
}
