<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Model\Member;
use OCA\Talk\Matrix\Client\Model\RoomState;
use OCA\Talk\Matrix\Client\Model\SyncBatch;
use Test\TestCase;

class SyncBatchTest extends TestCase {
	public function testFromArray(): void {
		$batch = SyncBatch::fromArray([
			'next_batch' => 's72595_4483_1934',
			'rooms' => [
				'join' => [
					'!room:example.org' => [
						'summary' => ['m.heroes' => ['@bob:example.org', 42], 'm.joined_member_count' => 2, 'm.invited_member_count' => 0],
						'state' => ['events' => [
							['type' => 'm.room.name', 'state_key' => '', 'sender' => '@alice:example.org', 'content' => ['name' => ' Team ']],
							'invalid',
						]],
						'timeline' => ['events' => [
							['type' => 'm.room.message', 'sender' => '@bob:example.org', 'content' => ['body' => 'Hello']],
							['type' => 'm.room.member', 'state_key' => '@bob:example.org', 'sender' => '@bob:example.org', 'content' => ['membership' => 'join', 'displayname' => 'Bob']],
						]],
					],
				],
				'leave' => ['!old:example.org' => []],
				'invite' => ['!invite:example.org' => []],
			],
		]);

		self::assertSame('s72595_4483_1934', $batch->nextBatch);
		self::assertSame(['!old:example.org'], $batch->left);
		self::assertFalse($batch->isEmpty());

		$joined = $batch->joined['!room:example.org'];
		self::assertSame(['@bob:example.org'], $joined->heroes);
		self::assertSame(2, $joined->joinedMemberCount);
		self::assertSame(['m.room.name', 'm.room.member'], array_map(static fn ($event) => $event->type, $joined->stateEvents));

		$state = new RoomState('!room:example.org');
		$state->applyAll($joined->stateEvents);
		self::assertSame('Team', $state->name);
		self::assertSame(Member::JOIN, $state->getMembers()['@bob:example.org']->membership);
		self::assertSame('Bob', $state->getMembers()['@bob:example.org']->getName());
	}

	public function testEmpty(): void {
		self::assertTrue(SyncBatch::fromArray(['next_batch' => 'next'])->isEmpty());
	}
}
