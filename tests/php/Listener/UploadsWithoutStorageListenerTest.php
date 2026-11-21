<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Listener;

use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
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
		$this->uploadsWithoutStorageService->method('getGuestsFolderById')->willReturnCallback(function (int $folderId): Folder {
			if ($folderId !== 42) {
				throw new NotFoundException();
			}
			return $this->guestsFolder;
		});
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

	public function testChangingTheGuestsFolderRevokesLinksOfThePreviousOne(): void {
		$room = $this->createRoom(0);
		$this->uploadsWithoutStorageService->expects($this->once())
			->method('revokeUploadLinks')
			->with($this->guestsFolder, $room);

		$this->listener->handle(new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_GUESTS_FOLDER, 0, 42));
	}

	public function testPublicConversationBecomingGroupRevokesLinks(): void {
		$room = $this->createRoom(42);
		$this->uploadsWithoutStorageService->expects($this->once())
			->method('revokeUploadLinks')
			->with($this->guestsFolder, $room);

		$this->listener->handle(new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_TYPE, Room::TYPE_GROUP, Room::TYPE_PUBLIC));
	}

	public function testDeletingTheRoomRevokesLinks(): void {
		$room = $this->createRoom(42);
		$this->uploadsWithoutStorageService->expects($this->once())
			->method('revokeUploadLinks')
			->with($this->guestsFolder, $room);

		$this->listener->handle(new RoomDeletedEvent($room));
	}

	public function testRemovingTheModeratorTurnsUploadsOff(): void {
		$room = $this->createRoom(42);
		$this->roomService->expects($this->once())->method('setGuestsFolderId')->with($room, 0);
		$this->uploadsWithoutStorageService->expects($this->never())->method('revokeUploadLinks');

		$this->listener->handle(new AttendeesRemovedEvent($room, [
			$this->createAttendee(Attendee::ACTOR_GUESTS, 'alice'),
			$this->createAttendee(Attendee::ACTOR_USERS, 'alice'),
		]));
	}

	public function testRemovingAGuestRevokesTheirLinks(): void {
		$room = $this->createRoom(42);
		$guest = $this->createAttendee(Attendee::ACTOR_GUESTS, 'guest');
		$this->roomService->expects($this->never())->method('setGuestsFolderId');
		$this->uploadsWithoutStorageService->expects($this->once())
			->method('revokeUploadLinks')
			->with($this->guestsFolder, $room, $guest);

		$this->listener->handle(new AttendeesRemovedEvent($room, [$guest]));
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
