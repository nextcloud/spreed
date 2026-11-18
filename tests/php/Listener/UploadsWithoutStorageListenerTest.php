<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Listener\UploadsWithoutStorageListener;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\UploadsWithoutStorageService;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UploadsWithoutStorageListenerTest extends TestCase {
	protected RoomService&MockObject $roomService;
	protected UploadsWithoutStorageService&MockObject $uploadsWithoutStorageService;
	protected UploadsWithoutStorageListener $listener;
	protected Folder&MockObject $guestsFolder;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->roomService = $this->createMock(RoomService::class);
		$this->uploadsWithoutStorageService = $this->createMock(UploadsWithoutStorageService::class);
		$this->listener = new UploadsWithoutStorageListener($this->roomService, $this->uploadsWithoutStorageService);

		$this->guestsFolder = $this->createMock(Folder::class);
		$this->uploadsWithoutStorageService->method('getGuestsFolder')->willReturnCallback(function (Room $room): Folder {
			if ($room->getGuestsFolderId() !== 42) {
				throw new NotFoundException();
			}
			return $this->guestsFolder;
		});
		$this->uploadsWithoutStorageService->method('getOwnerId')->willReturn('alice');
	}

	private function createRoom(int $guestsFolderId): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getGuestsFolderId')->willReturn($guestsFolderId);
		return $room;
	}

	private function createAttendee(string $actorType, string $actorId): Attendee {
		$attendee = new Attendee();
		$attendee->setActorType($actorType);
		$attendee->setActorId($actorId);
		return $attendee;
	}

	public function testRemovingTheModeratorTurnsUploadsOff(): void {
		$room = $this->createRoom(42);
		$this->roomService->expects($this->once())->method('setGuestsFolderId')->with($room, 0);

		$this->listener->handle(new AttendeesRemovedEvent($room, [
			$this->createAttendee(Attendee::ACTOR_GUESTS, 'alice'),
			$this->createAttendee(Attendee::ACTOR_USERS, 'alice'),
		]));
	}

	private function createParticipantModifiedEvent(Room $room, string $actorId, bool $isModerator): ParticipantModifiedEvent {
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($this->createAttendee(Attendee::ACTOR_USERS, $actorId));
		$participant->method('hasModeratorPermissions')->with(false)->willReturn($isModerator);
		return new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_TYPE, Participant::USER);
	}

	public function testDemotingTheModeratorTurnsUploadsOff(): void {
		$room = $this->createRoom(42);
		$this->roomService->expects($this->once())->method('setGuestsFolderId')->with($room, 0);

		$this->listener->handle($this->createParticipantModifiedEvent($room, 'alice', false));
	}

	public function testDemotingSomeoneElseKeepsUploads(): void {
		$this->roomService->expects($this->never())->method('setGuestsFolderId');

		$this->listener->handle($this->createParticipantModifiedEvent($this->createRoom(42), 'bob', false));
	}
}
