<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

/**
 * Reads a person's conversations and messages as that person sees them,
 * for an export of their data to another system.
 *
 * The reader applies Talk's visibility rules: messages from before the
 * person's history start ({@see \OCA\Talk\Participant::getHistorySince()})
 * are left out, deleted messages have no text, expired messages and
 * messages only others see are left out. It reads through the mappers and
 * the comments table, never through the live chat's services: no read
 * marker, unread counter, notification, last activity, session or
 * activity entry changes, and no event is dispatched besides the message
 * parsing Talk's own chat does. File parameters are taken from the file
 * cache, without setting up anybody's file system.
 *
 * Conversations of another server (federation) are not read: their
 * messages live there.
 *
 * The API has no user context and checks no Talk permissions beyond the
 * person being a participant: the caller authorizes the read.
 */
interface IHistoryReader {
	/** Most conversations or messages one call answers */
	public const MAX_ENTRIES = 500;

	public const TYPE_ONE_TO_ONE = 'one_to_one';
	public const TYPE_GROUP = 'group';
	public const TYPE_PUBLIC = 'public';
	public const TYPE_CHANGELOG = 'changelog';
	public const TYPE_NOTE_TO_SELF = 'note_to_self';

	/**
	 * The conversations $userId is a participant of, by conversation ID
	 *
	 * @param string $cursor '' for the first page, then the returned next cursor
	 * @param int $limit 1 to MAX_ENTRIES
	 * @return array{conversations: list<ReadConversation>, next_cursor: string} next_cursor is '' on the last page
	 * @throws ImportException CODE_INVALID for a cursor of another listing or a limit out of range
	 */
	public function conversations(string $userId, string $cursor, int $limit): array;

	/**
	 * Up to $limit messages after the message $afterId, by message ID, as
	 * $userId sees them
	 *
	 * @param int $afterId 0 for the first page, then the ID of the last message answered
	 * @param int $limit 1 to MAX_ENTRIES; fewer are answered only at the end
	 * @param bool $wholeHistory also the messages from before the person's history start (an export of the organisation with its own legal basis)
	 * @return list<ReadMessage>
	 * @throws ImportException CODE_NOT_FOUND when there is no such conversation or the person is not in it, CODE_INVALID for a limit out of range
	 */
	public function messages(string $userId, string $token, int $afterId, int $limit, bool $wholeHistory = false): array;
}
