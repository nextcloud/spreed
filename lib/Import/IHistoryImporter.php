<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCA\Talk\Room;

/**
 * Imports the history of conversations from another chat system.
 *
 * The importer writes conversations, participants and messages with their
 * original actors and times, keyed by the importing app's own refs, so every
 * call is safe to repeat. It is quiet: none of Talk's chat, participant or
 * conversation events is dispatched, so no notification, push, activity,
 * bot, webhook, signaling or federation delivery happens, and the room's
 * last message is only set by {@see self::finish()}. Only conversations the
 * same app created through {@see self::ensureConversation()} are accepted.
 *
 * Methods throw {@see ImportException} for a request Talk refuses as a whole;
 * entries of a list are answered one by one.
 */
interface IHistoryImporter {
	/** Most messages, refs or participants one call takes */
	public const MAX_ENTRIES = 500;

	/** Per participant "history visible from" ({@see ImportParticipant::$historySince}) */
	public const FEATURE_HISTORY_FROM = 'history-visible-from';
	/** Authors without an account, shown by name */
	public const FEATURE_PLACEHOLDERS = 'placeholder-actors';
	/** Posts with a title start a thread */
	public const FEATURE_THREADS = 'threads';
	/** Archived, muted, favorite and last read per person */
	public const FEATURE_IMPORT_STATE = 'import-state';
	/** Conversations linked to a calendar event or a file */
	public const FEATURE_OBJECT_LINK = 'object-link';
	/** Read-only conversations */
	public const FEATURE_READ_ONLY = 'read-only';

	/** @return list<string> the features of this importer (self::FEATURE_*) */
	public function getFeatures(): array;

	/**
	 * Creates or finds by $importRef (unique per importing app); owner is the importing service account.
	 *
	 * A group conversation gets the owner as its only participant until
	 * {@see self::setParticipants()} sets the real ones. A one-to-one
	 * conversation is named like Talk names them, with the JSON list of the
	 * two user IDs as $name; it has no participant until they are set, and
	 * one that exists outside the import between the same two users is
	 * refused with {@see ImportException::CODE_EXISTS}. A conversation found
	 * again is returned unchanged.
	 *
	 * @param \OCA\Talk\Room::TYPE_GROUP|\OCA\Talk\Room::TYPE_ONE_TO_ONE|int $type validated
	 * @param string $objectType '', \OCA\Talk\Room::OBJECT_TYPE_EVENT (object ID: the event UID) or \OCA\Talk\Room::OBJECT_TYPE_FILE (object ID: the file ID)
	 * @throws ImportException
	 */
	public function ensureConversation(string $app, string $importRef, int $type, string $name, string $description,
		\DateTimeImmutable $created, bool $readOnly, string $objectType, string $objectId, string $ownerUid): Room;

	/**
	 * The conversation created for $importRef, or null when there is none yet
	 *
	 * @throws ImportException
	 */
	public function findConversation(string $app, string $importRef): ?Room;

	/**
	 * The conversation with $token, when the app created it
	 *
	 * @throws ImportException
	 */
	public function getConversation(string $app, string $token): Room;

	/**
	 * Makes $participants the complete list of participants the importer
	 * manages: adds and updates them and removes managed ones that are not in
	 * the list. Participants others added are never changed or removed; one
	 * of them in the list is answered with {@see ImportException::CODE_EXISTS}.
	 * Nobody is notified; read markers start at $lastRead or at the newest
	 * message, so nothing counts as unread.
	 *
	 * @param list<ImportParticipant> $participants the complete list the importer manages
	 * @return list<ImportResult> one per participant, keyed like the input, with the attendee ID
	 * @throws ImportException
	 */
	public function setParticipants(string $app, Room $room, array $participants): array;

	/**
	 * @param list<ImportMessage> $messages in time order, parents before their replies
	 * @return list<ImportResult> one per message, with the message ID and the canonical hash as stored
	 * @throws ImportException
	 */
	public function importMessages(string $app, Room $room, array $messages): array;

	/**
	 * @param list<string> $refs
	 * @param bool $hash compute the canonical hash of what is stored now (and with it the changed flag)
	 * @return list<ImportMessageStat>
	 * @throws ImportException
	 */
	public function statMessages(string $app, Room $room, array $refs, bool $hash): array;

	/**
	 * Pages through the messages the app imported into the conversation, by message ID
	 *
	 * @param string $cursor '' for the first page, then the returned next cursor
	 * @return array{entries: list<array{ref: string, message_id: int}>, next_cursor: string} next_cursor is '' on the last page
	 * @throws ImportException
	 */
	public function listMessages(string $app, Room $room, string $cursor, int $limit): array;

	/**
	 * Deletes imported messages; Talk shows them as deleted
	 *
	 * @param list<string> $refs
	 * @return list<ImportResult>
	 * @throws ImportException
	 */
	public function removeMessages(string $app, Room $room, array $refs): array;

	/**
	 * Sets last_message and last_activity from the imported messages; no event.
	 *
	 * @throws ImportException
	 */
	public function finish(string $app, Room $room): void;
}
