<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * A message as {@see IHistoryReader} reads it for one person
 */
final class ReadMessage {
	/**
	 * @param string $text the rich text as the person sees it, with {placeholders}; '' for a deleted message
	 * @param array<string, array<string, string>> $parameters the placeholders' rich objects (mentions, files, polls, …)
	 * @param int $replyTo the message this one answers, 0 for none or one the person can't see
	 * @param array<string, int> $reactions count per emoji
	 * @param string $systemKind the kind of a system message ("user_added", "call_ended", …), '' for a message people wrote
	 * @param ?\DateTimeImmutable $edited the time of the last edit
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $actorType,
		public readonly string $actorId,
		public readonly string $displayName,
		public readonly \DateTimeImmutable $time,
		public readonly string $text,
		public readonly array $parameters,
		public readonly int $replyTo,
		public readonly array $reactions,
		public readonly string $systemKind,
		public readonly ?\DateTimeImmutable $edited,
		public readonly bool $deleted,
	) {
	}
}
