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
 * A session is the "I'm online in this conversation" state of Talk, you get one
 * when opening the conversation while the inCall flag tells if you are just
 * online (chatting), or in a call (with audio, camera or even sip).
 * Currently it's limited to 1 per attendee, but the plan is to remove this
 * restriction in the future, so e.g. in the future you can join with your phone
 * on the SIP bridge, have your video/screenshare on the laptop and chat in the
 * mobile app.
 *
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
		$this->addType('attendeeId', 'int');
		$this->addType('sessionId', 'string');
		$this->addType('inCall', 'int');
		$this->addType('lastPing', 'int');
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
