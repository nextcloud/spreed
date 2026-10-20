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
 * Olm session of an account with another device, used for to-device messages
 *
 * @method void setAccountId(string $accountId)
 * @method string getAccountId()
 * @method void setSenderKey(string $senderKey)
 * @method string getSenderKey()
 * @method void setSessionId(string $sessionId)
 * @method string getSessionId()
 * @method void setPickle(string $pickle)
 * @method string getPickle()
 * @method void setLastUsed(int $lastUsed)
 * @method int getLastUsed()
 */
class OlmSession extends SnowflakeAwareEntity {
	protected string $accountId = '';
	/** Unpadded base64 identity key of the other device */
	protected string $senderKey = '';
	protected string $sessionId = '';
	/** Encrypted session state */
	protected string $pickle = '';
	protected int $lastUsed = 0;

	public function __construct() {
		$this->addType('accountId', Types::STRING);
		$this->addType('senderKey', Types::STRING);
		$this->addType('sessionId', Types::STRING);
		$this->addType('pickle', Types::TEXT);
		$this->addType('lastUsed', Types::BIGINT);
	}
}
