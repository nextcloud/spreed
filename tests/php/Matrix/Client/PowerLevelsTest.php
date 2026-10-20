<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\PowerLevels;
use OCA\Talk\Matrix\Client\Model\RoomState;
use Test\TestCase;

class PowerLevelsTest extends TestCase {
	public function testWithoutPowerLevels(): void {
		$powerLevels = new PowerLevels([], '@creator:example.org');

		self::assertSame(PowerLevels::LEVEL_ADMIN, $powerLevels->getUserLevel('@creator:example.org'));
		self::assertSame(PowerLevels::LEVEL_USER, $powerLevels->getUserLevel('@alice:example.org'));
		self::assertTrue($powerLevels->canSendEvent('@alice:example.org', 'm.room.message'));
		self::assertTrue($powerLevels->canSendEvent('@alice:example.org', 'm.room.name', true));
		self::assertFalse($powerLevels->canRedact('@alice:example.org'));
		self::assertTrue($powerLevels->canRedact('@creator:example.org'));
	}

	public function testWithPowerLevels(): void {
		$powerLevels = new PowerLevels([
			'users' => ['@mod:example.org' => 50, '@muted:example.org' => -1],
			'users_default' => 0,
			'events_default' => 0,
			'events' => ['m.reaction' => 10],
			'state_default' => 50,
			'redact' => 50,
		], '@creator:example.org');

		self::assertSame(PowerLevels::LEVEL_USER, $powerLevels->getUserLevel('@creator:example.org'), 'Only the content counts once power levels exist');
		self::assertTrue($powerLevels->canSendEvent('@alice:example.org', 'm.room.message'));
		self::assertFalse($powerLevels->canSendEvent('@muted:example.org', 'm.room.message'));
		self::assertFalse($powerLevels->canSendEvent('@alice:example.org', 'm.reaction'));
		self::assertTrue($powerLevels->canSendEvent('@mod:example.org', 'm.reaction'));
		self::assertFalse($powerLevels->canSendEvent('@alice:example.org', 'm.room.name', true));
		self::assertTrue($powerLevels->canRedact('@mod:example.org'));
		self::assertFalse($powerLevels->canRedact('@alice:example.org'));
	}

	public function testCreatorsSinceRoomVersion12(): void {
		$state = new RoomState('!room:example.org');
		$state->applyAll([
			new Event('', 'm.room.create', '@creator:example.org', ['room_version' => '12', 'additional_creators' => ['@co:example.org']], ''),
			new Event('', 'm.room.power_levels', '@creator:example.org', ['users' => ['@admin:example.org' => 100]], ''),
		]);
		$powerLevels = $state->getPowerLevels();

		self::assertSame(['@creator:example.org', '@co:example.org'], $state->creators);
		self::assertSame(PowerLevels::LEVEL_CREATOR, $powerLevels->getUserLevel('@creator:example.org'));
		self::assertSame(PowerLevels::LEVEL_CREATOR, $powerLevels->getUserLevel('@co:example.org'));
		self::assertFalse($powerLevels->canKick('@admin:example.org', '@creator:example.org'));
		self::assertTrue($powerLevels->canChangeUserLevel('@creator:example.org', '@admin:example.org', 50));
		self::assertSame(['users' => ['@admin:example.org' => 100, '@alice:example.org' => 50]], $powerLevels->withUserLevel('@alice:example.org', 50));
		self::assertSame(['users' => ['@alice:example.org' => 50]], (new PowerLevels([], '@creator:example.org', ['@creator:example.org']))->withUserLevel('@alice:example.org', 50), 'Creators are not added to the content');

		$state->apply(new Event('', 'm.room.create', '@creator:example.org', ['room_version' => '11'], ''));
		self::assertSame([], $state->creators, 'Creators only have the creator level since room version 12');
	}

	public function testModeration(): void {
		$powerLevels = new PowerLevels([
			'users' => ['@admin:example.org' => 100, '@mod:example.org' => 50],
			'invite' => 50,
		]);

		self::assertTrue($powerLevels->canInvite('@mod:example.org'));
		self::assertFalse($powerLevels->canInvite('@alice:example.org'));
		self::assertTrue($powerLevels->canKick('@mod:example.org', '@alice:example.org'));
		self::assertFalse($powerLevels->canKick('@mod:example.org', '@admin:example.org'));
		self::assertTrue($powerLevels->canChangeUserLevel('@admin:example.org', '@alice:example.org', 100));
		self::assertFalse($powerLevels->canChangeUserLevel('@mod:example.org', '@alice:example.org', 100), 'Not above the own level');
		self::assertTrue($powerLevels->canChangeUserLevel('@mod:example.org', '@alice:example.org', 50));
		self::assertFalse($powerLevels->canChangeUserLevel('@mod:example.org', '@admin:example.org', 0), 'Not for higher levels');
	}

	public function testWithUserLevel(): void {
		self::assertSame(
			['users' => ['@creator:example.org' => 100, '@alice:example.org' => 50]],
			(new PowerLevels([], '@creator:example.org'))->withUserLevel('@alice:example.org', 50),
			'The creator keeps their implicit level',
		);
		self::assertSame(
			['users' => ['@admin:example.org' => 100, '@alice:example.org' => 0], 'ban' => 50],
			(new PowerLevels(['users' => ['@admin:example.org' => 100, '@alice:example.org' => 50], 'ban' => 50]))->withUserLevel('@alice:example.org', 0),
		);
	}
}
