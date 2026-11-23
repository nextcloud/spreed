<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Member;
use OCA\Talk\BackgroundJob\ResyncTeamRoom;
use OCA\Talk\BackgroundJob\ResyncTeamRoomsNow;
use OCA\Talk\Config;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\ParticipantProperty\ParticipantTypeException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\TeamRoomSettingsService;
use OCA\Talk\Service\TeamRoomSyncService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamRoomSyncServiceTest extends TestCase {
	/** Same values as OCA\Circles\Model\Member::LEVEL_* */
	private const LEVEL_MEMBER = 1;
	private const LEVEL_MODERATOR = 4;
	private const LEVEL_ADMIN = 8;
	private const LEVEL_OWNER = 9;

	/** Pause after the first failed invitation */
	private const INVITATION_PAUSE = TeamRoomSyncService::INVITATION_RETRY_PAUSE;

	protected CirclesAdapter&MockObject $circlesAdapter;
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected TeamRoomSettingsService&MockObject $settingsService;
	protected BanService&MockObject $banService;
	protected Config&MockObject $talkConfig;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected FederationManager&MockObject $federationManager;
	protected IUserManager&MockObject $userManager;
	protected ITimeFactory&MockObject $timeFactory;
	protected LoggerInterface&MockObject $logger;
	protected RoomService&MockObject $roomService;
	protected IEventDispatcher&MockObject $dispatcher;
	protected IJobList&MockObject $jobList;
	protected IAppConfig&MockObject $appConfig;
	protected GuestManager&MockObject $guestManager;
	protected TeamRoomSyncService $service;
	/** Local owner or moderator the federated members are invited in the name of */
	protected ?Attendee $inviter = null;
	protected Room&MockObject $room;

	/**
	 * Key of a member in the markers, with the secret 'secret' of the mocked config
	 */
	private static function hmac(string $memberKey): string {
		return hash_hmac('sha256', $memberKey, 'secret');
	}

	public function setUp(): void {
		parent::setUp();

		$this->circlesAdapter = $this->createMock(CirclesAdapter::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->settingsService = $this->createMock(TeamRoomSettingsService::class);
		$this->banService = $this->createMock(BanService::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->federationManager = $this->createMock(FederationManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1000000);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$serverConfig = $this->createMock(IConfig::class);
		$serverConfig->method('getSystemValueString')->with('secret')->willReturn('secret');
		// A local owner sends the invitations of federated members
		$this->inviter = Attendee::fromRow(['actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']);
		$this->participantService->method('getHighestPermissionAttendee')
			->willReturnCallback(fn (): ?Attendee => $this->inviter);

		$this->service = new TeamRoomSyncService(
			$this->circlesAdapter,
			$this->manager,
			$this->participantService,
			$this->settingsService,
			$this->banService,
			$this->talkConfig,
			$this->cloudIdManager,
			$this->federationManager,
			$this->userManager,
			$this->timeFactory,
			$this->logger,
			$this->roomService,
			$this->dispatcher,
			$this->jobList,
			$this->appConfig,
			$this->guestManager,
			$serverConfig,
		);

		$this->room = $this->createMock(Room::class);
		$this->room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_TEAM);
		$this->room->method('isTeamManaged')->willReturn(true);
		$this->room->method('getObjectId')->willReturn('team1');
		$this->room->method('getToken')->willReturn('teamroom');
		$this->room->method('getId')->willReturn(7);

		$this->cloudIdManager->method('getCloudId')
			->willReturnCallback(fn (string $user, ?string $remote): ICloudId => $this->makeCloudId($user, 'https://' . $remote));
		// Same as the server: only the protocol is removed
		$this->cloudIdManager->method('removeProtocolFromUrl')
			->willReturnCallback(static fn (string $url): string => (string)preg_replace('/^https?:\/\//', '', $url));
		$this->cloudIdManager->method('resolveCloudId')
			->willReturnCallback(function (string $cloudId): ICloudId {
				[$user, $remote] = explode('@', $cloudId, 2);
				return $this->makeCloudId($user, $remote);
			});
	}

	private function makeCloudId(string $user, string $remote): ICloudId&MockObject {
		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getUser')->willReturn($user);
		$cloudId->method('getRemote')->willReturn($remote);
		$cloudId->method('getId')->willReturn($user . '@' . preg_replace('/^https?:\/\//', '', $remote));
		$cloudId->method('getDisplayId')->willReturn($user . '@' . preg_replace('/^https?:\/\//', '', $remote));
		return $cloudId;
	}

	private function makeUserParticipant(string $userId, int $participantType = Participant::USER): Participant&MockObject {
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => $userId,
			'participant_type' => $participantType,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$participant->method('hasModeratorPermissions')->willReturn(in_array($participantType, [Participant::OWNER, Participant::MODERATOR], true));
		return $participant;
	}

	private function makeFederatedParticipant(string $cloudId, int $participantType = Participant::USER): Participant&MockObject {
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_FEDERATED_USERS,
			'actor_id' => $cloudId,
			'participant_type' => $participantType,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	private function makeEmailParticipant(string $email): Participant&MockObject {
		$attendee = Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_EMAILS,
			'actor_id' => hash('sha256', $email),
			'invited_cloud_id' => $email,
		]);
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		return $participant;
	}

	/**
	 * @param array<string, int> $levels User id => level of the direct members
	 * @param list<array{userId: string, instance: string, displayName: string, level: int}> $remote
	 * @param array<string, string> $emails
	 */
	private function mockSnapshot(array $levels, array $remote = [], array $emails = []): void {
		$members = [];
		foreach ($levels as $userId => $level) {
			$members[$userId] = ['level' => $level, 'joined' => 1];
		}
		$this->circlesAdapter->method('getTeamSnapshot')->with('team1')->willReturn([
			'team' => ['id' => 'team1', 'name' => 'Team', 'description' => '', 'config' => 0, 'local' => true],
			'direct' => $members,
			'remote' => $remote,
			'emails' => $emails,
		]);
		$this->circlesAdapter->method('getDirectMember')
			->willReturnCallback(static fn (string $teamId, string $userId): ?array => $members[$userId] ?? null);
	}

	/**
	 * @param list<Participant> $users
	 * @param list<Participant> $federated
	 * @param list<Participant> $emails
	 */
	private function mockParticipants(array $users, array $federated = [], array $emails = []): void {
		$this->participantService->method('getParticipantsByActorType')
			->willReturnCallback(static fn (Room $room, string $actorType): array => match ($actorType) {
				Attendee::ACTOR_USERS => $users,
				Attendee::ACTOR_FEDERATED_USERS => $federated,
				Attendee::ACTOR_EMAILS => $emails,
				default => [],
			});
	}

	private function mockUsers(): void {
		$this->userManager->method('get')->willReturnCallback(function (string $userId): IUser {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
			$user->method('getDisplayName')->willReturn(ucfirst($userId));
			return $user;
		});
	}

	private function syncFederatedMembers(): void {
		self::invokePrivate($this->service, 'syncFederatedMembers', [$this->room]);
	}

	/**
	 * Federation enabled, bob@remote.example is the only remote member of the
	 * team and not a participant yet, local users exist
	 */
	private function mockRemoteBob(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));
	}

	public static function dataParticipantTypeForLevel(): array {
		return [
			'owner' => [self::LEVEL_OWNER, Participant::OWNER],
			'admin' => [self::LEVEL_ADMIN, Participant::OWNER],
			'moderator' => [self::LEVEL_MODERATOR, Participant::MODERATOR],
			'member' => [self::LEVEL_MEMBER, Participant::USER],
		];
	}

	#[DataProvider('dataParticipantTypeForLevel')]
	public function testParticipantTypeForLevel(int $level, int $participantType): void {
		$this->assertSame($participantType, TeamRoomSyncService::getParticipantTypeForLevel($level));
	}

	public function testResyncIsQueuedOncePerTeam(): void {
		$this->jobList->method('has')->with(ResyncTeamRoom::class, ['teamId' => 'team1'])->willReturnOnConsecutiveCalls(false, true);
		$this->jobList->expects($this->once())->method('add')->with(ResyncTeamRoom::class, ['teamId' => 'team1']);

		$this->service->queueResync($this->room);
		$this->service->queueResync($this->room);
	}

	public function testFullResyncIsStartedWhenNoneIsRunning(): void {
		$this->jobList->method('getJobsIterator')->with(ResyncTeamRoomsNow::class, 1, 0)->willReturn([]);
		$this->jobList->expects($this->once())->method('add')->with(ResyncTeamRoomsNow::class, ['afterId' => 0]);

		$this->service->queueFullResync();
	}

	public function testRunningFullResyncIsNotStartedAgain(): void {
		// A running pass has its cursor as argument, e.g. ['afterId' => 42]
		$this->jobList->method('getJobsIterator')->willReturn([$this->createMock(IJob::class)]);
		$this->jobList->expects($this->never())->method('add');

		$this->service->queueFullResync();
	}

	public function testResyncAddsUpdatesAndRemovesUsers(): void {
		$this->mockSnapshot(['alice' => self::LEVEL_OWNER, 'bob' => self::LEVEL_MODERATOR, 'carol' => self::LEVEL_MEMBER]);
		$bob = $this->makeUserParticipant('bob');
		$dave = $this->makeUserParticipant('dave');
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER), $bob, $dave]);
		$this->mockUsers();

		$this->settingsService->expects($this->once())->method('applyTeamToRoom')->with($this->room, $this->anything());
		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => 'carol',
				'displayName' => 'Carol',
				'participantType' => Participant::USER,
			]])
			->willReturn([]);
		$this->participantService->expects($this->once())->method('updateParticipantType')->with($this->room, $bob, Participant::MODERATOR);
		$this->participantService->expects($this->once())
			->method('removeUser')
			->with($this->room, $this->callback(static fn (IUser $user): bool => $user->getUID() === 'dave'), AAttendeeRemovedEvent::REASON_REMOVED);

		$this->service->resyncRoom($this->room, false);
	}

	public function testPromotionsBeforeDemotions(): void {
		// The room never lacks a moderator in between
		$this->mockSnapshot(['alice' => self::LEVEL_MEMBER, 'bob' => self::LEVEL_OWNER]);
		$alice = $this->makeUserParticipant('alice', Participant::OWNER);
		$bob = $this->makeUserParticipant('bob');
		$this->mockParticipants([$alice, $bob]);
		$this->mockUsers();

		$changes = [];
		$this->participantService->method('updateParticipantType')
			->willReturnCallback(static function (Room $room, Participant $participant, int $type) use (&$changes): void {
				$changes[] = [$participant->getAttendee()->getActorId(), $type];
			});

		$this->service->resyncRoom($this->room, false);

		$this->assertSame([['bob', Participant::OWNER], ['alice', Participant::USER]], $changes);
	}

	public function testDeletedUserStillListedIsRemovedDirectly(): void {
		$this->mockSnapshot(['alice' => self::LEVEL_OWNER]);
		$ghost = $this->makeUserParticipant('ghost');
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER), $ghost]);
		$this->userManager->method('get')->willReturnCallback(fn (string $userId): ?IUser => $userId === 'ghost' ? null : $this->createMock(IUser::class));

		$this->participantService->expects($this->never())->method('removeUser');
		$this->participantService->expects($this->once())->method('removeAttendee')->with($this->room, $ghost, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->service->resyncRoom($this->room, false);
	}

	public function testUnknownTeamMembersAreSkipped(): void {
		// E.g. remnants of a removed LDAP user
		$this->mockSnapshot(['alice' => self::LEVEL_OWNER, 'ghost' => self::LEVEL_MEMBER]);
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER)]);
		$this->userManager->method('get')->willReturnCallback(fn (string $userId): ?IUser => $userId === 'ghost' ? null : $this->createMock(IUser::class));

		$this->participantService->expects($this->never())->method('addUsers');

		$this->service->resyncRoom($this->room, false);
	}

	public function testResyncNeverChangesTheTeam(): void {
		// Owner on another instance: nobody local is owner or admin
		$this->mockSnapshot(['carol' => self::LEVEL_MEMBER]);
		$this->mockParticipants([]);
		$this->mockUsers();
		$this->participantService->method('addUsers')->willReturn([]);

		$this->circlesAdapter->expects($this->never())->method('updateTeam');
		$this->circlesAdapter->expects($this->never())->method('removeDirectUser');
		$this->circlesAdapter->expects($this->never())->method('joinTeam');

		$this->service->resyncRoom($this->room, true);
	}

	public function testResyncInRequestsDoesNotInviteFederatedMembersOrEmails(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->mockSnapshot(
			['alice' => self::LEVEL_OWNER],
			[['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER]],
			['guest@example.tld' => 'Guest'],
		);
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER)]);
		$this->mockUsers();

		$this->participantService->expects($this->never())->method('addUsers');
		$this->participantService->expects($this->never())->method('inviteEmailAddress');

		$this->service->resyncRoom($this->room, false);
	}

	public function testResyncIsSkippedWhenTeamsAreUnavailable(): void {
		$this->circlesAdapter->method('isAvailable')->willReturn(false);
		$this->manager->expects($this->never())->method('getRoomsByObjectType');

		$this->assertNull($this->service->resyncFrom(0, 60));
	}

	public function testResyncOfAllRoomsContinuesAfterTheLastRoom(): void {
		$this->circlesAdapter->method('isAvailable')->willReturn(true);
		$this->manager->expects($this->exactly(2))
			->method('getRoomsByObjectType')
			->willReturnOnConsecutiveCalls([$this->room], []);
		$this->mockSnapshot([]);
		$this->mockParticipants([]);

		// 0: the pass is done
		$this->assertSame(0, $this->service->resyncFrom(0, 60));
	}

	public function testOneBrokenRoomDoesNotStopTheResync(): void {
		$this->circlesAdapter->method('isAvailable')->willReturn(true);
		$broken = $this->createMock(Room::class);
		$broken->method('getId')->willReturn(1);
		$broken->method('getObjectId')->willReturn('team1');
		$broken->method('getToken')->willReturn('broken');
		$this->manager->method('getRoomsByObjectType')->willReturnOnConsecutiveCalls([$broken, $this->room], []);
		$this->mockSnapshot([]);
		$this->participantService->method('getParticipantsByActorType')
			->willReturnCallback(static function (Room $room) use ($broken): array {
				if ($room === $broken) {
					throw new \TypeError('broken');
				}
				return [];
			});

		$this->logger->expects($this->once())->method('error');
		// The second room is synced as well
		$this->settingsService->expects($this->exactly(2))->method('applyTeamToRoom');

		$this->assertSame(0, $this->service->resyncFrom(0, 60));
	}

	public function testNothingChangesWhenFederationIsDisabled(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(false);
		$this->circlesAdapter->expects($this->never())->method('getRemoteMembers');
		$this->participantService->expects($this->never())->method('removeAttendee');

		$this->syncFederatedMembers();
	}

	public function testInvitesRemoteMembersAsModeratorAtMost(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_OWNER],
		]);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);

		$inviter = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('alice')->willReturn($inviter);

		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, [[
				'actorType' => Attendee::ACTOR_FEDERATED_USERS,
				'actorId' => 'bob@remote.example',
				'displayName' => 'Bob',
				'participantType' => Participant::MODERATOR,
			]], $inviter);

		$this->syncFederatedMembers();
	}

	public function testMembersAreInvitedInTheNameOfALocalOwner(): void {
		// Talk's federation settings (trusted servers, allowed groups) are checked for that user
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$owner = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('alice')->willReturn($owner);

		$this->federationManager->expects($this->once())
			->method('isAllowedToInvite')
			->with($owner, $this->anything(), $this->room);
		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, $this->anything(), $owner);

		$this->syncFederatedMembers();
	}

	public function testBannedRemoteMemberIsNotInvited(): void {
		$this->mockRemoteBob();
		$this->banService->method('isActorBanned')->with($this->room, Attendee::ACTOR_FEDERATED_USERS, 'bob@remote.example')->willReturn(true);
		$this->participantService->expects($this->never())->method('addUsers');

		$this->syncFederatedMembers();
	}

	public function testUnreachableRemoteIsTriedAgainLater(): void {
		$this->mockRemoteBob();

		$this->participantService->method('addUsers')->willThrowException(new CannotReachRemoteException());
		$this->participantService->expects($this->never())->method('removeAttendee');
		// Tried again after a pause, not with every sync
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [7 => ['wait' => [self::hmac('bob@remote.example') => ['count' => 1, 'until' => 1000000 + self::INVITATION_PAUSE]]]]);

		$this->syncFederatedMembers();
	}

	public static function dataNotInvitedAgain(): array {
		return [
			'during the pause after an unreachable server' => [['wait' => [self::hmac('bob@remote.example') => ['count' => 2, 'until' => 1000000 + 60]]]],
			'declined or left' => [['left' => [self::hmac('bob@remote.example') => true]]],
		];
	}

	#[DataProvider('dataNotInvitedAgain')]
	public function testRemoteMemberIsNotInvitedAgain(array $markers): void {
		$this->mockRemoteBob();
		$this->appConfig->method('getAppValueArray')->with(Config::TEAM_FEDERATED_MEMBERS)->willReturn([7 => $markers]);

		$this->participantService->expects($this->never())->method('addUsers');

		$this->syncFederatedMembers();
	}

	public function testMarkersOfFormerTeamMembersAreDeleted(): void {
		// Removed from the team: invited again when added again
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([]);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$this->appConfig->method('getAppValueArray')->willReturn([
			7 => ['left' => [self::hmac('bob@remote.example') => true]],
			9 => ['left' => [self::hmac('carol@remote.example') => true]],
		]);

		// Only the markers of this conversation change
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [9 => ['left' => [self::hmac('carol@remote.example') => true]]]);

		$this->syncFederatedMembers();
	}

	public function testMarkersAreDeletedWithTheConversation(): void {
		$this->appConfig->method('getAppValueArray')->willReturn([
			7 => ['left' => ['abc' => true], 'wait' => ['def' => ['count' => 1, 'until' => 1]]],
			9 => ['left' => ['ghi' => true]],
		]);

		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [9 => ['left' => ['ghi' => true]]]);

		$this->service->deleteFederatedMarkers($this->room);
	}

	public function testNothingIsWrittenWithoutMarkers(): void {
		$this->appConfig->method('getAppValueArray')->willReturn([]);
		$this->appConfig->expects($this->never())->method('setAppValueArray');

		$this->service->deleteFederatedMarkers($this->room);
	}

	public function testLeavingIsRemembered(): void {
		$this->appConfig->method('getAppValueArray')->willReturn([]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [7 => ['left' => [self::hmac('bob@remote.example') => true]]]);

		$this->service->rememberFederatedLeave($this->room, 'bob@Remote.Example');
	}

	public function testConfirmedCloudIdWithOtherCasingOfTheServerIsKept(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->participantService->method('getParticipantsByActorType')
			->willReturn([$this->makeFederatedParticipant('bob@Remote.Example')]);

		$this->participantService->expects($this->never())->method('addUsers');
		$this->participantService->expects($this->never())->method('removeAttendee');

		$this->syncFederatedMembers();
	}

	public function testUserIdsOfOtherCasingAreDifferentUsers(): void {
		// E.g. LDAP on the remote server: bob and Bob are two users
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'bob', 'level' => self::LEVEL_MEMBER],
		]);
		$other = $this->makeFederatedParticipant('Bob@remote.example');
		$this->participantService->method('getParticipantsByActorType')->willReturn([$other]);
		$this->userManager->method('get')->willReturn($this->createMock(IUser::class));

		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, $this->callback(static fn (array $participants): bool => $participants[0]['actorId'] === 'bob@remote.example'));
		$this->participantService->expects($this->once())
			->method('removeAttendee')
			->with($this->room, $other, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->syncFederatedMembers();
	}

	public function testFederatedRoleFollowsTheTeam(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MODERATOR],
		]);
		$bob = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantsByActorType')->willReturn([$bob]);

		$this->participantService->expects($this->once())->method('updateParticipantType')->with($this->room, $bob, Participant::MODERATOR);

		$this->syncFederatedMembers();
	}

	public function testFederatedParticipantsThatLeftTheTeamAreRemoved(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([]);
		$former = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantsByActorType')->willReturn([$former]);

		$this->participantService->expects($this->once())
			->method('removeAttendee')
			->with($this->room, $former, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->syncFederatedMembers();
	}

	public function testNoInvitationWithoutLocalInviter(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);

		// No local owner or moderator in the conversation
		$this->inviter = null;

		$this->participantService->expects($this->never())->method('addUsers');
		$this->logger->expects($this->once())->method('warning');

		$this->syncFederatedMembers();
	}

	public function testEmailMembersAreInvited(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'Guest']);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$participant = $this->makeEmailParticipant('guest@example.tld');

		$this->participantService->expects($this->once())
			->method('inviteEmailAddress')
			->with($this->room, hash('sha256', 'guest@example.tld'), 'guest@example.tld', 'Guest')
			->willReturn($participant);
		$this->guestManager->expects($this->once())->method('sendEmailInvitation')->with($this->room, $participant);

		self::invokePrivate($this->service, 'syncEmailMembers', [$this->room, true]);
	}

	public function testBannedEmailMemberIsNotInvited(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'Guest']);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$this->banService->method('isEmailBanned')->with($this->room, 'guest@example.tld')->willReturn(true);

		$this->participantService->expects($this->never())->method('inviteEmailAddress');

		self::invokePrivate($this->service, 'syncEmailMembers', [$this->room, true]);
	}

	public static function dataEmailMemberNotInvited(): array {
		return [
			// Every new email guest gets a mail: in the background only
			'in requests' => [false, []],
			'left before' => [true, [7 => ['emailLeft' => [self::hmac('guest@example.tld') => true]]]],
		];
	}

	#[DataProvider('dataEmailMemberNotInvited')]
	public function testEmailMemberIsNotInvited(bool $inviteEmails, array $markers): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'Guest']);
		$this->participantService->method('getParticipantsByActorType')->willReturn([]);
		$this->appConfig->method('getAppValueArray')->willReturn($markers);
		$this->participantService->expects($this->never())->method('inviteEmailAddress');

		self::invokePrivate($this->service, 'syncEmailMembers', [$this->room, $inviteEmails]);
	}

	public function testEmailGuestsThatLeftTheTeamAreRemoved(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn([]);
		$former = $this->makeEmailParticipant('former@example.tld');
		$this->participantService->method('getParticipantsByActorType')->willReturn([$former]);

		$this->participantService->expects($this->once())
			->method('removeAttendee')
			->with($this->room, $former, AAttendeeRemovedEvent::REASON_REMOVED);

		self::invokePrivate($this->service, 'syncEmailMembers', [$this->room, true]);
	}

	public function testDeletedTeamArchivesAndBlocksTheConversation(): void {
		$this->participantService->method('getNumberOfModerators')->willReturn(1);
		$owner = $this->makeUserParticipant('alice', Participant::OWNER);
		$member = $this->makeUserParticipant('bob');
		$federatedModerator = $this->makeFederatedParticipant('carol@remote.example', Participant::MODERATOR);
		$this->mockParticipants([$owner, $member], [$federatedModerator]);

		$this->roomService->expects($this->once())->method('setReadOnly')->with($this->room, Room::READ_ONLY);
		$changed = [];
		$this->participantService->method('updateParticipantType')
			->willReturnCallback(static function (Room $room, Participant $participant, int $type) use (&$changed): void {
				$changed[$participant->getAttendee()->getActorId()] = $type;
			});
		// Only local users have an archive
		$this->participantService->expects($this->exactly(2))->method('archiveConversation');

		$this->service->archiveForDeletedTeam($this->room);

		$this->assertSame(['alice' => Participant::USER, 'carol@remote.example' => Participant::USER], $changed);
	}

	public function testDeletedTeamIsOnlyArchivedOnce(): void {
		$this->room->method('getReadOnly')->willReturn(Room::READ_ONLY);
		$this->participantService->method('getNumberOfModerators')->willReturn(0);

		$this->participantService->expects($this->never())->method('archiveConversation');
		$this->roomService->expects($this->never())->method('setReadOnly');

		$this->service->archiveForDeletedTeam($this->room);
	}

	public function testOtherConversationsAreNeverArchivedAsTeamConversation(): void {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn('');
		$room->method('isTeamManaged')->willReturn(false);
		$this->roomService->expects($this->never())->method('setReadOnly');

		$this->service->archiveForDeletedTeam($room);
	}

	private function makeCirclesMember(string $userId, string $instance = ''): Member&MockObject {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_USER);
		$member->method('getUserId')->willReturn($userId);
		$member->method('isLocal')->willReturn($instance === '');
		$member->method('getInstance')->willReturn($instance);
		return $member;
	}

	public static function dataRemovedLocalMember(): array {
		return [
			'removed' => [false, AAttendeeRemovedEvent::REASON_REMOVED],
			'left' => [true, AAttendeeRemovedEvent::REASON_LEFT],
		];
	}

	#[DataProvider('dataRemovedLocalMember')]
	public function testRemovedLocalMemberIsRemovedRightAway(bool $left, string $reason): void {
		$participant = $this->makeUserParticipant('alice');
		$this->participantService->method('getParticipantByActor')
			->with($this->room, Attendee::ACTOR_USERS, 'alice')
			->willReturn($participant);
		$this->participantService->expects($this->once())->method('removeAttendee')->with($this->room, $participant, $reason);

		$this->service->removeFormerTeamMember($this->room, $this->makeCirclesMember('alice'), $left);
	}

	public function testRemovedLocalMemberThatIsNoParticipant(): void {
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$this->participantService->expects($this->never())->method('removeAttendee');

		$this->service->removeFormerTeamMember($this->room, $this->makeCirclesMember('alice'), false);
	}

	public function testRemovedFederatedMemberOnlyOfTheirServer(): void {
		// Same user id on two other servers: only the one of the removed member
		$other = $this->makeFederatedParticipant('bob@other.example');
		$removed = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantsByActorType')
			->with($this->room, Attendee::ACTOR_FEDERATED_USERS)
			->willReturn([$other, $removed]);
		$this->participantService->expects($this->once())->method('removeAttendee')
			->with($this->room, $removed, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->service->removeFormerTeamMember($this->room, $this->makeCirclesMember('bob', 'remote.example'), true);
	}

	public function testRemovedEmailMember(): void {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_MAIL);
		$this->circlesAdapter->method('getEmailOfMember')->with($member)->willReturn('guest@example.tld');
		$participant = $this->makeEmailParticipant('guest@example.tld');
		$this->participantService->method('getParticipantByActor')
			->with($this->room, Attendee::ACTOR_EMAILS, hash('sha256', 'guest@example.tld'))
			->willReturn($participant);
		$this->participantService->expects($this->once())->method('removeAttendee')
			->with($this->room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->service->removeFormerTeamMember($this->room, $member, false);
	}

	public function testRemovedGroupMemberIsLeftToTheSync(): void {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_GROUP);
		$this->circlesAdapter->method('getEmailOfMember')->willReturn(null);
		$this->participantService->expects($this->never())->method('removeAttendee');

		$this->service->removeFormerTeamMember($this->room, $member, false);
	}

	public function testMarkersAreWrittenOncePerResync(): void {
		$this->talkConfig->method('isFederationEnabled')->willReturn(true);
		$this->mockSnapshot(['alice' => self::LEVEL_OWNER], [
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
			['userId' => 'carol', 'instance' => 'other.example', 'displayName' => 'Carol', 'level' => self::LEVEL_MEMBER],
		]);
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER)]);
		$this->mockUsers();
		$this->participantService->method('addUsers')->willThrowException(new CannotReachRemoteException());

		// Both pauses in one write, not one write per invitation
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [7 => ['wait' => [
				self::hmac('bob@remote.example') => ['count' => 1, 'until' => 1000000 + self::INVITATION_PAUSE],
				self::hmac('carol@other.example') => ['count' => 1, 'until' => 1000000 + self::INVITATION_PAUSE],
			]]]);

		$this->service->resyncRoom($this->room, true);
	}

	public function testResyncOfADeletedTeamArchivesTheConversation(): void {
		// CirclesAdapter::run() keeps the exception of Circles as previous one
		$this->circlesAdapter->method('getTeamSnapshot')
			->willThrowException(new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')));
		$this->participantService->method('getNumberOfModerators')->willReturn(1);
		$this->mockParticipants([$this->makeUserParticipant('alice', Participant::OWNER)]);

		$this->roomService->expects($this->once())->method('setReadOnly')->with($this->room, Room::READ_ONLY);
		$this->participantService->expects($this->never())->method('removeUser');

		$this->service->resyncRoom($this->room, true);
	}

	public static function dataTeamsUnavailable(): array {
		return [
			'Circles disabled' => [new \Exception('Circles App unavailable')],
			'database error in Circles' => [new \Exception('Deadlock', 0, new \RuntimeException('Deadlock'))],
		];
	}

	#[DataProvider('dataTeamsUnavailable')]
	public function testFailingCirclesNeverArchivesTheConversation(\Exception $exception): void {
		$this->circlesAdapter->method('getTeamSnapshot')->willThrowException($exception);

		$this->roomService->expects($this->never())->method('setReadOnly');
		$this->participantService->expects($this->never())->method('updateParticipantType');
		$this->participantService->expects($this->never())->method('archiveConversation');
		$this->participantService->expects($this->never())->method('removeUser');

		// Thrown on: ResyncTeamRoom logs it, resyncFrom() stops the pass
		$this->expectExceptionObject($exception);
		$this->service->resyncRoom($this->room, true);
	}

	public function testFailingCirclesStopsTheResyncOfAllRooms(): void {
		$this->circlesAdapter->method('isAvailable')->willReturn(true);
		$this->manager->method('getRoomsByObjectType')->willReturn([$this->room]);
		$this->circlesAdapter->method('getTeamSnapshot')->willThrowException(new \Exception('Circles App unavailable'));

		$this->roomService->expects($this->never())->method('setReadOnly');

		$this->assertNull($this->service->resyncFrom(0, 60));
	}

	public function testEmailLeavingIsRememberedAsHash(): void {
		// Never the address itself in the app config, same key as the check in syncEmailMembers()
		$this->appConfig->method('getAppValueArray')->willReturn([]);
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [7 => ['emailLeft' => [self::hmac('guest@example.tld') => true]]]);

		$this->service->rememberEmailLeave($this->room, ' Guest@Example.TLD ');
	}

	public static function dataIsMemberOfTeam(): array {
		return [
			'direct member' => [Attendee::ACTOR_USERS, 'alice', null, true],
			'no direct member' => [Attendee::ACTOR_USERS, 'carol', null, false],
			'remote member, other casing of the server' => [Attendee::ACTOR_FEDERATED_USERS, 'bob@Remote.Example', null, true],
			'remote user of another server' => [Attendee::ACTOR_FEDERATED_USERS, 'bob@other.example', null, false],
			'email member' => [Attendee::ACTOR_EMAILS, hash('sha256', 'guest@example.tld'), 'Guest@Example.TLD', true],
			'other email address' => [Attendee::ACTOR_EMAILS, hash('sha256', 'other@example.tld'), 'other@example.tld', false],
			'guest' => [Attendee::ACTOR_GUESTS, 'random', null, false],
		];
	}

	#[DataProvider('dataIsMemberOfTeam')]
	public function testIsMemberOfTeam(string $actorType, string $actorId, ?string $invitedCloudId, bool $expected): void {
		$this->circlesAdapter->method('getDirectMember')
			->willReturnCallback(static fn (string $teamId, string $userId): ?array => $userId === 'alice' ? ['level' => self::LEVEL_MEMBER, 'joined' => 1] : null);
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'Guest']);

		$attendee = Attendee::fromRow(['actor_type' => $actorType, 'actor_id' => $actorId, 'invited_cloud_id' => $invitedCloudId]);
		$this->assertSame($expected, $this->service->isMemberOfTeam($this->room, $attendee));
	}

	public static function dataIsMemberOfTeamWhenCirclesFails(): array {
		return [
			// Nobody is a member of a deleted team: they can leave its archived conversation
			'team deleted' => [new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')), false],
			// Nothing is changed while Circles is unavailable
			'Circles unavailable' => [new \Exception('Circles App unavailable'), true],
		];
	}

	#[DataProvider('dataIsMemberOfTeamWhenCirclesFails')]
	public function testIsMemberOfTeamWhenCirclesFails(\Exception $exception, bool $expected): void {
		$this->circlesAdapter->method('getDirectMember')->willThrowException($exception);

		$attendee = Attendee::fromRow(['actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']);
		$this->assertSame($expected, $this->service->isMemberOfTeam($this->room, $attendee));
	}

	public static function dataInvitationRetryPause(): array {
		return [
			'first failure' => [1, 300],
			'second failure' => [2, 600],
			'fifth failure' => [5, 4800],
			'ninth failure' => [9, 76800],
			'never longer than a day' => [10, 86400],
			'also after many failures' => [1000, 86400],
		];
	}

	#[DataProvider('dataInvitationRetryPause')]
	public function testInvitationRetryPauseGrows(int $failures, int $pause): void {
		$this->assertSame($pause, TeamRoomSyncService::getInvitationRetryPause($failures));
	}

	public function testRepeatedFailureWaitsLonger(): void {
		$this->mockRemoteBob();
		// Third try after two failures
		$this->appConfig->method('getAppValueArray')->willReturn([7 => ['wait' => [self::hmac('bob@remote.example') => ['count' => 2, 'until' => 1000000 - 1]]]]);
		$this->participantService->method('addUsers')->willThrowException(new CannotReachRemoteException());

		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [7 => ['wait' => [self::hmac('bob@remote.example') => ['count' => 3, 'until' => 1000000 + 1200]]]]);

		$this->syncFederatedMembers();
	}

	public function testLeavingTheRoomLeavesTheTeamAsTheUser(): void {
		$this->mockSnapshot(['alice' => self::LEVEL_MEMBER]);
		$during = null;
		$this->circlesAdapter->expects($this->once())->method('leaveTeam')->with('team1', 'alice')
			->willReturnCallback(function () use (&$during): void {
				// Circles applies the leave in this process on the command line: its event arrives now
				$during = $this->service->isChangingTeamFromTalk();
			});

		$this->assertFalse($this->service->isChangingTeamFromTalk());
		$this->service->leaveTeamOfRoom($this->room, 'alice');

		$this->assertTrue($during);
		$this->assertFalse($this->service->isChangingTeamFromTalk());
	}

	public function testStaleParticipantOnlyLeavesTheRoom(): void {
		$this->mockSnapshot(['bob' => self::LEVEL_OWNER]);
		$this->circlesAdapter->expects($this->never())->method('leaveTeam');

		$this->service->leaveTeamOfRoom($this->room, 'alice');
	}

	public function testLeavingTheRoomOfADeletedTeam(): void {
		$this->circlesAdapter->method('getDirectMember')
			->willThrowException(new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')));
		$this->circlesAdapter->expects($this->never())->method('leaveTeam');

		$this->service->leaveTeamOfRoom($this->room, 'alice');
	}

	public static function dataLeaveRefused(): array {
		return [
			'Circles unavailable' => [new \Exception('Circles App unavailable')],
			'refused by the team' => [new \Exception('Not allowed', 0, new \RuntimeException('Not allowed'))],
		];
	}

	#[DataProvider('dataLeaveRefused')]
	public function testRefusedLeaveIsReported(\Exception $exception): void {
		$this->mockSnapshot(['alice' => self::LEVEL_MEMBER]);
		$this->circlesAdapter->method('leaveTeam')->willThrowException($exception);

		$this->expectExceptionObject($exception);
		try {
			$this->service->leaveTeamOfRoom($this->room, 'alice');
		} finally {
			$this->assertFalse($this->service->isChangingTeamFromTalk());
		}
	}

	public function testRemovingFromTheRoomRemovesFromTheTeamAsTheModerator(): void {
		$this->mockSnapshot(['bob' => self::LEVEL_MEMBER]);
		$during = null;
		$this->circlesAdapter->expects($this->once())->method('removeDirectUser')->with('team1', 'bob', 'alice')
			->willReturnCallback(function () use (&$during): void {
				$during = $this->service->isChangingTeamFromTalk();
			});

		$this->service->removeFromTeamRoom($this->room, 'bob', 'alice');

		$this->assertTrue($during);
		$this->assertFalse($this->service->isChangingTeamFromTalk());
	}

	public function testRemovingAStaleParticipantOnlyRemovesFromTheRoom(): void {
		$this->mockSnapshot(['alice' => self::LEVEL_OWNER]);
		$this->circlesAdapter->expects($this->never())->method('removeDirectUser');

		$this->service->removeFromTeamRoom($this->room, 'bob', 'alice');
	}

	public function testRemovingFromTheRoomOfADeletedTeam(): void {
		$this->circlesAdapter->method('getDirectMember')
			->willThrowException(new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')));
		$this->circlesAdapter->expects($this->never())->method('removeDirectUser');

		$this->service->removeFromTeamRoom($this->room, 'bob', 'alice');
	}

	public function testRefusedRemovalIsReported(): void {
		$this->mockSnapshot(['bob' => self::LEVEL_ADMIN]);
		$exception = new \Exception('Insufficient rights', 0, new \RuntimeException('Insufficient rights'));
		$this->circlesAdapter->method('removeDirectUser')->willThrowException($exception);

		$this->expectExceptionObject($exception);
		$this->service->removeFromTeamRoom($this->room, 'bob', 'alice');
	}

	public function testAddingToTheRoomAddsToTheTeamAsTheModerator(): void {
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$this->circlesAdapter->expects($this->once())->method('addUser')->with('team1', 'bob', 'alice')->willReturn(true);
		$participant = $this->makeUserParticipant('bob');
		// syncUserInTeamRoom() reads the member again: added by now
		$this->circlesAdapter->method('getDirectMember')->willReturnOnConsecutiveCalls(null, ['level' => self::LEVEL_MEMBER, 'joined' => 2]);
		$lookups = 0;
		$this->participantService->method('getParticipant')->willReturnCallback(function () use (&$lookups, $participant): Participant {
			if ($lookups++ === 0) {
				throw new ParticipantNotFoundException();
			}
			return $participant;
		});
		$this->participantService->expects($this->once())->method('addUsers');

		$this->assertSame($participant, $this->service->addToTeamRoom($this->room, $bob, 'alice'));
	}

	public function testInvitedUsersAreAddedAfterAccepting(): void {
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$this->circlesAdapter->method('getDirectMember')->willReturn(null);
		$this->circlesAdapter->expects($this->once())->method('addUser')->willReturn(false);
		$this->participantService->expects($this->never())->method('addUsers');

		$this->assertNull($this->service->addToTeamRoom($this->room, $bob, 'alice'));
	}

	public function testMissingMembersAreOnlyAddedToTheRoom(): void {
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_MEMBER, 'joined' => 2]);
		$participant = $this->makeUserParticipant('bob');
		// Not in the conversation yet, then the participant that was added
		$lookups = 0;
		$this->participantService->method('getParticipant')->willReturnCallback(function () use (&$lookups, $participant): Participant {
			if ($lookups++ === 0) {
				throw new ParticipantNotFoundException();
			}
			return $participant;
		});
		$this->circlesAdapter->expects($this->never())->method('addUser');
		$this->participantService->expects($this->once())->method('addUsers');

		$this->assertSame($participant, $this->service->addToTeamRoom($this->room, $bob, 'alice'));
	}

	public function testBannedUsersAreNotAddedToTheTeam(): void {
		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$this->banService->method('throwIfActorIsBanned')->with($this->room, 'bob')->willThrowException(new ForbiddenException('actor'));
		$this->circlesAdapter->expects($this->never())->method('addUser');

		$this->expectException(ForbiddenException::class);
		$this->service->addToTeamRoom($this->room, $bob, 'alice');
	}

	public static function dataLevelForParticipantType(): array {
		return [
			// Talk owners are team admins: only the team owner transfers the ownership
			'owner' => [Participant::OWNER, self::LEVEL_ADMIN],
			'moderator' => [Participant::MODERATOR, self::LEVEL_MODERATOR],
			'user' => [Participant::USER, self::LEVEL_MEMBER],
		];
	}

	#[DataProvider('dataLevelForParticipantType')]
	public function testLevelForParticipantType(int $participantType, int $level): void {
		$this->assertSame($level, TeamRoomSyncService::getLevelForParticipantType($participantType));
	}

	private function mockRoleChange(int $newType): array {
		$actor = $this->makeUserParticipant('alice', Participant::OWNER);
		$target = $this->makeUserParticipant('bob');
		$this->participantService->method('getParticipantTypeChangeByModerator')->willReturn($newType);
		$this->mockUsers();
		$this->participantService->method('getParticipant')->willReturn($target);
		return [$actor, $target];
	}

	public function testRoleChangeIsDoneInTheTeamAsTheModerator(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::MODERATOR);
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_MEMBER, 'joined' => 1]);
		$this->circlesAdapter->expects($this->once())->method('setMemberLevel')->with('team1', 'bob', self::LEVEL_MODERATOR, 'alice');

		$this->assertSame(Participant::MODERATOR, $this->service->changeRoleInTeam($this->room, $actor, $target, true));
	}

	public function testTeamOwnerSteppingDownHandsTheTeamOver(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::MODERATOR);
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_OWNER, 'joined' => 1]);
		$this->circlesAdapter->method('getDirectMembers')->willReturn([
			'bob' => ['level' => self::LEVEL_OWNER, 'joined' => 1],
			'carol' => ['level' => self::LEVEL_ADMIN, 'joined' => 3],
			'dave' => ['level' => self::LEVEL_ADMIN, 'joined' => 2],
			'erin' => ['level' => self::LEVEL_MODERATOR, 'joined' => 1],
		]);
		$changes = [];
		$this->circlesAdapter->method('setMemberLevel')->willReturnCallback(static function (string $teamId, string $userId, int $level, string $actorUserId) use (&$changes): void {
			$changes[] = [$userId, $level, $actorUserId];
		});

		$this->service->changeRoleInTeam($this->room, $actor, $target, false);

		// The admin who is member the longest takes over first
		$this->assertSame([['dave', self::LEVEL_OWNER, 'alice'], ['bob', self::LEVEL_MODERATOR, 'alice']], $changes);
	}

	public function testTeamOwnerCanNotStepDownWithoutAdmin(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::MODERATOR);
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_OWNER, 'joined' => 1]);
		$this->circlesAdapter->method('getDirectMembers')->willReturn([
			'bob' => ['level' => self::LEVEL_OWNER, 'joined' => 1],
			'erin' => ['level' => self::LEVEL_MODERATOR, 'joined' => 1],
		]);
		$this->circlesAdapter->expects($this->never())->method('setMemberLevel');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_LAST_MODERATOR);
		$this->service->changeRoleInTeam($this->room, $actor, $target, false);
	}

	public function testTeamOwnerStaysOwner(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::OWNER);
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_OWNER, 'joined' => 1]);
		$this->circlesAdapter->expects($this->never())->method('setMemberLevel');

		$this->assertSame(Participant::OWNER, $this->service->changeRoleInTeam($this->room, $actor, $target, true, Participant::OWNER));
	}

	public function testFederatedModeratorsCanNotChangeTeamRoles(): void {
		[, $target] = $this->mockRoleChange(Participant::MODERATOR);
		$actor = $this->makeFederatedParticipant('carol@remote.example', Participant::MODERATOR);
		$this->circlesAdapter->expects($this->never())->method('setMemberLevel');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_MODERATOR);
		$this->service->changeRoleInTeam($this->room, $actor, $target, true);
	}

	public function testRoleOfAFormerMemberIsNotChanged(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::MODERATOR);
		$this->circlesAdapter->method('getDirectMember')->willReturn(null);
		$this->circlesAdapter->expects($this->never())->method('setMemberLevel');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_TYPE);
		$this->service->changeRoleInTeam($this->room, $actor, $target, true);
	}

	public function testTeamRefusingTheRoleChangeIsReported(): void {
		[$actor, $target] = $this->mockRoleChange(Participant::OWNER);
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => self::LEVEL_MEMBER, 'joined' => 1]);
		$exception = new \Exception('Insufficient rights', 0, new \RuntimeException('Insufficient rights'));
		$this->circlesAdapter->method('setMemberLevel')->willThrowException($exception);
		$this->participantService->expects($this->never())->method('updateParticipantType');

		$this->expectExceptionObject($exception);
		$this->service->changeRoleInTeam($this->room, $actor, $target, true, Participant::OWNER);
	}

	/**
	 * Members of the team that removeMatchingMember() is called with: the first one that matches is removed
	 *
	 * @param list<Member> $members
	 * @param-out list<Member> $removed
	 */
	private function mockRemoveMatchingMember(array $members, ?array &$removed, ?bool &$changingFromTalk = null): void {
		$removed = [];
		$this->circlesAdapter->method('removeMatchingMember')
			->willReturnCallback(function (string $teamId, callable $match, string $actorUserId) use ($members, &$removed, &$changingFromTalk): bool {
				$this->assertSame('team1', $teamId);
				$this->assertSame('alice', $actorUserId);
				$changingFromTalk = $this->service->isChangingTeamFromTalk();
				foreach ($members as $member) {
					if ($match($member)) {
						$removed[] = $member;
						return true;
					}
				}
				return false;
			});
	}

	public function testRemovingAFederatedParticipantRemovesTheMemberOfTheirServer(): void {
		// Same user id locally and on another server: only the member of their server
		$local = $this->makeCirclesMember('bob');
		$other = $this->makeCirclesMember('bob', 'other.example');
		$theirs = $this->makeCirclesMember('bob', 'Remote.Example');
		$this->mockRemoveMatchingMember([$local, $other, $theirs], $removed, $changingFromTalk);

		$this->service->removeFederatedFromTeamRoom($this->room, 'bob@remote.example', 'alice');

		$this->assertSame([$theirs], $removed);
		$this->assertTrue($changingFromTalk);
		$this->assertFalse($this->service->isChangingTeamFromTalk());
	}

	public function testRemovingAFederatedParticipantThatLeftTheTeam(): void {
		// Stale participant: nothing to remove in the team, removing from the room is enough
		$this->mockRemoveMatchingMember([$this->makeCirclesMember('carol', 'remote.example')], $removed);

		$this->service->removeFederatedFromTeamRoom($this->room, 'bob@remote.example', 'alice');

		$this->assertSame([], $removed);
	}

	public function testRemovingAFederatedParticipantOfADeletedTeam(): void {
		$this->circlesAdapter->method('removeMatchingMember')
			->willThrowException(new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')));

		$this->service->removeFederatedFromTeamRoom($this->room, 'bob@remote.example', 'alice');
		$this->assertFalse($this->service->isChangingTeamFromTalk());
	}

	public function testRefusedRemovalOfAFederatedParticipantIsReported(): void {
		$exception = new \Exception('Insufficient rights', 0, new \RuntimeException('Insufficient rights'));
		$this->circlesAdapter->method('removeMatchingMember')->willThrowException($exception);

		$this->expectExceptionObject($exception);
		$this->service->removeFederatedFromTeamRoom($this->room, 'bob@remote.example', 'alice');
	}

	public function testRemovingAnEmailGuestRemovesTheEmailMember(): void {
		$other = $this->createMock(Member::class);
		$guest = $this->createMock(Member::class);
		// Email and contact members: the address is the one of the member
		$this->circlesAdapter->method('getEmailOfMember')
			->willReturnCallback(static fn (Member $member): ?string => $member === $guest ? 'guest@example.tld' : 'other@example.tld');
		$this->mockRemoveMatchingMember([$other, $guest], $removed, $changingFromTalk);

		$this->service->removeEmailFromTeamRoom($this->room, ' Guest@Example.TLD ', 'alice');

		$this->assertSame([$guest], $removed);
		$this->assertTrue($changingFromTalk);
	}

	public function testRemovingAnEmailGuestThatLeftTheTeam(): void {
		$this->circlesAdapter->method('getEmailOfMember')->willReturn('other@example.tld');
		$this->mockRemoveMatchingMember([$this->createMock(Member::class)], $removed);

		$this->service->removeEmailFromTeamRoom($this->room, 'guest@example.tld', 'alice');

		$this->assertSame([], $removed);
	}

	public function testRemovingAnEmailGuestOfADeletedTeam(): void {
		$this->circlesAdapter->method('removeMatchingMember')
			->willThrowException(new \Exception('Circle not found', 0, new CircleNotFoundException('Circle not found')));

		$this->service->removeEmailFromTeamRoom($this->room, 'guest@example.tld', 'alice');
		$this->assertFalse($this->service->isChangingTeamFromTalk());
	}

	private function makeAlice(): IUser&MockObject {
		$alice = $this->createMock(IUser::class);
		$alice->method('getUID')->willReturn('alice');
		return $alice;
	}

	public function testAddingAFederatedUserAddsThemToTheTeamAndInvitesThem(): void {
		$alice = $this->makeAlice();
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([]);
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$this->circlesAdapter->expects($this->once())->method('addRemoteUser')->with('team1', 'bob', 'remote.example', 'alice')->willReturn(true);

		// In the name of the acting user, as member
		$this->federationManager->expects($this->once())->method('isAllowedToInvite')->with($alice, $this->anything(), $this->room);
		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, [[
				'actorType' => Attendee::ACTOR_FEDERATED_USERS,
				'actorId' => 'bob@remote.example',
				'displayName' => 'bob@remote.example',
				'participantType' => Participant::USER,
			]], $alice);

		$this->assertTrue($this->service->addFederatedToTeamRoom($this->room, $this->makeCloudId('bob', 'https://remote.example'), $alice));
	}

	public function testAddingAFederatedMemberThatDeclinedInvitesThemAgain(): void {
		$alice = $this->makeAlice();
		// Moderator of the team already, declined the invitation before
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'Remote.Example', 'displayName' => 'Bob', 'level' => self::LEVEL_MODERATOR],
		]);
		$stored = [7 => ['left' => [self::hmac('bob@remote.example') => true]]];
		$this->appConfig->method('getAppValueArray')->willReturnCallback(static function () use (&$stored): array {
			return $stored;
		});
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());

		$this->circlesAdapter->expects($this->never())->method('addRemoteUser');
		// The "left" record is deleted, nothing else is written
		$this->appConfig->expects($this->once())
			->method('setAppValueArray')
			->with(Config::TEAM_FEDERATED_MEMBERS, [])
			->willReturnCallback(static function (string $key, array $value) use (&$stored): bool {
				$stored = $value;
				return true;
			});
		$this->participantService->expects($this->once())
			->method('addUsers')
			->with($this->room, $this->callback(static fn (array $participants): bool => $participants[0]['participantType'] === Participant::MODERATOR), $alice);

		$this->assertTrue($this->service->addFederatedToTeamRoom($this->room, $this->makeCloudId('bob', 'https://remote.example'), $alice));
	}

	public function testFederatedUserOnlyInvitedToAnInviteOnlyTeam(): void {
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([]);
		$this->circlesAdapter->method('addRemoteUser')->willReturn(false);
		$this->participantService->expects($this->never())->method('addUsers');

		$this->assertFalse($this->service->addFederatedToTeamRoom($this->room, $this->makeCloudId('bob', 'https://remote.example'), $this->makeAlice()));
	}

	public function testFederatedParticipantIsNotInvitedTwice(): void {
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([
			['userId' => 'bob', 'instance' => 'remote.example', 'displayName' => 'Bob', 'level' => self::LEVEL_MEMBER],
		]);
		$this->participantService->method('getParticipantByActor')->willReturn($this->makeFederatedParticipant('bob@remote.example'));
		$this->participantService->expects($this->never())->method('addUsers');

		$this->assertTrue($this->service->addFederatedToTeamRoom($this->room, $this->makeCloudId('bob', 'https://remote.example'), $this->makeAlice()));
	}

	public function testTeamRefusingTheFederatedUserIsReported(): void {
		$this->circlesAdapter->method('getRemoteMembers')->willReturn([]);
		$exception = new \Exception('Federated members not allowed');
		$this->circlesAdapter->method('addRemoteUser')->willThrowException($exception);

		$this->expectExceptionObject($exception);
		$this->service->addFederatedToTeamRoom($this->room, $this->makeCloudId('bob', 'https://remote.example'), $this->makeAlice());
	}

	public function testAddingAnEmailAddressAddsItToTheTeamAndInvitesIt(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn([]);
		$this->circlesAdapter->expects($this->once())->method('addEmailMember')->with('team1', 'guest@example.tld', 'alice')->willReturn(true);
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$participant = $this->makeEmailParticipant('guest@example.tld');

		// The invitation mail right away
		$this->participantService->expects($this->once())
			->method('inviteEmailAddress')
			->with($this->room, hash('sha256', 'guest@example.tld'), 'guest@example.tld', null)
			->willReturn($participant);
		$this->guestManager->expects($this->once())->method('sendEmailInvitation')->with($this->room, $participant);

		$this->assertTrue($this->service->addEmailToTeamRoom($this->room, ' Guest@Example.TLD ', 'alice'));
	}

	public function testAddingAnEmailMemberThatLeftInvitesItAgain(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'Guest']);
		$this->appConfig->method('getAppValueArray')->willReturn([7 => ['emailLeft' => [self::hmac('guest@example.tld') => true]]]);
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());

		$this->circlesAdapter->expects($this->never())->method('addEmailMember');
		// The "left" record is deleted
		$this->appConfig->expects($this->once())->method('setAppValueArray')->with(Config::TEAM_FEDERATED_MEMBERS, []);
		$this->participantService->expects($this->once())
			->method('inviteEmailAddress')
			->with($this->room, hash('sha256', 'guest@example.tld'), 'guest@example.tld', 'Guest')
			->willReturn($this->makeEmailParticipant('guest@example.tld'));

		$this->assertTrue($this->service->addEmailToTeamRoom($this->room, 'guest@example.tld', 'alice'));
	}

	public function testEmailAddressNotAddedByTheTeamIsNotInvited(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn([]);
		$this->circlesAdapter->method('addEmailMember')->willReturn(false);
		$this->participantService->expects($this->never())->method('inviteEmailAddress');

		$this->assertFalse($this->service->addEmailToTeamRoom($this->room, 'guest@example.tld', 'alice'));
	}

	public function testEmailGuestIsNotInvitedTwice(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn(['guest@example.tld' => 'guest@example.tld']);
		$this->participantService->method('getParticipantByActor')->willReturn($this->makeEmailParticipant('guest@example.tld'));
		$this->participantService->expects($this->never())->method('inviteEmailAddress');

		$this->assertTrue($this->service->addEmailToTeamRoom($this->room, 'guest@example.tld', 'alice'));
	}

	public function testTeamRefusingTheEmailAddressIsReported(): void {
		$this->circlesAdapter->method('getEmailMembers')->willReturn([]);
		$exception = new \Exception('Insufficient rights');
		$this->circlesAdapter->method('addEmailMember')->willThrowException($exception);

		$this->expectExceptionObject($exception);
		$this->service->addEmailToTeamRoom($this->room, 'guest@example.tld', 'alice');
	}

	/**
	 * Members of the team that setMatchingMemberLevel() is called with: the level of the first one that matches is set
	 *
	 * @param list<Member> $members
	 */
	private function mockSetMatchingMemberLevel(array $members, ?array &$changed): void {
		$changed = [];
		$this->circlesAdapter->method('setMatchingMemberLevel')
			->willReturnCallback(function (string $teamId, callable $match, int $level, string $actorUserId) use ($members, &$changed): bool {
				$this->assertSame('team1', $teamId);
				$this->assertSame('alice', $actorUserId);
				foreach ($members as $member) {
					if ($match($member)) {
						$changed[] = [$member, $level];
						return true;
					}
				}
				return false;
			});
	}

	public function testFederatedRoleIsChangedInTheTeamAsTheModerator(): void {
		$actor = $this->makeUserParticipant('alice', Participant::OWNER);
		$target = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantTypeChangeByModerator')->willReturn(Participant::MODERATOR);
		// Same user id on another server: only the member of their server
		$other = $this->makeCirclesMember('bob', 'other.example');
		$theirs = $this->makeCirclesMember('bob', 'remote.example');
		$this->mockSetMatchingMemberLevel([$other, $theirs], $changed);

		$this->participantService->expects($this->once())->method('updateParticipantType')->with($this->room, $target, Participant::MODERATOR);

		$this->assertSame(Participant::MODERATOR, $this->service->changeFederatedRoleInTeam($this->room, $actor, $target, true));
		$this->assertSame([[$theirs, self::LEVEL_MODERATOR]], $changed);
	}

	public function testFederatedModeratorsCanNotChangeFederatedRoles(): void {
		$actor = $this->makeFederatedParticipant('carol@remote.example', Participant::MODERATOR);
		$target = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantTypeChangeByModerator')->willReturn(Participant::MODERATOR);
		$this->circlesAdapter->expects($this->never())->method('setMatchingMemberLevel');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_MODERATOR);
		$this->service->changeFederatedRoleInTeam($this->room, $actor, $target, true);
	}

	public function testFederatedRoleOfAFormerMemberIsNotChanged(): void {
		$actor = $this->makeUserParticipant('alice', Participant::OWNER);
		$target = $this->makeFederatedParticipant('bob@remote.example');
		$this->participantService->method('getParticipantTypeChangeByModerator')->willReturn(Participant::MODERATOR);
		$this->mockSetMatchingMemberLevel([$this->makeCirclesMember('carol', 'remote.example')], $changed);
		$this->participantService->expects($this->never())->method('updateParticipantType');

		$this->expectException(ParticipantTypeException::class);
		$this->expectExceptionMessage(ParticipantTypeException::REASON_TYPE);
		$this->service->changeFederatedRoleInTeam($this->room, $actor, $target, true);
	}

	public function testTeamRefusingTheFederatedRoleChangeKeepsTheRole(): void {
		$actor = $this->makeUserParticipant('alice', Participant::MODERATOR);
		$target = $this->makeFederatedParticipant('bob@remote.example', Participant::MODERATOR);
		$this->participantService->method('getParticipantTypeChangeByModerator')->willReturn(Participant::USER);
		$exception = new \Exception('Insufficient rights');
		$this->circlesAdapter->method('setMatchingMemberLevel')->willThrowException($exception);
		$this->participantService->expects($this->never())->method('updateParticipantType');

		$this->expectExceptionObject($exception);
		$this->service->changeFederatedRoleInTeam($this->room, $actor, $target, false);
	}
}
