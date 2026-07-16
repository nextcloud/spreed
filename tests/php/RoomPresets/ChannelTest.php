<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\RoomPresets;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\RoomPresets\Channel;
use OCA\Talk\RoomPresets\Parameter;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ChannelTest extends TestCase {
	protected IL10N&MockObject $l10n;
	protected Channel $preset;

	public function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->preset = new Channel($this->l10n);
	}

	public function testIdentifier(): void {
		$this->assertSame('channel', Channel::getIdentifier());
	}

	public function testName(): void {
		$this->assertSame('Channel', $this->preset->getName());
	}

	public function testParametersRestrictThePostingToModerators(): void {
		$parameters = $this->preset->getParameters();

		$this->assertSame(Room::LISTABLE_USERS, $parameters[Parameter::LISTABLE->value], 'Channels are listable for users by default');

		$permissions = $parameters[Parameter::PERMISSIONS->value];
		$this->assertSame(Attendee::PERMISSIONS_CUSTOM, $permissions & Attendee::PERMISSIONS_CUSTOM, 'Channels need custom permissions to apply the defaults');
		$this->assertSame(Attendee::PERMISSIONS_REACT, $permissions & Attendee::PERMISSIONS_REACT, 'Channels allow everyone to react');
		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CHAT, 'Channels only allow moderators to post');
		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_START, 'Channels do not allow calls');
		$this->assertSame(0, $permissions & Attendee::PERMISSIONS_CALL_JOIN, 'Channels do not allow calls');
	}
}
