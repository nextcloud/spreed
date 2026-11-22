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
	private function createRoom(?\DateTime $lobbyTimer = null, int $lobbyState = Webinary::LOBBY_NONE, string $objectType = '', string $remoteServer = '', int $attributes = 0): Room {
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
			$remoteServer,
			'',
			Attendee::PERMISSIONS_DEFAULT,
			Participant::FLAG_DISCONNECTED,
			null,
			null,
			0,
			null,
			$lobbyTimer,
			$objectType,
			$objectType === '' ? '' : 'object1',
			BreakoutRoom::MODE_NOT_CONFIGURED,
			BreakoutRoom::STATUS_STOPPED,
			Room::RECORDING_NONE,
			RecordingService::CONSENT_REQUIRED_NO,
			Room::HAS_FEDERATION_NONE,
			Room::MENTION_PERMISSIONS_EVERYONE,
			'',
			0,
			$attributes,
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

	public static function dataIsTeamManaged(): array {
		return [
			'conversation of a team' => [Room::OBJECT_TYPE_TEAM, '', true],
			// Synced by the server of the team, the copy only has the attribute
			'federated copy of a team conversation' => [Room::OBJECT_TYPE_TEAM, 'https://remote.example', false],
			'other conversation' => ['', '', false],
			'breakout room' => [BreakoutRoom::PARENT_OBJECT_TYPE, '', false],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('dataIsTeamManaged')]
	public function testIsTeamManaged(string $objectType, string $remoteServer, bool $expected): void {
		$this->assertSame($expected, $this->createRoom(objectType: $objectType, remoteServer: $remoteServer)->isTeamManaged());
	}
}
