<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Listener\CircleMembershipListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BanService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CircleMembershipListenerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected CircleMembershipListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		// No further group or team memberships that keep the user in the room
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn([]);

		$this->listener = new CircleMembershipListener(
			$this->manager,
			$appManager,
			$groupManager,
			$this->participantService,
			$this->createMock(BanService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ISession::class),
		);
	}

	public static function dataRemovedFromTheTeam(): array {
		return [
			'team conversation, moderator' => [Room::OBJECT_TYPE_TEAM, Participant::MODERATOR, true],
			'team conversation, owner' => [Room::OBJECT_TYPE_TEAM, Participant::OWNER, true],
			'team conversation, user' => [Room::OBJECT_TYPE_TEAM, Participant::USER, true],
			// Other conversations with the team as participant: moderators stay
			'other conversation, moderator' => ['', Participant::MODERATOR, false],
			'other conversation, user' => ['', Participant::USER, true],
		];
	}

	#[DataProvider('dataRemovedFromTheTeam')]
	public function testMembersLeavingTheTeamAreRemoved(string $objectType, int $participantType, bool $removed): void {
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(42);
		$room->method('getObjectType')->willReturn($objectType);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'alice',
			'participant_type' => $participantType,
		]));
		$this->participantService->method('getParticipant')->with($room, 'alice')->willReturn($participant);

		$this->participantService->expects($removed ? $this->once() : $this->never())
			->method('removeUser')
			->with($room, $user, AAttendeeRemovedEvent::REASON_REMOVED);

		self::invokePrivate($this->listener, 'removeFromRoomsUnlessStillLinked', [[$room], $user]);
	}

	public static function dataLevelChangedInTheTeam(): array {
		return [
			'user to moderator' => [Participant::MODERATOR, Participant::USER, true],
			'self-joined to moderator' => [Participant::MODERATOR, Participant::USER_SELF_JOINED, true],
			'user to owner' => [Participant::OWNER, Participant::USER, true],
			'moderator to owner' => [Participant::OWNER, Participant::MODERATOR, true],
			'owner to moderator' => [Participant::MODERATOR, Participant::OWNER, true],
			'owner to user' => [Participant::USER, Participant::OWNER, true],
			'moderator to user' => [Participant::USER, Participant::MODERATOR, true],
			'self-joined stays self-joined' => [Participant::USER, Participant::USER_SELF_JOINED, false],
		];
	}

	#[DataProvider('dataLevelChangedInTheTeam')]
	public function testLevelChangesInTheTeamAreMirrored(int $newType, int $participantType, bool $updated): void {
		$teamRoom = $this->createMock(Room::class);
		$teamRoom->method('getObjectType')->willReturn(Room::OBJECT_TYPE_TEAM);
		// Only the team conversation follows the levels of the team
		$otherRoom = $this->createMock(Room::class);
		$otherRoom->method('getObjectType')->willReturn('');
		$this->manager->method('getRoomsForActor')
			->with(Attendee::ACTOR_CIRCLES, 'team')
			->willReturn([$teamRoom, $otherRoom]);

		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'alice',
			'participant_type' => $participantType,
		]));
		$this->participantService->method('getParticipant')->with($teamRoom, 'alice')->willReturn($participant);

		$this->participantService->expects($updated ? $this->once() : $this->never())
			->method('updateParticipantType')
			->with($teamRoom, $participant, $newType);

		self::invokePrivate($this->listener, 'setUserTypeInTeamConversations', ['team', 'alice', $newType]);
	}
}
