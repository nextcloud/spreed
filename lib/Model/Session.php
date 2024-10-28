<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A session is the "I'm online in this conversation" state of Talk, you get one
 * when opening the conversation while the inCall flag tells if you are just
 * online (chatting), or in a call (with audio, camera or even sip).
 *
 * @method void setAttendeeId(int $attendeeId)
 * @method string getAttendeeId()
 * @method void setSessionId(string $sessionId)
 * @method string getSessionId()
 * @method void setInCall(int $inCall)
 * @method int getInCall()
 * @method void setLastPing(int $lastPing)
 * @method int getLastPing()
 * @method void setState(int $state)
 * @method int getState()
 */
class Session extends Entity {
	public const STATE_INACTIVE = 0;
	public const STATE_ACTIVE = 1;

	public const SESSION_TIMEOUT = 30;
	public const SESSION_TIMEOUT_KILL = self::SESSION_TIMEOUT * 3 + 10;

	/** @var int */
	protected $attendeeId;

	/** @var string */
	protected $sessionId;

	/** @var int */
	protected $inCall;

	/** @var int */
	protected $lastPing;

	/** @var int */
	protected $state;

	public function __construct() {
		$this->addType('attendeeId', Types::BIGINT);
		$this->addType('sessionId', Types::STRING);
		$this->addType('inCall', Types::INTEGER);
		$this->addType('lastPing', Types::INTEGER);
		$this->addType('state', Types::SMALLINT);
	}

	/**
	 * @return array
	 */
	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'attendee_id' => $this->getAttendeeId(),
			'session_id' => $this->getSessionId(),
			'in_call' => $this->getInCall(),
			'last_ping' => $this->getLastPing(),
		];
	}
}
