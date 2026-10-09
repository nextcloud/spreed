<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Client;

use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Client\Model\RoomState;
use OCA\Talk\Matrix\Client\Room\NameCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class NameCalculatorTest extends TestCase {
	public static function dataCalculate(): array {
		$alice = ['@alice:example.org', 'join', 'Alice'];
		$bob = ['@bob:example.org', 'join', 'Bob'];
		$carol = ['@carol:example.org', 'invite', 'Carol'];
		$dave = ['@dave:example.org', 'join', null];
		$bob2 = ['@bob:other.org', 'join', 'Bob'];
		$eve = ['@eve:example.org', 'join', 'Eve'];
		$eveLeft = ['@eve:example.org', 'leave', 'Eve'];

		return [
			'explicit name wins' => [['m.room.name' => 'Team', 'm.room.canonical_alias' => '#team:example.org'], [$alice, $bob], [], 'Team'],
			'alias before members' => [['m.room.canonical_alias' => '#team:example.org'], [$alice, $bob], [], '#team:example.org'],
			'direct chat' => [[], [$alice, $bob], [], 'Bob'],
			'two others with invite' => [[], [$alice, $bob, $carol], [], 'Bob and Carol'],
			'user id without display name' => [[], [$alice, $dave], [], '@dave:example.org'],
			'ambiguous display names' => [[], [$alice, $bob, $bob2], [], 'Bob (@bob:example.org) and Bob (@bob:other.org)'],
			'heroes from summary' => [[], [$alice], ['@bob:example.org'], '@bob:example.org'],
			'nobody else' => [[], [$alice], [], 'Empty room'],
			'only former members' => [[], [$alice, $eve, $eveLeft], [], 'Empty room (was Eve)'],
		];
	}

	#[DataProvider('dataCalculate')]
	public function testCalculate(array $stateContent, array $members, array $heroes, string $expected): void {
		$state = new RoomState('!room:example.org');
		foreach ($stateContent as $type => $value) {
			$key = $type === 'm.room.name' ? 'name' : 'alias';
			$state->apply(new Event('$' . $type, $type, '@alice:example.org', [$key => $value], ''));
		}
		foreach ($members as [$mxid, $membership, $displayName]) {
			$content = ['membership' => $membership];
			if ($displayName !== null) {
				$content['displayname'] = $displayName;
			}
			$state->apply(new Event('$' . $mxid, 'm.room.member', $mxid, $content, $mxid));
		}

		self::assertSame($expected, NameCalculator::calculate($state, '@alice:example.org', $heroes, null, null, static fn (string $text): string => $text));
	}

	public function testCalculateWithMoreMembers(): void {
		$state = new RoomState('!room:example.org');
		$state->apply(new Event('$1', 'm.room.member', '@bob:example.org', ['membership' => 'join', 'displayname' => 'Bob'], '@bob:example.org'));

		self::assertSame('Bob and 8 others', NameCalculator::calculate($state, '@alice:example.org', ['@bob:example.org'], 10, 0, static fn (string $text): string => $text));
	}
}
