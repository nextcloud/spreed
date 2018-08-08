<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Participant {
	const OWNER = 1;
	const MODERATOR = 2;
	const USER = 3;
	const GUEST = 4;
	const USER_SELF_JOINED = 5;

	/** @var IDBConnection */
	protected $db;
	/** @var Room */
	protected $room;
	/** @var string */
	protected $user;
	/** @var int */
	protected $participantType;
	/** @var int */
	protected $lastPing;
	/** @var string */
	protected $sessionId;
	/** @var bool */
	protected $inCall;
	/** @var bool */
	private $isPinned;
	/** @var \DateTime|null */
	private $lastMention;

	/**
	 * @param IDBConnection $db
	 * @param Room $room
	 * @param string $user
	 * @param int $participantType
	 * @param int $lastPing
	 * @param string $sessionId
	 * @param bool $inCall
	 * @param bool $isPinned
	 * @param \DateTime|null $lastMention
	 */
	public function __construct(IDBConnection $db, Room $room, $user, $participantType, $lastPing, $sessionId, $inCall, $isPinned, \DateTime $lastMention = null) {
		$this->db = $db;
		$this->room = $room;
		$this->user = $user;
		$this->participantType = $participantType;
		$this->lastPing = $lastPing;
		$this->sessionId = $sessionId;
		$this->inCall = $inCall;
		$this->isPinned = $isPinned;
		$this->lastMention = $lastMention;
	}

	public function getUser() {
		return $this->user;
	}

	public function getParticipantType() {
		return $this->participantType;
	}

	public function getLastPing() {
		return $this->lastPing;
	}

	public function getSessionId() {
		return $this->sessionId;
	}

	public function isInCall() {
		return $this->inCall;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getLastMention() {
		return $this->lastMention;
	}

	public function isPinned(): bool {
		return $this->isPinned;
	}

	public function setIsPinned(bool $pin) {
		if (!$this->user) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('favorite', $query->createNamedParameter((int) $pin, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($this->user)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())));
		$query->execute();

		$this->isPinned = $pin;
		return true;
	}
}
