<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

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
 * @psalm-method int-mask<1, 2, 4, 8> getInCall()
 * @method void setLastPing(int $lastPing)
 * @method int getLastPing()
 * @psalm-method int<0, max> getLastPing()
 * @method void setState(int $state)
 * @method int getState()
 * @psalm-method self::STATE_* getState()
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
		$this->addType('attendeeId', 'int');
		$this->addType('sessionId', 'string');
		$this->addType('inCall', 'int');
		$this->addType('lastPing', 'int');
		$this->addType('state', 'int');
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
