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
use OCP\IDBConnection;

class Manager {

	/** @var IDBConnection */
	private $db;

	/**
	 * Manager constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param int $id
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomById($id) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return new Room($this->db, (int) $row['id'], (int) $row['type'], $row['name']);
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @return Room
	 * @throws \BadMethodCallException
	 */
	public function createRoom($type, $name) {
		if (!in_array($type, [Room::ONE_TO_ONE_CALL, Room::GROUP_CALL])) {
			throw new \BadMethodCallException('Invalid room type');
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('spreedme_rooms')
			->values(
				[
					'name' => $query->createNamedParameter($name),
					'type' => $query->createNamedParameter($type),
				]
			);
		$query->execute();
		$roomId = $query->getLastInsertId();

		return new Room($this->db, $roomId, $type, $name);
	}
}
