<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Command\Developer;

use OCA\Talk\Developer\ChatGenerator;
use OCA\Talk\Room;
use Test\TestCase;

class ChatGeneratorTest extends TestCase {
	private const USERS = ['alice', 'bob', 'carol', 'dave', 'eve', 'frank', 'grace', 'heidi'];
	private const GROUPS = ['backend', 'frontend', 'qa'];

	public function testSameSeedYieldsIdenticalPlan(): void {
		$first = (new ChatGenerator(12345))->planRooms(self::USERS, self::GROUPS, 5, 5, 50, 14, 0.2, 0.3);
		$second = (new ChatGenerator(12345))->planRooms(self::USERS, self::GROUPS, 5, 5, 50, 14, 0.2, 0.3);

		// Byte-equal serialisation is the strictest possible determinism check.
		$this->assertSame(json_encode($first), json_encode($second));
	}

	public function testDifferentSeedsYieldDifferentPlans(): void {
		$first = (new ChatGenerator(1))->planRooms(self::USERS, self::GROUPS, 5, 5, 50, 14, 0.2, 0.3);
		$second = (new ChatGenerator(2))->planRooms(self::USERS, self::GROUPS, 5, 5, 50, 14, 0.2, 0.3);
		$this->assertNotSame(json_encode($first), json_encode($second));
	}

	public function testPickPoolIsDeterministicAndSubset(): void {
		$first = (new ChatGenerator(999))->pickPool(self::USERS, 4);
		$second = (new ChatGenerator(999))->pickPool(self::USERS, 4);
		$this->assertSame($first, $second);
		$this->assertCount(4, $first);
		foreach ($first as $picked) {
			$this->assertContains($picked, self::USERS);
		}
		$this->assertSame(array_values(array_unique($first)), $first, 'pool must not contain duplicates');
	}

	public function testPickPoolReturnsAllWhenSizeExceedsCandidates(): void {
		$picked = (new ChatGenerator(1))->pickPool(['a', 'b'], 10);
		$this->assertSame(['a', 'b'], $picked);
	}

	public function testPlanShapeMatchesExpectations(): void {
		$plans = (new ChatGenerator(42))->planRooms(self::USERS, self::GROUPS, 6, 5, 50, 14, 0.2, 0.3);
		$this->assertNotEmpty($plans);

		foreach ($plans as $plan) {
			$this->assertContains($plan['type'], [Room::TYPE_ONE_TO_ONE, Room::TYPE_GROUP, Room::TYPE_PUBLIC]);
			$this->assertNotEmpty($plan['name']);
			$this->assertContains($plan['owner'], $plan['users']);
			$this->assertGreaterThanOrEqual(2, count($plan['users']));

			if ($plan['type'] === Room::TYPE_ONE_TO_ONE) {
				$this->assertCount(2, $plan['users']);
				$this->assertSame([], $plan['groups']);
			}

			foreach ($plan['messages'] as $i => $msg) {
				$this->assertContains($msg['author'], $plan['users']);
				$this->assertNotSame('', $msg['text']);
				$this->assertGreaterThanOrEqual(0, $msg['secondsAgo']);
				$this->assertIsBool($msg['silent']);
				if ($msg['replyTo'] !== null) {
					$this->assertGreaterThanOrEqual(0, $msg['replyTo']);
					$this->assertLessThan($i, $msg['replyTo'], 'replyTo must point to an earlier message');
				}
			}

			// Timestamps must be chronologically ordered (oldest first => largest secondsAgo first).
			$prev = PHP_INT_MAX;
			foreach ($plan['messages'] as $msg) {
				$this->assertLessThanOrEqual($prev, $msg['secondsAgo']);
				$prev = $msg['secondsAgo'];
			}
		}
	}

	public function testMessageCountStaysInRequestedRange(): void {
		$plans = (new ChatGenerator(123))->planRooms(self::USERS, self::GROUPS, 20, 5, 500, 14, 0.0, 0.0);
		foreach ($plans as $plan) {
			$this->assertGreaterThanOrEqual(5, count($plan['messages']));
			$this->assertLessThanOrEqual(500, count($plan['messages']));
		}
	}

	public function testSameAuthorConsecutiveGapsAreAlwaysUnderFiveMinutes(): void {
		$plans = (new ChatGenerator(99))->planRooms(self::USERS, self::GROUPS, 5, 200, 300, 14, 0.0, 0.0);
		$sameAuthorPairs = 0;
		foreach ($plans as $plan) {
			$msgs = $plan['messages'];
			for ($i = 1; $i < count($msgs); $i++) {
				if ($msgs[$i]['author'] !== $msgs[$i - 1]['author']) {
					continue;
				}
				$sameAuthorPairs++;
				// Older message has the larger secondsAgo, so the gap is older - newer.
				$gap = $msgs[$i - 1]['secondsAgo'] - $msgs[$i]['secondsAgo'];
				$this->assertLessThan(300, $gap, "same-author gap at index $i was {$gap}s; must trigger author-grouping (< 5min)");
			}
		}
		$this->assertGreaterThan(20, $sameAuthorPairs, 'expected enough same-author pairs to make the assertion meaningful');
	}

	public function testMajorityOfConsecutiveGapsTriggerGrouping(): void {
		$plans = (new ChatGenerator(45))->planRooms(self::USERS, self::GROUPS, 5, 200, 400, 14, 0.0, 0.0);
		$gaps = [];
		foreach ($plans as $plan) {
			$msgs = $plan['messages'];
			for ($i = 1; $i < count($msgs); $i++) {
				$gaps[] = $msgs[$i - 1]['secondsAgo'] - $msgs[$i]['secondsAgo'];
			}
		}
		$this->assertNotEmpty($gaps);
		$underFiveMin = count(array_filter($gaps, static fn (int $g): bool => $g < 300));
		$ratio = $underFiveMin / count($gaps);
		$this->assertGreaterThan(0.65, $ratio, 'majority of gaps should be < 5min so grouping triggers; got ' . round($ratio * 100) . '%');
	}

	public function testSilentDistributionIsRoughlyFivePercent(): void {
		$plans = (new ChatGenerator(2024))->planRooms(self::USERS, self::GROUPS, 5, 400, 400, 14, 0.0, 0.0);

		$total = 0;
		$silent = 0;
		foreach ($plans as $plan) {
			foreach ($plan['messages'] as $msg) {
				$total++;
				if ($msg['silent']) {
					$silent++;
				}
			}
		}
		$this->assertGreaterThan(0, $total);
		$ratio = $silent / $total;
		$this->assertGreaterThan(0.02, $ratio, 'silent ratio too low');
		$this->assertLessThan(0.10, $ratio, 'silent ratio too high');
	}

	public function testMainUserAppearsInEveryRoomAndIsAlwaysOwner(): void {
		$pool = array_values(array_diff(self::USERS, ['alice']));
		$plans = (new ChatGenerator(2026))->planRooms($pool, self::GROUPS, 8, 5, 30, 14, 0.2, 0.3, 'alice');
		$this->assertNotEmpty($plans);

		foreach ($plans as $plan) {
			$this->assertContains('alice', $plan['users'], 'main user must appear in every room');
			$this->assertSame('alice', $plan['owner'], 'main user must own every room');
			if ($plan['type'] === Room::TYPE_ONE_TO_ONE) {
				$this->assertCount(2, $plan['users']);
				$this->assertNotSame('alice', $plan['users'][1], 'partner must be a different user');
			}
		}
	}

	public function testMainUserPlanIsDeterministic(): void {
		$pool = array_values(array_diff(self::USERS, ['alice']));
		$first = (new ChatGenerator(2026))->planRooms($pool, self::GROUPS, 5, 5, 30, 14, 0.2, 0.3, 'alice');
		$second = (new ChatGenerator(2026))->planRooms($pool, self::GROUPS, 5, 5, 30, 14, 0.2, 0.3, 'alice');
		$this->assertSame(json_encode($first), json_encode($second));
	}

	public function testBurstsAndVarietyExistAcrossLargeRun(): void {
		$plans = (new ChatGenerator(7))->planRooms(self::USERS, self::GROUPS, 3, 200, 300, 14, 0.0, 0.0);
		$this->assertNotEmpty($plans);

		$groupPlan = null;
		foreach ($plans as $plan) {
			if ($plan['type'] === Room::TYPE_GROUP && count($plan['users']) >= 3) {
				$groupPlan = $plan;
				break;
			}
		}
		$this->assertNotNull($groupPlan, 'expected at least one multi-user group room');

		$consecutiveSameAuthor = 0;
		$replies = 0;
		$authors = [];
		$previousAuthor = null;
		foreach ($groupPlan['messages'] as $msg) {
			if ($msg['author'] === $previousAuthor) {
				$consecutiveSameAuthor++;
			}
			$previousAuthor = $msg['author'];
			if ($msg['replyTo'] !== null) {
				$replies++;
			}
			$authors[$msg['author']] = true;
		}

		$this->assertGreaterThan(0, $consecutiveSameAuthor, 'expected bursts (consecutive same-author messages)');
		$this->assertGreaterThan(0, $replies, 'expected at least some replies');
		$this->assertGreaterThan(1, count($authors), 'expected multiple distinct authors');
	}
}
