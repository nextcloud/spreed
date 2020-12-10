<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Avatar;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;

class Util {

	/** @var string|null */
	protected $userId;

	/** @var TalkSession */
	protected $session;

	/** @var Manager */
	private $manager;

	/**
	 * @param string|null $userId
	 * @param TalkSession $session
	 * @param Manager $manager
	 */
	public function __construct(
			?string $userId,
			TalkSession $session,
			Manager $manager) {
		$this->userId = $userId;
		$this->session = $session;
		$this->manager = $manager;
	}

	/**
	 * @param Room $room
	 * @return Participant
	 * @throws ParticipantNotFoundException
	 */
	public function getCurrentParticipant(Room $room): Participant {
		$participant = null;
		try {
			$participant = $room->getParticipant($this->userId);
		} catch (ParticipantNotFoundException $e) {
			$participant = $room->getParticipantBySession($this->session->getSessionForRoom($room->getToken()));
		}

		return $participant;
	}

	/**
	 * @param Room $room
	 * @return bool
	 */
	public function isRoomListableByUser(Room $room): bool {
		return $this->manager->isRoomListableByUser($room, $this->userId);
	}
}
