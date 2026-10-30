<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * One message to import. A message with a system kind has no text; its
 * members are the people the event is about.
 */
final class ImportMessage {
	public const SYSTEM_USER_ADDED = 'user_added';
	public const SYSTEM_USER_REMOVED = 'user_removed';
	public const SYSTEM_HISTORY_SHARED = 'history_shared';

	/**
	 * @param string $ref the importer's key of the message, unique per conversation (at most 255 characters)
	 * @param string $text Markdown with Talk mentions; nobody is notified about them
	 * @param string $parentRef ref of the message this one replies to, '' for none
	 * @param string $threadTitle starts a thread with this message (only without a parent)
	 * @param ?\DateTimeImmutable $edited time of the last edit in the source
	 * @param ''|self::SYSTEM_* $systemKind
	 * @param list<ImportActor> $members the people a system message is about
	 * @param list<int> $fileIds files shared into the conversation with the message
	 * @param list<ImportReaction> $reactions
	 * @param string $sha256 the importer's hash of the canonical form ({@see CanonicalForm}); informational
	 * @param bool $newVersion replace the stored message when its content differs and nobody changed it in Talk
	 */
	public function __construct(
		public readonly string $ref,
		public readonly ImportActor $actor,
		public readonly \DateTimeImmutable $created,
		public readonly string $text = '',
		public readonly string $parentRef = '',
		public readonly string $threadTitle = '',
		public readonly ?\DateTimeImmutable $edited = null,
		public readonly string $systemKind = '',
		public readonly array $members = [],
		public readonly array $fileIds = [],
		public readonly array $reactions = [],
		public readonly string $sha256 = '',
		public readonly bool $newVersion = false,
	) {
	}
}
