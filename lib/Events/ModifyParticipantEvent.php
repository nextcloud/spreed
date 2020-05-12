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

class ModifyParticipantEvent extends ParticipantEvent {

	/** @var string */
	protected $parameter;
	/** @var int|string|bool */
	protected $newValue;
	/** @var int|string|bool|null */
	protected $oldValue;


	public function __construct(Room $room,
								Participant $participant,
								string $parameter,
								$newValue,
								$oldValue = null) {
		parent::__construct($room, $participant);
		$this->parameter = $parameter;
		$this->newValue = $newValue;
		$this->oldValue = $oldValue;
	}

	/**
	 * @return string
	 */
	public function getParameter(): string {
		return $this->parameter;
	}

	/**
	 * @return int|string|bool
	 */
	public function getNewValue() {
		return $this->newValue;
	}

	/**
	 * @return int|string|bool|null
	 */
	public function getOldValue() {
		return $this->oldValue;
	}
}
