<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
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

class CallEndedForEveryoneEvent extends ACallEndedForEveryoneEvent {
	public function __construct(
		Room $room,
		?Participant $actor = null,
		/** @var string[] */
		protected array $sessionIds = [],
		/** @var string[] */
		protected array $userIds = [],
	) {
		parent::__construct(
			$room,
			$actor
		);
	}

	/**
	 * @return string[]
	 */
	public function getSessionIds(): array {
		return $this->sessionIds;
	}

	/**
	 * @return string[]
	 */
	public function getUserIds(): array {
		return $this->userIds;
	}
}
