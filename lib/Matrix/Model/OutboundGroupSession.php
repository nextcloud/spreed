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
 * Megolm session an account encrypts its messages to a room with
 *
 * @method void setAccountId(string $accountId)
 * @method string getAccountId()
 * @method void setRoomId(string $roomId)
 * @method string getRoomId()
 * @method void setPickle(string $pickle)
 * @method string getPickle()
 * @method void setSharedWith(string $sharedWith)
 * @method string getSharedWith()
 */
class OutboundGroupSession extends SnowflakeAwareEntity {
	protected string $accountId = '';
	/** Matrix room id */
	protected string $roomId = '';
	/** Encrypted session state */
	protected string $pickle = '';
	/** JSON list of the devices that received the session, as `user id|device id` */
	protected string $sharedWith = '[]';

	public function __construct() {
		$this->addType('accountId', Types::STRING);
		$this->addType('roomId', Types::STRING);
		$this->addType('pickle', Types::TEXT);
		$this->addType('sharedWith', Types::TEXT);
	}
}
