<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Status;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Status\Listener;
use OCP\UserStatus\IManager;
use OCP\UserStatus\IUserStatus;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ListenerTest extends TestCase {
	protected IManager&MockObject $statusManager;
	protected Listener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->statusManager = $this->createMock(IManager::class);
		$this->listener = new Listener($this->statusManager);
	}

	protected function joinCallEvent(Room $room): BeforeParticipantModifiedEvent&MockObject {
		$attendee = new Attendee();
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId('user1');

		$event = $this->createMock(BeforeParticipantModifiedEvent::class);
		$event->method('getRoom')->willReturn($room);
		$event->method('getParticipant')->willReturn(new Participant($room, $attendee, null));
		$event->method('getProperty')->willReturn(AParticipantModifiedEvent::PROPERTY_IN_CALL);
		$event->method('getOldValue')->willReturn(Participant::FLAG_DISCONNECTED);
		$event->method('getNewValue')->willReturn(Participant::FLAG_IN_CALL);
		$event->method('getDetail')->willReturn(false);
		return $event;
	}

	public function testDoesNotSetInACallStatusForClassifiedRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(true);

		// The status is visible to everyone on the instance, so it must not even
		// be looked up, let alone set
		$this->statusManager->expects(self::never())->method('getUserStatuses');
		$this->statusManager->expects(self::never())->method('setUserStatus');

		$this->listener->handle($this->joinCallEvent($room));
	}

	public function testSetsInACallStatusForRegularRoom(): void {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn(false);

		$this->statusManager->method('getUserStatuses')->willReturn([]);
		$this->statusManager->expects(self::once())
			->method('setUserStatus')
			->with('user1', 'call', IUserStatus::BUSY, true);

		$this->listener->handle($this->joinCallEvent($room));
	}
}
