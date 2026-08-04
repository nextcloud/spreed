<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\RoomPresets\Announcement;
use OCA\Talk\RoomPresets\Channel;
use OCA\Talk\RoomPresets\Parameter;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AnnouncementTest extends TestCase {
	protected IL10N&MockObject $l10n;
	protected Announcement $preset;

	public function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->preset = new Announcement($this->l10n);
	}

	public function testIdentifier(): void {
		$this->assertSame('announcement', Announcement::getIdentifier());
	}

	public function testName(): void {
		$this->assertSame('Announcement', $this->preset->getName());
	}

	public function testIsAChannel(): void {
		$this->assertInstanceOf(Channel::class, $this->preset, 'Announcements inherit all constraints of a channel');
	}

	public function testParametersAreNotListable(): void {
		$parameters = $this->preset->getParameters();

		$this->assertSame(Room::LISTABLE_NONE, $parameters[Parameter::LISTABLE->value], 'Announcements are not openly joinable, contrary to channels');
	}

	public function testParametersInheritTheChannelPermissions(): void {
		$channelPermissions = (new Channel($this->l10n))->getParameters()[Parameter::PERMISSIONS->value];
		$parameters = $this->preset->getParameters();

		$this->assertSame($channelPermissions, $parameters[Parameter::PERMISSIONS->value], 'Announcements restrict posting the same way channels do');
		$this->assertSame(0, $parameters[Parameter::PERMISSIONS->value] & Attendee::PERMISSIONS_CHAT, 'Announcements only allow moderators to post');
	}
}
