<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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


use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

class Manager {

	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $secureRandom;

	/**
	 * Manager constructor.
	 *
	 * @param IDBConnection $db
	 * @param IConfig $config
	 * @param ISecureRandom $secureRandom
	 */
	public function __construct(IDBConnection $db, IConfig $config, ISecureRandom $secureRandom) {
		$this->db = $db;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * @param string $participant
	 * @return Room[]
	 */
	public function getRoomsForParticipant($participant) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms', 'r')
			->leftJoin('r', 'spreedme_room_participants', 'p', $query->expr()->andX(
				$query->expr()->eq('p.userId', $query->createNamedParameter($participant)),
				$query->expr()->eq('p.roomId', 'r.id')
			))
			->where($query->expr()->isNotNull('p.userId'));

		$result = $query->execute();
		$rooms = [];
		while ($row = $result->fetch()) {
			$rooms[] = new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);
		}
		$result->closeCursor();

		return $rooms;
	}

	/**
	 * Does *not* return public rooms for participants that have not been invited
	 *
	 * @param int $roomId
	 * @param string $participant
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForParticipant($roomId, $participant) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms', 'r')
			->where($query->expr()->eq('id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		if ($participant !== null) {
			// Non guest user
			$query->leftJoin('r', 'spreedme_room_participants', 'p', $query->expr()->andX(
					$query->expr()->eq('p.userId', $query->createNamedParameter($participant)),
					$query->expr()->eq('p.roomId', 'r.id')
				))
				->andWhere($query->expr()->isNotNull('p.userId'));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		$room = new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);

		if ($participant === null && $room->getType() !== Room::PUBLIC_CALL) {
			throw new RoomNotFoundException();
		}

		return $room;
	}

	/**
	 * Also returns public rooms for participants that have not been invited,
	 * so they can join.
	 *
	 * @param string $token
	 * @param string $participant
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForParticipantByToken($token, $participant) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms', 'r')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)))
			->setMaxResults(1);

		if ($participant !== null) {
			// Non guest user
			$query->leftJoin('r', 'spreedme_room_participants', 'p', $query->expr()->andX(
					$query->expr()->eq('p.userId', $query->createNamedParameter($participant)),
					$query->expr()->eq('p.roomId', 'r.id')
				));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		$room = new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);

		if ($room->getType() === Room::PUBLIC_CALL) {
			return $room;
		}

		if ($participant !== null && $row['userId'] === $participant) {
			return $room;
		}

		throw new RoomNotFoundException();
	}

	/**
	 * @param int $roomId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomById($roomId) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);
	}

	/**
	 * @param string $token
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByToken($token) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);
	}

	/**
	 * @param string $participant1
	 * @param string $participant2
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getOne2OneRoom($participant1, $participant2) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms', 'r1')
			->leftJoin('r1', 'spreedme_room_participants', 'p1', $query->expr()->andX(
				$query->expr()->eq('p1.userId', $query->createNamedParameter($participant1)),
				$query->expr()->eq('p1.roomId', 'r1.id')
			))
			->leftJoin('r1', 'spreedme_room_participants', 'p2', $query->expr()->andX(
				$query->expr()->eq('p2.userId', $query->createNamedParameter($participant2)),
				$query->expr()->eq('p2.roomId', 'r1.id')
			))
			->where($query->expr()->eq('r1.type', $query->createNamedParameter(Room::ONE_TO_ONE_CALL, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('p1.userId'))
			->andWhere($query->expr()->isNotNull('p2.userId'));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return new Room($this->db, $this->secureRandom, (int) $row['id'], (int) $row['type'], $row['token'], $row['name']);
	}

	/**
	 * @return Room
	 */
	public function createOne2OneRoom() {
		return $this->createRoom(Room::ONE_TO_ONE_CALL);
	}

	/**
	 * @param string $name
	 * @return Room
	 */
	public function createGroupRoom($name = '') {
		return $this->createRoom(Room::GROUP_CALL, $name);
	}

	/**
	 * @return Room
	 */
	public function createPublicRoom() {
		return $this->createRoom(Room::PUBLIC_CALL);
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @return Room
	 */
	private function createRoom($type, $name = '') {
		$token = $this->getNewToken();

		$query = $this->db->getQueryBuilder();
		$query->insert('spreedme_rooms')
			->values(
				[
					'name' => $query->createNamedParameter($name),
					'type' => $query->createNamedParameter($type, IQueryBuilder::PARAM_INT),
					'token' => $query->createNamedParameter($token),
				]
			);
		$query->execute();
		$roomId = $query->getLastInsertId();

		return new Room($this->db, $this->secureRandom, $roomId, $type, $token, $name);
	}

	/**
	 * @param string $userId
	 */
	public function disconnectUserFromAllRooms($userId) {
		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('sessionId', $query->createNamedParameter('0'))
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)));
		$query->execute();
	}

	/**
	 * @param string $sessionId
	 */
	public function removeSessionFromAllRooms($sessionId) {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)));
		$query->execute();
	}

	/**
	 * @param string $userId
	 * @return string[]
	 */
	public function getSessionIdsForUser($userId) {
		if (!is_string($userId) || $userId === '') {
			// No deleting messages for guests
			return [];
		}

		// Delete all messages from or to the current user
		$query = $this->db->getQueryBuilder();
		$query->select('sessionId')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)));
		$result = $query->execute();

		$sessionIds = [];
		while ($row = $result->fetch()) {
			if ($row['sessionId'] !== '0') {
				$sessionIds[] = $row['sessionId'];
			}
		}
		$result->closeCursor();

		return $sessionIds;
	}

	/**
	 * @param string[] $sessionIds
	 */
	public function deleteMessagesForSessionIds($sessionIds) {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_messages')
			->where($query->expr()->in('recipient', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orWhere($query->expr()->in('sender', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$query->execute();
	}

	/**
	 * @return string
	 */
	protected function getNewToken() {
		$chars = str_replace(['l', '0', '1'], '', ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
		$entropy = (int) $this->config->getAppValue('spreed', 'token_entropy', 4);

		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('spreedme_rooms')
			->where($query->expr()->eq('token', $query->createParameter('token')));

		$i = 0;
		while ($i < 1000) {
			try {
				return $this->generateNewToken($query, $entropy, $chars);
			} catch (\OutOfBoundsException $e) {
				$i++;
				if ($entropy >= 30 || $i >= 999) {
					// Max entropy of 30
					$i = 0;
				}
			}
		}

		$entropy++;
		$this->config->setAppValue('spreed', 'token_entropy', $entropy);
		return $this->generateNewToken($query, $entropy, $chars);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param int $entropy
	 * @param string $chars
	 * @return string
	 * @throws \OutOfBoundsException
	 */
	protected function generateNewToken(IQueryBuilder $query, $entropy, $chars) {
		$token = $this->secureRandom->generate($entropy, $chars);
		$query->setParameter('token', $token);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (is_array($row)) {
			// Token already in use
			throw new \OutOfBoundsException();
		}
		return $token;
	}
}
