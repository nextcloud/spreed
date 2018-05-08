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


use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class GuestManager {

	/** @var IDBConnection */
	protected $connection;

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	public function __construct(IDBConnection $connection, EventDispatcherInterface $dispatcher) {
		$this->connection = $connection;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @param Room $room
	 * @param string $sessionId
	 * @param string $displayName
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function updateName(Room $room, $sessionId, $displayName) {
		$sessionHash = sha1($sessionId);
		$dispatchEvent = true;

		try {
			$oldName = $this->getNameBySessionHash($sessionHash);

			if ($oldName !== $displayName) {
				$query = $this->connection->getQueryBuilder();
				$query->update('talk_guests')
					->set('display_name', $query->createNamedParameter($displayName))
					->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));
				$query->execute();
			} else {
				$dispatchEvent = false;
			}
		} catch (ParticipantNotFoundException $e) {
			$this->connection->insertIfNotExist('*PREFIX*talk_guests', [
				'session_hash' => $sessionHash,
				'display_name' => $displayName,
			], ['session_hash']);
		}


		if ($dispatchEvent) {
			$this->dispatcher->dispatch(self::class . '::updateName', new GenericEvent($room, [
				'sessionId' => $sessionId,
				'newName' => $displayName,
			]));
		}
	}

	/**
	 * @param string $sessionHash
	 * @return string
	 * @throws ParticipantNotFoundException
	 */
	public function getNameBySessionHash($sessionHash) {
		$query = $this->connection->getQueryBuilder();
		$query->select('display_name')
			->from('talk_guests')
			->where($query->expr()->eq('session_hash', $query->createNamedParameter($sessionHash)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (isset($row['display_name'])) {
			return $row['display_name'];
		}

		throw new ParticipantNotFoundException();
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
