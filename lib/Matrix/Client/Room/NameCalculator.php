<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Room;

use OCA\Talk\Matrix\Client\Model\Member;
use OCA\Talk\Matrix\Client\Model\RoomState;

/**
 * Calculating the display name for a room (spec 13.2.2.5), using the heroes
 * from the room summary when the member list is lazy-loaded
 */
final class NameCalculator {
	/**
	 * @param list<string> $heroes m.heroes from the sync summary
	 * @param callable(string): string $translate Translator for the fixed strings
	 */
	public static function calculate(RoomState $state, string $ownUserId, array $heroes, ?int $joinedCount, ?int $invitedCount, callable $translate): string {
		if ($state->name !== null) {
			return $state->name;
		}
		if ($state->canonicalAlias !== null) {
			return $state->canonicalAlias;
		}

		$members = $state->getMembers();
		if ($heroes === []) {
			$others = array_filter($members, static fn (Member $member): bool => $member->userId !== $ownUserId && ($member->isJoined() || $member->isInvited()));
			$heroes = array_map('strval', array_keys($others));
			sort($heroes);
			$heroes = array_slice($heroes, 0, 5);
		}

		$heroNames = [];
		foreach ($heroes as $heroId) {
			if ($heroId !== $ownUserId) {
				$heroNames[] = self::disambiguate($members[$heroId] ?? null, $heroId, $members);
			}
		}

		$total = ($joinedCount ?? count(array_filter($members, static fn (Member $member): bool => $member->isJoined())))
			+ ($invitedCount ?? count(array_filter($members, static fn (Member $member): bool => $member->isInvited())));

		if ($heroNames === []) {
			$left = array_filter($members, static fn (Member $member): bool => $member->userId !== $ownUserId && !$member->isJoined() && !$member->isInvited());
			if ($left === []) {
				return $translate('Empty room');
			}
			$leftIds = array_map('strval', array_keys($left));
			sort($leftIds);
			$names = array_map(static fn (string $userId): string => self::disambiguate($members[$userId], $userId, $members), array_slice($leftIds, 0, 5));
			return sprintf($translate('Empty room (was %s)'), self::joinNames($names, count($leftIds) - count($names), $translate));
		}

		return self::joinNames($heroNames, max(0, $total - 1) - count($heroNames), $translate);
	}

	/**
	 * @param list<string> $names
	 * @param callable(string): string $translate
	 */
	private static function joinNames(array $names, int $remaining, callable $translate): string {
		if ($remaining > 0) {
			return sprintf($translate('%1$s and %2$d others'), implode(', ', $names), $remaining);
		}
		if (count($names) === 1) {
			return $names[0];
		}
		$last = array_pop($names);
		return implode(', ', $names) . $translate(' and ') . $last;
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
