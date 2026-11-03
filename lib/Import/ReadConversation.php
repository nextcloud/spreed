<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * A conversation as {@see IHistoryReader} reads it for one person
 */
final class ReadConversation {
	/**
	 * @param string $name the name the person sees (the other person's name in a one-to-one conversation)
	 * @param IHistoryReader::TYPE_* $type
	 * @param int $participants number of participants
	 * @param int $messages number of messages the person can see, including those Talk shows as system messages only to some
	 * @param ?\DateTimeImmutable $historySince the person's history start, null when they see the whole history
	 */
	public function __construct(
		public readonly string $token,
		public readonly string $name,
		public readonly string $type,
		public readonly int $participants,
		public readonly int $messages,
		public readonly ?\DateTimeImmutable $lastActivity,
		public readonly ?\DateTimeImmutable $historySince,
	) {
	}
}
