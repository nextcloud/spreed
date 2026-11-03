<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * Something a history import wrote, found again by the importing app's ref
 *
 * - room: object ID is the room ID, owner ID the importing account, parent
 *   ID the newest imported message at the last finish(), details the time
 *   the import last wrote to it (creation, participants, finish())
 * - message: object ID is the message ID, sha256 the canonical hash as
 *   written, details the IDs of the other comments the message consists of
 * - participant: ref is the user ID, object ID the attendee ID
 * - actor: ref is the placeholder ID, display name its name
 * - share: ref is the file ID, object ID the room share ID
 *
 * @method void setApp(string $app)
 * @method string getApp()
 * @method void setKind(string $kind)
 * @method string getKind()
 * @method void setRefHash(string $refHash)
 * @method string getRefHash()
 * @method void setRef(?string $ref)
 * @method ?string getRef()
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setObjectId(int $objectId)
 * @method int getObjectId()
 * @method void setParentId(int $parentId)
 * @method int getParentId()
 * @method void setSha256(?string $sha256)
 * @method ?string getSha256()
 * @method void setState(int $state)
 * @method int getState()
 * @method void setDisplayName(?string $displayName)
 * @method ?string getDisplayName()
 * @method void setOwnerId(?string $ownerId)
 * @method ?string getOwnerId()
 * @method void setDetails(?string $details)
 * @method ?string getDetails()
 */
class ImportRef extends SnowflakeAwareEntity {
	public const KIND_ROOM = 'room';
	public const KIND_MESSAGE = 'message';
	public const KIND_PARTICIPANT = 'participant';
	public const KIND_ACTOR = 'actor';
	public const KIND_SHARE = 'share';

	public const STATE_ACTIVE = 0;
	public const STATE_REMOVED = 1;

	protected string $app = '';
	protected string $kind = '';
	protected string $refHash = '';
	protected ?string $ref = null;
	protected int $roomId = 0;
	protected int $objectId = 0;
	protected int $parentId = 0;
	protected ?string $sha256 = null;
	protected int $state = self::STATE_ACTIVE;
	protected ?string $displayName = null;
	protected ?string $ownerId = null;
	protected ?string $details = null;

	public function __construct() {
		$this->addType('app', Types::STRING);
		$this->addType('kind', Types::STRING);
		$this->addType('refHash', Types::STRING);
		$this->addType('ref', Types::STRING);
		$this->addType('roomId', Types::BIGINT);
		$this->addType('objectId', Types::BIGINT);
		$this->addType('parentId', Types::BIGINT);
		$this->addType('sha256', Types::STRING);
		$this->addType('state', Types::SMALLINT);
		$this->addType('displayName', Types::STRING);
		$this->addType('ownerId', Types::STRING);
		$this->addType('details', Types::TEXT);
	}

	/**
	 * The key of a ref in the unique index; refs of things inside a
	 * conversation are scoped by the room.
	 */
	public static function hashRef(string $kind, int $roomId, string $ref): string {
		return hash('sha256', $kind . "\n" . $roomId . "\n" . $ref);
	}

	/**
	 * Unix time the import last wrote to a conversation, 0 when unknown
	 */
	public function getLastWrite(): int {
		$decoded = json_decode((string)$this->details, true);
		return is_array($decoded) ? (int)($decoded['lastWrite'] ?? 0) : 0;
	}

	public function setLastWrite(int $time): void {
		$this->setDetails(json_encode(['lastWrite' => $time], JSON_THROW_ON_ERROR));
	}

	/**
	 * Comment IDs of a message besides the main one: the other file shares
	 * and system messages it consists of, and its reactions
	 *
	 * @return array{parts: list<int>, reactions: list<array{type: string, id: string, emoji: string, comment: int}>}
	 */
	public function getDecodedDetails(): array {
		$details = ['parts' => [], 'reactions' => []];
		if ($this->details === null || $this->details === '') {
			return $details;
		}
		$decoded = json_decode($this->details, true);
		if (!is_array($decoded)) {
			return $details;
		}
		if (isset($decoded['parts']) && is_array($decoded['parts'])) {
			$details['parts'] = array_values(array_map(intval(...), $decoded['parts']));
		}
		if (isset($decoded['reactions']) && is_array($decoded['reactions'])) {
			foreach ($decoded['reactions'] as $reaction) {
				if (is_array($reaction) && isset($reaction['type'], $reaction['id'], $reaction['emoji'], $reaction['comment'])) {
					$details['reactions'][] = [
						'type' => (string)$reaction['type'],
						'id' => (string)$reaction['id'],
						'emoji' => (string)$reaction['emoji'],
						'comment' => (int)$reaction['comment'],
					];
				}
			}
		}
		return $details;
	}

	/**
	 * @param list<int> $parts
	 * @param list<array{type: string, id: string, emoji: string, comment: int}> $reactions
	 */
	public function setEncodedDetails(array $parts, array $reactions): void {
		if ($parts === [] && $reactions === []) {
			$this->setDetails(null);
			return;
		}
		$this->setDetails(json_encode(['parts' => $parts, 'reactions' => $reactions], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
	}
}
