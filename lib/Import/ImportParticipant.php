<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCA\Talk\Participant;

/**
 * A participant the importer manages. Unset state fields (null) keep Talk's
 * defaults for a new participant and the current value for an existing one.
 */
final class ImportParticipant {
	/**
	 * @param Participant::OWNER|Participant::MODERATOR|Participant::USER|int $participantType validated by the importer
	 * @param ?\DateTimeImmutable $historySince first message time the person may read, null for the whole history
	 * @param ?bool $archived the conversation is archived (hidden) for the person
	 * @param ?bool $muted notifications of the conversation are off ({@see Participant::NOTIFY_NEVER})
	 * @param ?bool $favorite the conversation is a favorite (pinned)
	 * @param ?\DateTimeImmutable $lastRead messages up to this time count as read; null: everything imported is read
	 */
	public function __construct(
		public readonly string $userId,
		public readonly int $participantType = Participant::USER,
		public readonly ?\DateTimeImmutable $historySince = null,
		public readonly ?bool $archived = null,
		public readonly ?bool $muted = null,
		public readonly ?bool $favorite = null,
		public readonly ?\DateTimeImmutable $lastRead = null,
	) {
	}
}
