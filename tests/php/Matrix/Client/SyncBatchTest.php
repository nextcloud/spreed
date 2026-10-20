<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Model\Event;
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
		self::assertSame(['m.room.message'], array_map(static fn ($event) => $event->type, $joined->timeline));
		self::assertSame('Hello', $joined->timeline[0]->getBody());

		$state = new RoomState('!room:example.org');
		$state->applyAll($joined->stateEvents);
		self::assertSame('Team', $state->name);
		self::assertSame(Member::JOIN, $state->getMembers()['@bob:example.org']->membership);
		self::assertSame('Bob', $state->getMembers()['@bob:example.org']->getName());
	}

	public function testTimelineGapAndReadReceipts(): void {
		$batch = SyncBatch::fromArray([
			'next_batch' => 'next',
			'rooms' => ['join' => ['!room:example.org' => [
				'timeline' => ['events' => [], 'limited' => true, 'prev_batch' => 't42'],
				'ephemeral' => ['events' => [
					['type' => 'm.typing', 'content' => ['user_ids' => ['@bob:example.org']]],
					['type' => 'm.receipt', 'content' => [
						'$1' => ['m.read' => ['@bob:example.org' => ['ts' => 1], '@carol:example.org' => ['ts' => 2, 'thread_id' => 'main']]],
						'$2' => ['m.read' => ['@bob:example.org' => ['ts' => 3, 'thread_id' => '$root']], 'm.read.private' => ['@alice:example.org' => ['ts' => 4]]],
					]],
				]],
			]]],
		]);

		$joined = $batch->joined['!room:example.org'];
		self::assertTrue($joined->limited);
		self::assertSame('t42', $joined->prevBatch);
		self::assertSame(['$1' => ['@bob:example.org', '@carol:example.org']], $joined->readReceipts);

		$joined = SyncBatch::fromArray(['next_batch' => 'next', 'rooms' => ['join' => ['!room:example.org' => []]]])->joined['!room:example.org'];
		self::assertFalse($joined->limited);
		self::assertNull($joined->prevBatch);
		self::assertSame([], $joined->readReceipts);
	}

	public function testAccountData(): void {
		$batch = SyncBatch::fromArray([
			'next_batch' => 'next',
			'account_data' => ['events' => [
				['type' => 'm.push_rules', 'content' => ['global' => ['room' => []]]],
				['type' => 'invalid'],
			]],
		]);

		self::assertSame(['m.push_rules' => ['global' => ['room' => []]]], $batch->accountData);
		self::assertFalse($batch->isEmpty());
	}

	public function testEncryption(): void {
		$batch = SyncBatch::fromArray([
			'next_batch' => 'next',
			'to_device' => ['events' => [['type' => 'm.room.encrypted', 'sender' => '@bob:example.org', 'content' => ['algorithm' => 'm.olm.v1.curve25519-aes-sha2']], 'invalid']],
			'device_lists' => ['changed' => ['@bob:example.org', 42], 'left' => ['@carol:example.org']],
			'device_one_time_keys_count' => ['signed_curve25519' => 12],
			'device_unused_fallback_key_types' => ['signed_curve25519'],
		]);

		self::assertCount(1, $batch->toDevice);
		self::assertSame('@bob:example.org', $batch->toDevice[0]->sender);
		self::assertSame(['@bob:example.org'], $batch->changedDevices);
		self::assertSame(12, $batch->oneTimeKeyCount);
		self::assertSame(['signed_curve25519'], $batch->unusedFallbackKeyTypes);
		self::assertFalse($batch->isEmpty());

		$batch = SyncBatch::fromArray(['next_batch' => 'next']);
		self::assertNull($batch->oneTimeKeyCount);
		self::assertNull($batch->unusedFallbackKeyTypes);
	}

	public function testEmpty(): void {
		self::assertTrue(SyncBatch::fromArray(['next_batch' => 'next'])->isEmpty());
	}

	public function testRedacts(): void {
		self::assertSame('$old', Event::fromArray(['type' => 'm.room.redaction', 'redacts' => '$old'])->redacts);
		self::assertSame('$new', Event::fromArray(['type' => 'm.room.redaction', 'content' => ['redacts' => '$new']])->redacts);
		self::assertNull(Event::fromArray(['type' => 'm.room.message'])->redacts);
	}

	public function testRelations(): void {
		$event = Event::fromArray(['type' => 'm.room.message', 'content' => ['m.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$original', 'm.in_reply_to' => ['event_id' => '$parent']]]]);
		self::assertSame('m.replace', $event->getRelationType());
		self::assertSame('$original', $event->getRelatedEventId());
		self::assertSame('$parent', $event->getInReplyTo());
	}
}
