<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Model\Circle;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Listener\CircleDeletedListener;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CircleDeletedListenerTest extends TestCase {
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected CircleDeletedListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->listener = new CircleDeletedListener($this->manager, $this->participantService);
	}

	private function makeEvent(): CircleDestroyedEvent&MockObject {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team1');
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn($circle);
		return $event;
	}

	public function testTeamAsParticipantIsStillRemovedFromOtherRooms(): void {
		$room = $this->createMock(Room::class);
		$this->manager->method('getRoomsForActor')->with(Attendee::ACTOR_CIRCLES, 'team1')->willReturn([$room]);
		$participant = $this->createMock(Participant::class);
		$this->participantService->method('getParticipantByActor')->with($room, Attendee::ACTOR_CIRCLES, 'team1')->willReturn($participant);

		$this->participantService->expects($this->once())->method('removeAttendee')->with($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);

		$this->listener->handle($this->makeEvent());
	}
}
