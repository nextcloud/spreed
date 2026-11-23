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
	protected ParticipantService&MockObject $participantService;
	protected CircleMembershipListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->participantService = $this->createMock(ParticipantService::class);
		// No further group or team memberships that keep the user in the room
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('getUserGroupIds')->willReturn([]);

		$this->listener = new CircleMembershipListener(
			$this->createMock(Manager::class),
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
}
