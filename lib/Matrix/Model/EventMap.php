<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Mapping between a Matrix event and the Talk message it produced. Also the
 * idempotency record that makes replaying a sync batch harmless.
 *
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setEventId(string $eventId)
 * @method string getEventId()
 * @method void setEventType(string $eventType)
 * @method string getEventType()
 * @method void setCommentId(?int $commentId)
 * @method ?int getCommentId()
 * @method void setTxnId(?string $txnId)
 * @method ?string getTxnId()
 * @method void setOriginTs(int $originTs)
 * @method int getOriginTs()
 * @method void setSender(string $sender)
 * @method string getSender()
 * @method void setRelatesTo(?string $relatesTo)
 * @method ?string getRelatesTo()
 * @method void setRelType(?string $relType)
 * @method ?string getRelType()
 * @method void setEncrypted(bool $encrypted)
 * @method bool getEncrypted()
 * @method void setCiphertext(?string $ciphertext)
 * @method ?string getCiphertext()
 * @method void setDecryptState(int $decryptState)
 * @method int getDecryptState()
 * @method void setSessionId(?string $sessionId)
 * @method ?string getSessionId()
 * @method void setProcessed(bool $processed)
 * @method bool getProcessed()
 */
class EventMap extends Entity {
	public const DECRYPT_NA = 0;
	public const DECRYPT_OK = 1;
	public const DECRYPT_MISSING_SESSION = 2;
	public const DECRYPT_FAILED = 3;

	protected string $matrixRoomId = '';
	protected string $eventId = '';
	protected string $eventType = '';
	protected ?int $commentId = null;
	protected ?string $txnId = null;
	protected int $originTs = 0;
	protected string $sender = '';
	protected ?string $relatesTo = null;
	protected ?string $relType = null;
	protected bool $encrypted = false;
	protected ?string $ciphertext = null;
	protected int $decryptState = self::DECRYPT_NA;
	protected ?string $sessionId = null;
	protected bool $processed = true;

	public function __construct() {
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('eventId', Types::STRING);
		$this->addType('eventType', Types::STRING);
		$this->addType('commentId', Types::BIGINT);
		$this->addType('txnId', Types::STRING);
		$this->addType('originTs', Types::BIGINT);
		$this->addType('sender', Types::STRING);
		$this->addType('relatesTo', Types::STRING);
		$this->addType('relType', Types::STRING);
		$this->addType('encrypted', Types::BOOLEAN);
		$this->addType('ciphertext', Types::STRING);
		$this->addType('decryptState', Types::SMALLINT);
		$this->addType('sessionId', Types::STRING);
		$this->addType('processed', Types::BOOLEAN);
	}
}
