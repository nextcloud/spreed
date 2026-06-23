<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Webinary;
use Test\TestCase;

class RoomTest extends TestCase {
	private function createRoom(?\DateTime $lobbyTimer = null, int $lobbyState = Webinary::LOBBY_NONE): Room {
		return new Room(
			1,
			Room::TYPE_GROUP,
			Room::READ_WRITE,
			Room::LISTABLE_NONE,
			0,
			$lobbyState,
			Webinary::SIP_DISABLED,
			null,
			'token',
			'name',
			'',
			'',
			'',
			'',
			'',
			Attendee::PERMISSIONS_DEFAULT,
			Participant::FLAG_DISCONNECTED,
			null,
			null,
			0,
			null,
			$lobbyTimer,
			'',
			'',
			BreakoutRoom::MODE_NOT_CONFIGURED,
			BreakoutRoom::STATUS_STOPPED,
			Room::RECORDING_NONE,
			RecordingService::CONSENT_REQUIRED_NO,
			Room::HAS_FEDERATION_NONE,
			Room::MENTION_PERMISSIONS_EVERYONE,
			'',
			0,
			0,
		);
	}

	public function testGetLobbyStateIsPure(): void {
		$room = $this->createRoom(null, Webinary::LOBBY_NON_MODERATORS);
		$this->assertSame(Webinary::LOBBY_NON_MODERATORS, $room->getLobbyState());
		$this->assertSame(Webinary::LOBBY_NON_MODERATORS, $room->getLobbyState());
	}

	public function testGetLobbyTimerIsPure(): void {
		$timer = new \DateTime('+1 hour');
		$room = $this->createRoom($timer);
		$this->assertEquals($timer, $room->getLobbyTimer());
		$this->assertEquals($timer, $room->getLobbyTimer());
	}
}
