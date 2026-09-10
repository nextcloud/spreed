<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Tests\Unit;

use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\RoomState;
use Nextcloud\Matrix\Model\SyncBatch;
use Nextcloud\Matrix\Room\NameCalculator;
use PHPUnit\Framework\TestCase;

final class SyncBatchTest extends TestCase {
	private SyncBatch $batch;

	protected function setUp(): void {
		$this->batch = SyncBatch::fromArray(json_decode((string)file_get_contents(__DIR__ . '/../fixtures/sync.json'), true));
	}

	public function testJoinedRoom(): void {
		$room = $this->batch->joined['!room:hs'];
		self::assertTrue($room->limited);
		self::assertSame('p1', $room->prevBatch);
		self::assertCount(4, $room->state);
		self::assertCount(5, $room->timeline);
		self::assertCount(5, $room->getStateEvents()); // 4 + topic in timeline
		self::assertSame(['@bob:hs'], $room->getHeroes());
		self::assertSame(2, $room->getJoinedMemberCount());
		self::assertSame(1, $room->notificationCount);
		self::assertSame('m.receipt', $room->ephemeral[0]->type);
	}

	public function testEventHelpers(): void {
		[$msg1, $msg2, , $reaction, $img] = $this->batch->joined['!room:hs']->timeline;
		self::assertSame('Hello <strong>world</strong>', $msg1->getFormattedBody());
		self::assertNull($msg1->getRelationType());
		self::assertSame('m.in_reply_to', $msg2->getRelationType());
		self::assertSame('$msg1', $msg2->getInReplyTo());
		self::assertSame('nc-1', $msg2->getTransactionId());
		self::assertSame('m.annotation', $reaction->getRelationType());
		self::assertSame('$msg2', $reaction->getRelatedEventId());
		self::assertSame('m.image', $img->getMsgType());
		self::assertFalse($img->isState());
	}

	public function testRoomStateAndPowerLevels(): void {
		$room = $this->batch->joined['!room:hs'];
		$state = new RoomState('!room:hs');
		$state->applyAll($room->getStateEvents());
		self::assertNull($state->name);
		self::assertSame('A topic', $state->topic);
		self::assertSame('10', $state->roomVersion);
		self::assertFalse($state->isEncrypted());
		self::assertSame('Bob', $state->getMember('@bob:hs')?->displayName);
		self::assertSame(Member::JOIN, $state->getMembership('@alice:hs'));
		self::assertSame(Member::LEAVE, $state->getMembership('@nobody:hs'));

		$pl = $state->getPowerLevels();
		self::assertTrue($pl->isAdmin('@alice:hs'));
		self::assertFalse($pl->isModerator('@bob:hs'));
		self::assertTrue($pl->canSendMessage('@bob:hs'));
		self::assertFalse($pl->canSendEvent('@bob:hs', 'm.room.name', true));
		self::assertTrue($pl->canSendEvent('@alice:hs', 'm.room.name', true));
		self::assertTrue($pl->canActOn('@alice:hs', '@bob:hs', 'kick'));
		self::assertFalse($pl->canActOn('@bob:hs', '@alice:hs', 'kick'));
		self::assertTrue($pl->canDo('@bob:hs', 'invite'));
		self::assertSame(['@alice:hs' => 100, '@bob:hs' => 50], $pl->withUserLevel('@bob:hs', 50)['users']);
	}

	public function testNameCalculation(): void {
		$room = $this->batch->joined['!room:hs'];
		$state = new RoomState('!room:hs');
		$state->applyAll($room->getStateEvents());
		self::assertSame('Bob', NameCalculator::calculate($state, '@alice:hs', $room->getHeroes(), 2, 0));

		$state->apply($room->state[0]); // no-op re-apply
		$named = new RoomState('!x:hs');
		$named->apply(new \Nextcloud\Matrix\Model\Event('$n', 'm.room.name', '@a:hs', 1, ['name' => 'Team'], ''));
		self::assertSame('Team', NameCalculator::calculate($named, '@alice:hs'));

		$empty = new RoomState('!e:hs');
		self::assertSame('Empty room', NameCalculator::calculate($empty, '@alice:hs'));

		$many = new RoomState('!m:hs');
		foreach (['@a:hs' => 'Ann', '@b:hs' => 'Ben', '@c:hs' => 'Ann'] as $id => $name) {
			$many->apply(new \Nextcloud\Matrix\Model\Event('$' . $id, 'm.room.member', $id, 1, ['membership' => 'join', 'displayname' => $name], $id));
		}
		self::assertSame('Ann (@a:hs), Ben and Ann (@c:hs)', NameCalculator::calculate($many, '@me:hs'));
		self::assertSame('Ann (@a:hs), Ben and 3 others', NameCalculator::calculate($many, '@me:hs', ['@a:hs', '@b:hs'], 6, 0));
	}

	public function testInviteAndLeave(): void {
		$invite = $this->batch->invited['!invite:hs'];
		self::assertSame('@carol:hs', $invite->getInviter('@alice:hs'));
		self::assertTrue($invite->isDirect('@alice:hs'));
		self::assertSame('Invited room', $invite->getState()->name);

		$left = $this->batch->left['!left:hs'];
		self::assertSame('leave', $left->getOwnMembershipEvent('@alice:hs')?->content['membership']);
	}

	public function testSpaceDetection(): void {
		$state = new RoomState('!s:hs');
		$state->apply(new \Nextcloud\Matrix\Model\Event('$c', 'm.room.create', '@a:hs', 1, ['type' => 'm.space', 'creator' => '@a:hs'], ''));
		self::assertTrue($state->isSpace());
	}
}
