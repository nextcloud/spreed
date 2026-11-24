<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Circles\Events\EditingCircleEvent;
use OCA\Circles\Model\Circle;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Listener\TeamRoomSettingsListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CirclesAdapter;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\TeamRoomSettingsService;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamRoomSettingsListenerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected TeamRoomSettingsService&MockObject $settingsService;
	protected CirclesAdapter&MockObject $circlesAdapter;
	protected ParticipantService&MockObject $participantService;
	protected LoggerInterface&MockObject $logger;
	protected TeamRoomSettingsListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->settingsService = $this->createMock(TeamRoomSettingsService::class);
		$this->circlesAdapter = $this->createMock(CirclesAdapter::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new TeamRoomSettingsListener(
			$this->manager,
			$this->settingsService,
			$this->circlesAdapter,
			$this->participantService,
			$this->logger,
		);
	}

	private function makeRoom(string $objectType = Room::OBJECT_TYPE_TEAM): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('isTeamManaged')->willReturn($objectType === Room::OBJECT_TYPE_TEAM);
		$room->method('getObjectId')->willReturn('team1');
		$room->method('getToken')->willReturn('teamroom');
		return $room;
	}

	private function makeTeamEditedEvent(): EditingCircleEvent&MockObject {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team1');
		$event = $this->createMock(EditingCircleEvent::class);
		$event->method('getCircle')->willReturn($circle);
		return $event;
	}

	public function testTeamChangesAreAppliedToItsRoom(): void {
		$room = $this->makeRoom();
		$this->manager->method('getTeamRoom')->with('team1')->willReturn($room);
		$this->settingsService->expects($this->once())->method('applyTeamToRoom')->with($room);

		$this->listener->handle($this->makeTeamEditedEvent());
	}

	public function testRenameFromTalkIsNotAppliedTwice(): void {
		// TeamRoomSettingsService::renameTeamOfRoom() applies the name to the room itself
		$this->manager->method('getTeamRoom')->willReturn($this->makeRoom());
		$this->settingsService->method('isChangingTeamFromTalk')->willReturn(true);
		$this->settingsService->expects($this->never())->method('applyTeamToRoom');

		$this->listener->handle($this->makeTeamEditedEvent());
	}

	public function testTeamWithoutRoomIsIgnored(): void {
		$this->manager->method('getTeamRoom')->willReturn(null);
		$this->settingsService->expects($this->never())->method('applyTeamToRoom');

		$this->listener->handle($this->makeTeamEditedEvent());
	}

	public function testFailureToApplyTheTeamDoesNotBreakTheTeamChange(): void {
		$this->manager->method('getTeamRoom')->willReturn($this->makeRoom());
		$this->settingsService->method('applyTeamToRoom')->willThrowException(new \Exception('Circles unavailable'));
		$this->logger->expects($this->once())->method('warning');

		$this->listener->handle($this->makeTeamEditedEvent());
	}

	public static function dataRoomProperty(): array {
		return [
			'name' => [ARoomModifiedEvent::PROPERTY_NAME, true],
			'description' => [ARoomModifiedEvent::PROPERTY_DESCRIPTION, true],
			'listable' => [ARoomModifiedEvent::PROPERTY_LISTABLE, true],
			'read-only is no team setting' => [ARoomModifiedEvent::PROPERTY_READ_ONLY, false],
			'avatar is not synced yet' => [ARoomModifiedEvent::PROPERTY_AVATAR, false],
		];
	}

	#[DataProvider('dataRoomProperty')]
	public function testRoomChangesOfTeamSettingsAreAppliedToTheTeam(string $property, bool $applied): void {
		$room = $this->makeRoom();
		$this->settingsService->expects($applied ? $this->once() : $this->never())
			->method('applyRoomToTeam')
			->with($room, $property);

		$this->listener->handle(new RoomModifiedEvent($room, $property, 'new'));
	}

	public function testChangesOfOtherRoomsAreIgnored(): void {
		$this->settingsService->expects($this->never())->method('applyRoomToTeam');

		$this->listener->handle(new RoomModifiedEvent($this->makeRoom(''), ARoomModifiedEvent::PROPERTY_NAME, 'new'));
	}

	private function makeJoinEvent(Room $room, string $userId = 'alice'): BeforeUserJoinedRoomEvent {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		return new BeforeUserJoinedRoomEvent($room, $user, '', false);
	}

	private function notYetAParticipant(bool $listable = true): void {
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$this->manager->method('isRoomListableByUser')->willReturn($listable);
	}

	public function testJoiningTheOpenRoomJoinsTheTeamFirst(): void {
		$this->notYetAParticipant();
		$this->circlesAdapter->method('getDirectMember')->with('team1', 'alice')->willReturn(null);
		$this->circlesAdapter->expects($this->once())->method('joinTeam')->with('team1', 'alice')->willReturn(true);

		$event = $this->makeJoinEvent($this->makeRoom());
		$this->listener->handle($event);

		$this->assertFalse($event->getCancelJoin());
	}

	public function testMembersMissingInTheRoomJoinWithoutJoiningTheTeamAgain(): void {
		$this->notYetAParticipant();
		$this->circlesAdapter->method('getDirectMember')->willReturn(['level' => 1, 'joined' => 1]);
		$this->circlesAdapter->expects($this->never())->method('joinTeam');

		$event = $this->makeJoinEvent($this->makeRoom());
		$this->listener->handle($event);

		$this->assertFalse($event->getCancelJoin());
	}

	public static function dataNotJoined(): array {
		return [
			'only requested to join' => [false],
			'refused by the team' => [new \Exception('refused')],
		];
	}

	#[DataProvider('dataNotJoined')]
	public function testNotJoiningTheTeamCancelsJoiningTheRoom(bool|\Exception $joined): void {
		$this->notYetAParticipant();
		$this->circlesAdapter->method('getDirectMember')->willReturn(null);
		if ($joined instanceof \Exception) {
			$this->circlesAdapter->method('joinTeam')->willThrowException($joined);
		} else {
			$this->circlesAdapter->method('joinTeam')->willReturn($joined);
		}

		$event = $this->makeJoinEvent($this->makeRoom());
		$this->listener->handle($event);

		$this->assertTrue($event->getCancelJoin());
	}

	public function testParticipantsOpeningTheRoomAgainAreNotChecked(): void {
		$room = $this->makeRoom();
		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_USERS, 'alice')
			->willReturn($this->createMock(Participant::class));
		$this->circlesAdapter->expects($this->never())->method('getDirectMember');
		$this->circlesAdapter->expects($this->never())->method('joinTeam');

		$event = $this->makeJoinEvent($room);
		$this->listener->handle($event);

		$this->assertFalse($event->getCancelJoin());
	}

	public function testJoiningWithoutListingIsLeftToTheRoom(): void {
		// Not listable for the user: ParticipantService refuses the join itself
		$this->notYetAParticipant(false);
		$this->circlesAdapter->expects($this->never())->method('joinTeam');

		$event = $this->makeJoinEvent($this->makeRoom());
		$this->listener->handle($event);

		$this->assertFalse($event->getCancelJoin());
	}

	public function testJoiningOtherRoomsIsIgnored(): void {
		$this->participantService->expects($this->never())->method('getParticipantByActor');
		$this->circlesAdapter->expects($this->never())->method('joinTeam');

		$event = $this->makeJoinEvent($this->makeRoom(''));
		$this->listener->handle($event);

		$this->assertFalse($event->getCancelJoin());
	}
}
