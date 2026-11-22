<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Listener\TeamFederatedListener;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamFederatedListenerTest extends TestCase {
	protected TeamRoomSyncService&MockObject $syncService;
	protected TeamFederatedListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->syncService = $this->createMock(TeamRoomSyncService::class);
		$this->listener = new TeamFederatedListener($this->syncService);
	}

	private function makeRoom(string $objectType): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn($objectType);
		return $room;
	}

	private function makeAttendee(string $actorType, string $actorId): Attendee {
		return Attendee::fromRow(['actor_type' => $actorType, 'actor_id' => $actorId]);
	}

	public function testDeletedTeamConversationDeletesTheMarkers(): void {
		$room = $this->makeRoom(Room::OBJECT_TYPE_TEAM);
		$this->syncService->expects($this->once())->method('deleteFederatedMarkers')->with($room);
		$this->listener->handle(new RoomDeletedEvent($room));
	}

	public function testOtherDeletedConversationsAreIgnored(): void {
		$this->syncService->expects($this->never())->method('deleteFederatedMarkers');
		$this->listener->handle(new RoomDeletedEvent($this->makeRoom('')));
	}

	public function testFederatedParticipantLeavingIsRemembered(): void {
		$room = $this->makeRoom(Room::OBJECT_TYPE_TEAM);
		$this->syncService->expects($this->once())->method('rememberFederatedLeave')->with($room, 'bob@remote.example');
		$this->listener->handle(new AttendeeRemovedEvent($room, $this->makeAttendee(Attendee::ACTOR_FEDERATED_USERS, 'bob@remote.example'), AAttendeeRemovedEvent::REASON_LEFT, []));
	}

	public function testEmailGuestLeavingIsRemembered(): void {
		$room = $this->makeRoom(Room::OBJECT_TYPE_TEAM);
		$attendee = Attendee::fromRow(['actor_type' => Attendee::ACTOR_EMAILS, 'actor_id' => hash('sha256', 'guest@example.tld'), 'invited_cloud_id' => 'guest@example.tld']);
		$this->syncService->expects($this->once())->method('rememberEmailLeave')->with($room, 'guest@example.tld');
		$this->listener->handle(new AttendeeRemovedEvent($room, $attendee, AAttendeeRemovedEvent::REASON_LEFT, []));
	}

	public function testRemovalByModeratorsAndLocalUsersAreNotRemembered(): void {
		$room = $this->makeRoom(Room::OBJECT_TYPE_TEAM);
		$this->syncService->expects($this->never())->method('rememberFederatedLeave');
		$this->listener->handle(new AttendeeRemovedEvent($room, $this->makeAttendee(Attendee::ACTOR_FEDERATED_USERS, 'bob@remote.example'), AAttendeeRemovedEvent::REASON_REMOVED, []));
		$this->listener->handle(new AttendeeRemovedEvent($room, $this->makeAttendee(Attendee::ACTOR_USERS, 'alice'), AAttendeeRemovedEvent::REASON_LEFT, []));
	}
}
