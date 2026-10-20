<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * A Matrix event that was mirrored, recorded before the chat message is
 * written so every event is only applied once
 *
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setEventId(string $eventId)
 * @method string getEventId()
 * @method void setEventType(string $eventType)
 * @method string getEventType()
 * @method void setSender(string $sender)
 * @method string getSender()
 * @method void setCommentId(?int $commentId)
 * @method ?int getCommentId()
 * @method void setMegolmSession(?string $megolmSession)
 * @method ?string getMegolmSession()
 * @method void setMegolmIndex(?int $megolmIndex)
 * @method ?int getMegolmIndex()
 * @method void setCiphertext(?string $ciphertext)
 * @method ?string getCiphertext()
 * @method void setMediaKeys(?string $mediaKeys)
 * @method ?string getMediaKeys()
 */
class EventMap extends SnowflakeAwareEntity {
	/** Id of the MatrixRoom entity */
	protected string $matrixRoomId = '';
	protected string $eventId = '';
	protected string $eventType = '';
	protected string $sender = '';
	/** Chat message, reaction or deletion created for the event */
	protected ?int $commentId = null;
	/** Megolm session and message index of encrypted events, an index is only used once */
	protected ?string $megolmSession = null;
	protected ?int $megolmIndex = null;
	/** Content of an encrypted event that waits for its key */
	protected ?string $ciphertext = null;
	/** Encrypted JSON with the key, IV and hash of an encrypted attachment */
	protected ?string $mediaKeys = null;

	public function __construct() {
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('eventId', Types::STRING);
		$this->addType('eventType', Types::STRING);
		$this->addType('sender', Types::STRING);
		$this->addType('commentId', Types::BIGINT);
		$this->addType('megolmSession', Types::STRING);
		$this->addType('megolmIndex', Types::BIGINT);
		$this->addType('ciphertext', Types::TEXT);
		$this->addType('mediaKeys', Types::TEXT);
	}
}
