<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Signaling;

use OCA\Talk\Events\BeforeSignalingRoomPropertiesSentEvent;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Signaling\RoomPropertiesHelper;
use OCA\Talk\Webinary;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RoomPropertiesHelperTest extends TestCase {
	private IEventDispatcher&MockObject $dispatcher;
	private RoomService&MockObject $roomService;
	private RoomPropertiesHelper $helper;

	public function setUp(): void {
		parent::setUp();
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->roomService = $this->createMock(RoomService::class);
		$this->helper = new RoomPropertiesHelper($this->dispatcher, $this->roomService);
	}

	private function createRoomMock(string $displayName = 'Room name'): Room&MockObject {
		$room = $this->createMock(Room::class);
		$room->method('getDisplayName')->willReturnCallback(fn (string $userId) => $displayName);
		$room->method('getType')->willReturn(Room::TYPE_GROUP);
		$room->method('getLobbyState')->willReturn(Webinary::LOBBY_NONE);
		$room->method('getLobbyTimer')->willReturn(null);
		$room->method('getReadOnly')->willReturn(Room::READ_WRITE);
		$room->method('getListable')->willReturn(Room::LISTABLE_NONE);
		$room->method('getActiveSince')->willReturn(null);
		$room->method('getSIPEnabled')->willReturn(Webinary::SIP_DISABLED);
		$room->method('getDescription')->willReturn('A description');
		return $room;
	}

	public function testGetPropertiesForSignalingRoomModifiedIncludesDescription(): void {
		$room = $this->createRoomMock();

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(BeforeSignalingRoomPropertiesSentEvent::class));

		$properties = $this->helper->getPropertiesForSignaling($room, 'alice');

		$this->assertSame('Room name', $properties['name']);
		$this->assertSame(Room::TYPE_GROUP, $properties['type']);
		$this->assertSame('A description', $properties['description']);
		$this->assertArrayNotHasKey('participant-list', $properties);
	}

	public function testGetPropertiesForSignalingNotRoomModifiedIncludesParticipantList(): void {
		$room = $this->createRoomMock();

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(BeforeSignalingRoomPropertiesSentEvent::class));

		$properties = $this->helper->getPropertiesForSignaling($room, '', false);

		$this->assertSame('refresh', $properties['participant-list']);
		$this->assertArrayNotHasKey('description', $properties);
	}

	public function testGetPropertiesForSignalingEventCanModifyProperties(): void {
		$room = $this->createRoomMock();

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(BeforeSignalingRoomPropertiesSentEvent::class))
			->willReturnCallback(function (BeforeSignalingRoomPropertiesSentEvent $event): void {
				$event->setProperty('custom-key', 'custom-value');
				$event->unsetProperty('name');
			});

		$properties = $this->helper->getPropertiesForSignaling($room, 'alice');

		$this->assertSame('custom-value', $properties['custom-key']);
		$this->assertArrayNotHasKey('name', $properties);
	}

	public function testGetPropertiesForSignalingPassesUserIdToEvent(): void {
		$room = $this->createRoomMock();

		$capturedEvent = null;
		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (BeforeSignalingRoomPropertiesSentEvent $event) use (&$capturedEvent): void {
				$capturedEvent = $event;
			});

		$this->helper->getPropertiesForSignaling($room, 'bob');

		$this->assertInstanceOf(BeforeSignalingRoomPropertiesSentEvent::class, $capturedEvent);
		$this->assertSame('bob', $capturedEvent->getUserId());
	}

	public function testGetPropertiesForSignalingCallsValidateLobbyTimer(): void {
		$room = $this->createRoomMock();

		$this->roomService->expects($this->once())
			->method('validateLobbyTimer')
			->with($room);

		$this->helper->getPropertiesForSignaling($room, 'alice');
	}
}
