<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * The canonical form of an imported message and its SHA-256, computed the
 * same way by the importing app from its source and by Talk from what it
 * stored, so both sides can check that a message arrived unchanged.
 *
 * Ten values, each JSON-encoded without escaping slashes or non-ASCII
 * characters (U+2028 and U+2029 stay escaped), joined with "\n":
 *
 * 1. ref (string)
 * 2. parent ref (string, '' for none)
 * 3. actor type (string, `users` or `imported`)
 * 4. actor ID (string)
 * 5. creation time (string, RFC 3339 in UTC with seconds, e.g. `2026-10-30T09:15:00Z`)
 * 6. text (string, '' for system messages)
 * 7. file IDs (list of integers, ascending)
 * 8. reactions (list of `[actor type, actor ID, emoji]`, sorted by these three strings byte-wise)
 * 9. system kind (string, '' for none)
 * 10. members (list of `[actor type, actor ID]`, sorted byte-wise)
 *
 * The hash is the lowercase hex SHA-256 of the UTF-8 bytes.
 */
final class CanonicalForm {
	/**
	 * @param list<int> $fileIds
	 * @param list<array{0: string, 1: string, 2: string}> $reactions
	 * @param list<array{0: string, 1: string}> $members
	 */
	public static function hash(
		string $ref,
		string $parentRef,
		string $actorType,
		string $actorId,
		\DateTimeInterface $created,
		string $text,
		array $fileIds,
		array $reactions,
		string $systemKind,
		array $members,
	): string {
		sort($fileIds, SORT_NUMERIC);
		usort($reactions, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]) ?: strcmp($a[2], $b[2]));
		usort($members, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));

		$utc = \DateTimeImmutable::createFromInterface($created)->setTimezone(new \DateTimeZone('UTC'));
		$values = [
			$ref,
			$parentRef,
			$actorType,
			$actorId,
			$utc->format('Y-m-d\TH:i:s\Z'),
			$text,
			array_map(intval(...), $fileIds),
			array_map(static fn (array $r): array => [$r[0], $r[1], $r[2]], $reactions),
			$systemKind,
			array_map(static fn (array $m): array => [$m[0], $m[1]], $members),
		];

		$lines = array_map(
			static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			$values,
		);
		return hash('sha256', implode("\n", $lines));
	}

	public static function hashMessage(ImportMessage $message): string {
		return self::hash(
			$message->ref,
			$message->parentRef,
			$message->actor->type,
			$message->actor->id,
			$message->created,
			$message->systemKind === '' ? $message->text : '',
			$message->fileIds,
			array_map(static fn (ImportReaction $r): array => [$r->actor->type, $r->actor->id, $r->emoji], $message->reactions),
			$message->systemKind,
			array_map(static fn (ImportActor $m): array => [$m->type, $m->id], $message->members),
		);
	}
}
