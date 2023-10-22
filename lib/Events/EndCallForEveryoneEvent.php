<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

/**
 * @deprecated
 */
class EndCallForEveryoneEvent extends ModifyRoomEvent {
	/** @var string[] */
	protected array $sessionIds = [];
	/** @var string[] */
	protected array $userIds = [];

	public function __construct(
		Room $room,
		?Participant $actor = null,
	) {
		parent::__construct($room, 'in_call', Participant::FLAG_DISCONNECTED, null, $actor);
	}

	/**
	 * @param string[] $sessionIds
	 * @return void
	 */
	public function setSessionIds(array $sessionIds): void {
		$this->sessionIds = $sessionIds;
	}

	/**
	 * Only available in the after-event
	 */
	public function getSessionIds(): array {
		return $this->sessionIds;
	}

	/**
	 * @param string[] $userIds
	 * @return void
	 */
	public function setUserIds(array $userIds): void {
		$this->userIds = $userIds;
	}

	/**
	 * Only available in the after-event
	 */
	public function getUserIds(): array {
		return $this->userIds;
	}
}
