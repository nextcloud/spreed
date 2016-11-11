<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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

	/**
	 * @param IUser $user
	 */
	public function addUser(IUser $user) {
		$query = $this->db->getQueryBuilder();
		$query->insert('spreedme_room_participants')
			->values(
				[
					'userId' => $query->createNamedParameter($user),
					'roomId' => $query->createNamedParameter($this->getId()),
					'lastPing' => $query->createNamedParameter('0'),
				]
			);
		$query->execute();
	}
}
