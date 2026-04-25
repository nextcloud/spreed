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
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class RoomTest extends TestCase {
	private function createRoom(): Room {
		return new Room(
			$this->createMock(ITimeFactory::class),
			1,
			Room::TYPE_GROUP,
			Room::READ_WRITE,
			Room::LISTABLE_NONE,
			0,
			Webinary::LOBBY_NONE,
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
			null,
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

	public function testSetActiveSinceSetsValue(): void {
		$room = $this->createRoom();
		$since = new \DateTime();
		$room->setActiveSince($since);
		$this->assertSame($since, $room->getActiveSince());
	}

	public function testSetActiveSinceAcceptsNull(): void {
		$room = $this->createRoom();
		$room->setActiveSince(new \DateTime());
		$room->setActiveSince(null);
		$this->assertNull($room->getActiveSince());
	}

	public function testSetCallFlagSetsValue(): void {
		$room = $this->createRoom();
		$room->setCallFlag(Participant::FLAG_WITH_VIDEO);
		$this->assertSame(Participant::FLAG_WITH_VIDEO, $room->getCallFlag());
	}

	public function testSetCallFlagReplacesValue(): void {
		$room = $this->createRoom();
		$room->setCallFlag(Participant::FLAG_IN_CALL);
		$room->setCallFlag(Participant::FLAG_WITH_VIDEO);
		$this->assertSame(Participant::FLAG_WITH_VIDEO, $room->getCallFlag());
	}
}
