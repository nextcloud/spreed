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
 * Megolm session of a sender in a room, to decrypt their messages
 *
 * @method void setAccountId(string $accountId)
 * @method string getAccountId()
 * @method void setRoomId(string $roomId)
 * @method string getRoomId()
 * @method void setSessionId(string $sessionId)
 * @method string getSessionId()
 * @method void setSender(string $sender)
 * @method string getSender()
 * @method void setSenderKey(string $senderKey)
 * @method string getSenderKey()
 * @method void setFirstKnownIndex(int $firstKnownIndex)
 * @method int getFirstKnownIndex()
 * @method void setPickle(string $pickle)
 * @method string getPickle()
 */
class InboundGroupSession extends SnowflakeAwareEntity {
	protected string $accountId = '';
	/** Matrix room id */
	protected string $roomId = '';
	protected string $sessionId = '';
	/** Matrix user id of the device that shared the session, only their messages are decrypted with it */
	protected string $sender = '';
	/** Identity key of the device that shared the session */
	protected string $senderKey = '';
	protected int $firstKnownIndex = 0;
	/** Encrypted session state */
	protected string $pickle = '';

	public function __construct() {
		$this->addType('accountId', Types::STRING);
		$this->addType('roomId', Types::STRING);
		$this->addType('sessionId', Types::STRING);
		$this->addType('sender', Types::STRING);
		$this->addType('senderKey', Types::STRING);
		$this->addType('firstKnownIndex', Types::BIGINT);
		$this->addType('pickle', Types::TEXT);
	}
}
