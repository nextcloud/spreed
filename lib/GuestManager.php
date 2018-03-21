<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

class GuestManager {

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param string $sessionHash
	 * @param string $displayName
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function updateName($sessionHash, $displayName) {
		$result = $this->connection->insertIfNotExist('*PREFIX*talk_guests', [
			'session_hash' => $sessionHash,
			'display_name' => $displayName,
		], ['session_hash']);

		if ($result === 1) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->update('talk_guests')
			->set('display_name', $query->createNamedParameter($displayName))
			->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));
		$query->execute();
	}

	/**
	 * @param string[] $sessionHashes
	 * @return string[]
	 */
	public function getNamesBySessionHashes(array $sessionHashes) {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('talk_guests')
			->where($query->expr()->in('session_hash', $query->createNamedParameter($sessionHashes, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $query->execute();

		$map = [];

		while ($row = $result->fetch()) {
			$map[$row['session_hash']] = $row['display_name'];
		}
		$result->closeCursor();

		return $map;
	}
}
