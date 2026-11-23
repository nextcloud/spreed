<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\TeamRoomSettingsService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamRoomSettingsServiceTest extends TestCase {
	protected CirclesAdapter&MockObject $circlesAdapter;
	protected RoomService&MockObject $roomService;
	protected IUserSession&MockObject $userSession;
	protected TeamRoomSettingsService $service;

	public function setUp(): void {
		parent::setUp();

		$this->circlesAdapter = $this->createMock(CirclesAdapter::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->service = new TeamRoomSettingsService(
			$this->circlesAdapter,
			$this->roomService,
			$this->createMock(LoggerInterface::class),
			$this->userSession,
		);
	}

	private function makeRoom(string $objectType = Room::OBJECT_TYPE_TEAM, int $listable = Room::LISTABLE_NONE): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('isTeamManaged')->willReturn($objectType === Room::OBJECT_TYPE_TEAM);
		$room->method('getObjectId')->willReturn('team1');
		$room->method('getName')->willReturn('New name');
		$room->method('getDescription')->willReturn('New description');
		$room->method('getListable')->willReturn($listable);
		return $room;
	}

	private function loggedInAs(string $userId): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$this->userSession->method('getUser')->willReturn($user);
	}

	/**
	 * @return array{id: string, name: string, description: string, config: int, local: bool}
	 */
	private static function makeTeam(int $config = 0): array {
		return ['id' => 'team1', 'name' => 'Team name', 'description' => 'Team description', 'config' => $config, 'local' => true];
	}

	public static function dataIsSupportedTeam(): array {
		return [
			'normal team' => [0, true],
			'visible and open' => [8 | 16, true],
			'personal team of a user' => [1, false],
			'personal circle' => [2, false],
			'system circle' => [4, false],
			'hidden' => [1024, false],
			'backend' => [2048, false],
			'managed by an app' => [131072, false],
			'managed by SCIM' => [262144 | 8, false],
		];
	}

	#[DataProvider('dataIsSupportedTeam')]
	public function testIsSupportedTeam(int $config, bool $expected): void {
		$this->assertSame($expected, TeamRoomSettingsService::isSupportedTeam($config));
	}

	public static function dataListable(): array {
		$visible = TeamRoomSettingsService::TEAM_CFG_VISIBLE;
		$open = TeamRoomSettingsService::TEAM_CFG_OPEN;
		return [
			'closed team' => [0, Room::LISTABLE_NONE],
			'visible only' => [$visible, Room::LISTABLE_NONE],
			'open only' => [$open, Room::LISTABLE_NONE],
			'visible and open' => [$visible | $open, Room::LISTABLE_USERS],
		];
	}

	#[DataProvider('dataListable')]
	public function testListableOfTheRoomFollowsTheTeam(int $config, int $listable): void {
		$this->assertSame($listable, TeamRoomSettingsService::getListableForConfig($config));
	}

	public function testListableRoomMakesTheTeamVisibleAndOpen(): void {
		$visible = TeamRoomSettingsService::TEAM_CFG_VISIBLE;
		$open = TeamRoomSettingsService::TEAM_CFG_OPEN;
		$invite = TeamRoomSettingsService::TEAM_CFG_INVITE;

		$this->assertSame($invite | $visible | $open, TeamRoomSettingsService::getConfigForListable($invite, Room::LISTABLE_USERS));
		$this->assertSame($invite | $visible | $open, TeamRoomSettingsService::getConfigForListable($invite, Room::LISTABLE_ALL));
		// Not listable: closed, but still visible
		$this->assertSame($invite | $visible, TeamRoomSettingsService::getConfigForListable($invite | $visible | $open, Room::LISTABLE_NONE));
	}

	public function testTeamIsAppliedToTheRoom(): void {
		$room = $this->makeRoom();
		$this->roomService->expects($this->once())->method('setName')->with($room, 'Team name');
		$this->roomService->expects($this->once())->method('setDescription')->with($room, 'Team description');
		$this->roomService->expects($this->once())->method('setListable')->with($room, Room::LISTABLE_NONE);

		$this->service->applyTeamToRoom($room, self::makeTeam());
	}

	public function testTeamIsLoadedWhenNotGiven(): void {
		$room = $this->makeRoom();
		$this->circlesAdapter->expects($this->once())->method('getTeam')->with('team1')->willReturn(self::makeTeam());
		$this->roomService->expects($this->once())->method('setName')->with($room, 'Team name');

		$this->service->applyTeamToRoom($room);
	}

	public function testTeamChangesAppliedToTheRoomAreNotWrittenBackToTheTeam(): void {
		$this->loggedInAs('alice');
		$room = $this->makeRoom();
		// RoomService dispatches RoomModifiedEvent, TeamRoomSettingsListener calls applyRoomToTeam()
		$this->roomService->method('setName')->willReturnCallback(function (Room $room): void {
			$this->service->applyRoomToTeam($room, ARoomModifiedEvent::PROPERTY_NAME);
		});
		$this->circlesAdapter->expects($this->never())->method('updateTeam');

		$this->service->applyTeamToRoom($room, self::makeTeam());
	}

	public function testRoomChangesAreWrittenAgainAfterTheTeamWasApplied(): void {
		$this->loggedInAs('alice');
		$room = $this->makeRoom();
		$this->roomService->method('setName')->willThrowException(new \RuntimeException('failed'));
		try {
			$this->service->applyTeamToRoom($room, self::makeTeam());
			$this->fail('Exception expected');
		} catch (\RuntimeException) {
		}

		$this->circlesAdapter->expects($this->once())->method('updateTeam');
		$this->service->applyRoomToTeam($room, ARoomModifiedEvent::PROPERTY_NAME);
	}

	public static function dataRoomChange(): array {
		return [
			'name' => [ARoomModifiedEvent::PROPERTY_NAME, ['name' => 'New name']],
			'description' => [ARoomModifiedEvent::PROPERTY_DESCRIPTION, ['description' => 'New description']],
		];
	}

	#[DataProvider('dataRoomChange')]
	public function testRoomChangesAreWrittenAsTheLoggedInUser(string $property, array $settings): void {
		$this->loggedInAs('alice');
		$this->circlesAdapter->expects($this->once())
			->method('updateTeam')
			->with('team1', $settings, 'alice');

		$this->service->applyRoomToTeam($this->makeRoom(), $property);
	}

	public function testRoomChangesWithoutLoggedInUserAreNotWrittenToTheTeam(): void {
		// Background jobs, occ and federated requests: never as super user
		$this->userSession->method('getUser')->willReturn(null);
		$this->circlesAdapter->expects($this->never())->method('updateTeam');

		$this->service->applyRoomToTeam($this->makeRoom(), ARoomModifiedEvent::PROPERTY_NAME);
	}

	public function testListableIsOnlyWrittenWhenTheTeamConfigChanges(): void {
		$this->loggedInAs('alice');
		$visibleAndOpen = TeamRoomSettingsService::TEAM_CFG_VISIBLE | TeamRoomSettingsService::TEAM_CFG_OPEN;
		$this->circlesAdapter->method('getTeam')->willReturn(self::makeTeam($visibleAndOpen));
		$this->circlesAdapter->expects($this->never())->method('updateTeam');

		$this->service->applyRoomToTeam($this->makeRoom(Room::OBJECT_TYPE_TEAM, Room::LISTABLE_USERS), ARoomModifiedEvent::PROPERTY_LISTABLE);
	}

	public function testListableIsWrittenToTheTeam(): void {
		$this->loggedInAs('alice');
		$this->circlesAdapter->method('getTeam')->willReturn(self::makeTeam(0));
		$this->circlesAdapter->expects($this->once())
			->method('updateTeam')
			->with('team1', ['config' => TeamRoomSettingsService::TEAM_CFG_VISIBLE | TeamRoomSettingsService::TEAM_CFG_OPEN], 'alice');

		$this->service->applyRoomToTeam($this->makeRoom(Room::OBJECT_TYPE_TEAM, Room::LISTABLE_USERS), ARoomModifiedEvent::PROPERTY_LISTABLE);
	}

	public function testTeamRefusingTheChangeIsOnlyLogged(): void {
		$this->loggedInAs('bob');
		// No exception: the room change stays, the next resync applies the team again
		$this->circlesAdapter->expects($this->once())->method('updateTeam')->willThrowException(new \Exception('Not an admin of the team'));

		$this->service->applyRoomToTeam($this->makeRoom(), ARoomModifiedEvent::PROPERTY_NAME);
	}

	/**
	 * A real participant: canEditTeamSettings() asks it (isOwner()), a mock would answer false
	 */
	private function makeParticipant(string $actorType, int $participantType): Participant {
		$attendee = Attendee::fromRow([
			'actor_type' => $actorType,
			'actor_id' => 'alice',
			'participant_type' => $participantType,
		]);
		return new Participant($this->createMock(Room::class), $attendee, null);
	}

	public static function dataCanEditTeamSettings(): array {
		return [
			'team owner or admin' => [Room::OBJECT_TYPE_TEAM, Attendee::ACTOR_USERS, Participant::OWNER, true],
			'team moderator' => [Room::OBJECT_TYPE_TEAM, Attendee::ACTOR_USERS, Participant::MODERATOR, false],
			'team member' => [Room::OBJECT_TYPE_TEAM, Attendee::ACTOR_USERS, Participant::USER, false],
			'federated moderator' => [Room::OBJECT_TYPE_TEAM, Attendee::ACTOR_FEDERATED_USERS, Participant::MODERATOR, false],
			'moderator of another conversation' => ['', Attendee::ACTOR_USERS, Participant::MODERATOR, true],
		];
	}

	#[DataProvider('dataCanEditTeamSettings')]
	public function testCanEditTeamSettings(string $objectType, string $actorType, int $participantType, bool $expected): void {
		$this->assertSame($expected, TeamRoomSettingsService::canEditTeamSettings($this->makeRoom($objectType), $this->makeParticipant($actorType, $participantType)));
	}

	public function testNobodyCanEditTeamSettingsWithoutParticipant(): void {
		$this->assertFalse(TeamRoomSettingsService::canEditTeamSettings($this->makeRoom(), null));
	}
}
