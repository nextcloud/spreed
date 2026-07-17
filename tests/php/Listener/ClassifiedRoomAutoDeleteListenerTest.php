<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\ACallEndedEvent;
use OCA\Talk\Listener\ClassifiedRoomAutoDeleteListener;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ClassifiedRoomAutoDeleteListenerTest extends TestCase {
	protected RoomService&MockObject $roomService;
	protected ITimeFactory&MockObject $timeFactory;
	protected ClassifiedRoomAutoDeleteListener $listener;

	public function setUp(): void {
		parent::setUp();

		$this->roomService = $this->createMock(RoomService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->listener = new ClassifiedRoomAutoDeleteListener($this->roomService, $this->timeFactory);
	}

	protected function createRoom(bool $classified, string $objectType = '', bool $preserved = false): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('isClassified')->willReturn($classified);
		$room->method('getObjectType')->willReturn($objectType);
		$room->method('isPreserved')->willReturn($preserved);
		return $room;
	}

	protected function callEndedEvent(Room $room): ACallEndedEvent {
		$event = $this->createMock(ACallEndedEvent::class);
		$event->method('getRoom')->willReturn($room);
		return $event;
	}

	public function testQueuesClassifiedRoomForDeletion(): void {
		$room = $this->createRoom(classified: true);
		$this->timeFactory->method('getTime')->willReturn(1234567890);

		$this->roomService->expects($this->once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_CLASSIFIED, '1234567890');

		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testIgnoresNonClassifiedRoom(): void {
		$room = $this->createRoom(classified: false);
		$this->roomService->expects($this->never())->method('setObject');
		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testIgnoresRoomThatAlreadyHasAnObjectType(): void {
		$room = $this->createRoom(classified: true, objectType: Room::OBJECT_TYPE_CLASSIFIED_PERSIST);
		$this->roomService->expects($this->never())->method('setObject');
		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testQueuesPreservedClassifiedRoomForDeletion(): void {
		// Preserving only blocks the manual deletion via the API, it must not
		// keep a classified conversation from being deleted automatically
		$room = $this->createRoom(classified: true, preserved: true);
		$this->timeFactory->method('getTime')->willReturn(1234567890);

		$this->roomService->expects($this->once())
			->method('setObject')
			->with($room, Room::OBJECT_TYPE_CLASSIFIED, '1234567890');

		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testIgnoresPreservedRoomThatIsNotClassified(): void {
		$room = $this->createRoom(classified: false, preserved: true);
		$this->roomService->expects($this->never())->method('setObject');
		$this->listener->handle($this->callEndedEvent($room));
	}

	public function testIgnoresUnrelatedEvent(): void {
		$this->roomService->expects($this->never())->method('setObject');
		$this->listener->handle($this->createMock(Event::class));
	}
}
