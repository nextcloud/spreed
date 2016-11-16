<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use OCP\IUser;

class Room {
	const ONE_TO_ONE_CALL = 1;
	const GROUP_CALL = 2;

	/** @var IDBConnection */
	private $db;

	/** @var int */
	private $id;
	/** @var int */
	private $type;
	/** @var string */
	private $name;

	/**
	 * Room constructor.
	 *
	 * @param IDBConnection $db
	 * @param int $id
	 * @param int $type
	 * @param string $name
	 */
	public function __construct(IDBConnection $db, $id, $type, $name) {
		$this->db = $db;
		$this->id = $id;
		$this->type = $type;
		$this->name = $name;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	public function deleteRoom() {
		$query = $this->db->getQueryBuilder();

		// Delete all participants
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		// Delete room
		$query->delete('spreedme_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	/**
	 * @param IUser $user
	 */
	public function addUser(IUser $user) {
		$query = $this->db->getQueryBuilder();
		$query->insert('spreedme_room_participants')
			->values(
				[
					'userId' => $query->createNamedParameter($user->getUID()),
					'roomId' => $query->createNamedParameter($this->getId()),
					'lastPing' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				]
			);
		$query->execute();
	}

	/**
	 * @param IUser $user
	 */
	public function removeUser(IUser $user) {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($user->getUID())));
		$query->execute();
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return array[] Array of users with [userId => [lastPing, sessionId]]
	 */
	public function getParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('lastPing', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();

		$rows = [];
		while ($row = $result->fetch()) {
			$rows[$row['userId']] = [
				'lastPing' => (int) $row['lastPing'],
				'sessionId' => $row['sessionId'],
			];
		}
		$result->closeCursor();

		return $rows;
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return int
	 */
	public function getNumberOfParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_participants')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('lastPing', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();
		$row = $result->fetchAll();
		$result->closeCursor();

		return (int) $row['num_participants'];
	}

	/**
	 * @param string $participant
	 * @param int $timestamp
	 */
	public function ping($participant, $timestamp) {
		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('lastPing', $query->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('userId', $query->createNamedParameter($participant)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		$query->execute();
	}
}
