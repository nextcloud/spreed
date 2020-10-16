<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setAttendeeId(int $attendeeId)
 * @method string getAttendeeId()
 * @method void setSessionId(string $sessionId)
 * @method string getSessionId()
 * @method void setInCall(int $inCall)
 * @method int getInCall()
 * @method void setLastPing(int $lastPing)
 * @method int getLastPing()
 */
class Session extends Entity {

	/** @var int */
	protected $attendeeId;

	/** @var string */
	protected $sessionId;

	/** @var int */
	protected $inCall;

	/** @var int */
	protected $lastPing;

	public function __construct() {
		$this->addType('attendee_id', 'int');
		$this->addType('session_id', 'string');
		$this->addType('in_call', 'int');
		$this->addType('last_ping', 'int');
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
