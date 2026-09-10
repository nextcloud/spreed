<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Room;

use Nextcloud\Matrix\Model\Member;
use Nextcloud\Matrix\Model\RoomState;

/**
 * "Calculating the display name for a room" (spec §13.2.2.1), with the heroes
 * from the room summary when the member list is lazy-loaded.
 */
final class NameCalculator {
	/**
	 * @param list<string> $heroes m.heroes from the sync summary
	 * @param callable(string): string $labels Translator for the fixed strings ('Empty room', 'and %d others', ' and ')
	 */
	public static function calculate(RoomState $state, string $ownUserId, array $heroes = [], ?int $joinedCount = null, ?int $invitedCount = null, ?callable $labels = null): string {
		$t = $labels ?? static fn (string $s): string => $s;

		if ($state->name !== null) {
			return $state->name;
		}
		if ($state->canonicalAlias !== null) {
			return $state->canonicalAlias;
		}

		$members = $state->getMembers();
		$others = array_values(array_filter($members, static fn (Member $m) => $m->userId !== $ownUserId && ($m->isJoined() || $m->isInvited())));
		usort($others, static fn (Member $a, Member $b) => strcmp($a->userId, $b->userId));

		if ($heroes === []) {
			$heroes = array_map(static fn (Member $m) => $m->userId, array_slice($others, 0, 5));
		}
		$heroNames = [];
		foreach ($heroes as $heroId) {
			if ($heroId === $ownUserId) {
				continue;
			}
			$heroNames[] = self::disambiguate($members[$heroId] ?? null, $heroId, $members);
		}

		$total = ($joinedCount ?? count(array_filter($members, static fn (Member $m) => $m->isJoined())))
			+ ($invitedCount ?? count(array_filter($members, static fn (Member $m) => $m->isInvited())));
		$othersCount = max(0, $total - 1);

		if ($heroNames === []) {
			// Nobody else: name after former members, else "Empty room"
			$left = array_values(array_filter($members, static fn (Member $m) => $m->userId !== $ownUserId && !$m->isJoined() && !$m->isInvited()));
			if ($left !== []) {
				usort($left, static fn (Member $a, Member $b) => strcmp($a->userId, $b->userId));
				$names = array_map(static fn (Member $m) => self::disambiguate($m, $m->userId, $members), array_slice($left, 0, 5));
				return sprintf($t('Empty room (was %s)'), self::joinNames($names, count($left) - count($names), $t));
			}
			return $t('Empty room');
		}

		return self::joinNames($heroNames, $othersCount - count($heroNames), $t);
	}

	/**
	 * @param list<string> $names
	 * @param callable(string): string $t
	 */
	private static function joinNames(array $names, int $remaining, callable $t): string {
		if ($remaining > 0) {
			return sprintf($t('%1$s and %2$d others'), implode(', ', $names), $remaining);
		}
		if (count($names) === 1) {
			return $names[0];
		}
		$last = array_pop($names);
		return implode(', ', $names) . $t(' and ') . $last;
	}

	/** @param array<string, Member> $members */
	private static function disambiguate(?Member $member, string $userId, array $members): string {
		$name = $member?->displayName;
		if ($name === null || trim($name) === '') {
			return $userId;
		}
		foreach ($members as $other) {
			if ($other->userId !== $userId && $other->displayName === $name && ($other->isJoined() || $other->isInvited())) {
				return $name . ' (' . $userId . ')';
			}
		}
		return $name;
	}
}
