<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleGenericEvent;
use OCA\Circles\Events\EditingCircleMemberEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\FederatedEvent;
use OCA\Circles\Model\Member;
use OCA\Circles\Tools\Model\SimpleDataStore;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Listener\TeamRoomListener;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCA\Talk\Service\TeamRoomSyncService;
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

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->syncService = $this->createMock(TeamRoomSyncService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->listener = new TeamRoomListener($this->manager, $this->syncService, $this->logger);

		$this->room = $this->createMock(Room::class);
		$this->room->method('getToken')->willReturn('teamroom');
		$this->member = $this->createMock(Member::class);
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
		$this->manager->method('getRoomByObject')->with(Room::OBJECT_TYPE_TEAM, 'team1')->willReturn($this->room);
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
		$this->manager->method('getRoomByObject')->willThrowException(new RoomNotFoundException());
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

	public function testAddedMemberIsOnlyResynced(): void {
		$this->withRoom();
		$event = $this->createMock(AddingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());

		$this->syncService->expects($this->never())->method('removeFormerTeamMember');
		$this->syncService->expects($this->once())->method('queueResync')->with($this->room);

		$this->listener->handle($event);
	}

	public static function dataEditedMember(): array {
		return [
			'level changed' => [CircleGenericEvent::LEVEL, 1],
			'display name changed' => [CircleGenericEvent::NAME, 0],
		];
	}

	#[DataProvider('dataEditedMember')]
	public function testOnlyLevelChangesAreResynced(int $type, int $resyncs): void {
		$this->withRoom();
		$event = $this->createMock(EditingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn($this->makeCircle());
		$event->method('getType')->willReturn($type);

		$this->syncService->expects($this->exactly($resyncs))->method('queueResync')->with($this->room);

		$this->listener->handle($event);
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
		$this->syncService->expects($this->exactly($resyncs))->method('queueResync')->with($room);

		$this->listener->handle(new RoomCreatedEvent($room));
	}
}
