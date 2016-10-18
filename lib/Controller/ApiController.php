<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Spreed\Controller;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;

class ApiController extends Controller {
	/** @var string */
	private $userId;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var IL10N */
	private $l10n;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IDBConnection $dbConnection
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IDBConnection $dbConnection,
								IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
		$this->l10n = $l10n;
	}

	/**
	 * @param int $roomId
	 * @return array
	 */
	private function getActivePeers($roomId) {
		$qb = $this->dbConnection->getQueryBuilder();
		return $qb->select('*')
			->from('spreedme_room_participants')
			->where($qb->expr()->eq('roomId', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->gt('lastPing', $qb->createNamedParameter(time() - 10)))
			->execute()
			->fetchAll();
	}

	/**
	 * @return array
	 */
	private function getAllRooms() {
		$qb = $this->dbConnection->getQueryBuilder();
		return $qb->select('*')
			->from('spreedme_rooms')
			->execute()
			->fetchAll();
	}

	/**
	 * @param int $roomId
	 */
	private function deleteRoom($roomId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('spreedme_rooms')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($roomId)))
			->execute();
	}

	/**
	 * Checks for all empty rooms and deletes them
	 */
	private function deleteEmptyRooms() {
		$rooms = $this->getAllRooms();
		foreach($rooms as $room) {
			$activePeers = $this->getActivePeers($room['id']);
			if(count($activePeers) === 0) {
				//$this->deleteRoom($room['id']);
			}
		}
	}

	/**
	 * Get all currently existent rooms
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function getRooms() {
		$this->deleteEmptyRooms();

		$qb = $this->dbConnection->getQueryBuilder();
		$rooms = $qb->select('*')
			->from('spreedme_rooms')
			->execute()
			->fetchAll();
		foreach($rooms as $key => $room) {
			$rooms[$key]['count'] = count($this->getActivePeers($room['id']));
		}
		return new JSONResponse($rooms);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $name
	 * @return JSONResponse
	 */
	public function createRoom($name) {
		$query = $this->dbConnection->getQueryBuilder();
		$query->insert('spreedme_rooms')
			->values(
				[
					'name' => $query->createNamedParameter($name),
				]
			);

		try {
			$query->execute();
		} catch (UniqueConstraintViolationException $e) {
			return new JSONResponse(
				[
					'message' => $this->l10n->t('A room with this name already exists.'),
				], Http::STATUS_CONFLICT
			);
		}

		return new JSONResponse([
				'id' => $query->getLastInsertId(),
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function joinRoom($roomId) {
		$qb = $this->dbConnection->getQueryBuilder();

		// Remove from any current room that the participant is in
		$qb->delete('spreedme_room_participants')
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->execute();

		// Add to new room
		$qb->insert('spreedme_room_participants')
			->values(
				[
					'userId' => $qb->createNamedParameter($this->userId),
					'roomId' => $qb->createNamedParameter($roomId),
					'lastPing' => $qb->createNamedParameter(time()),
				]
			)
			->execute();
		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function getPeersInRoom($roomId) {
		return new JSONResponse($this->getActivePeers($roomId));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $currentRoom
	 * @return JSONResponse
	 */
	public function ping($currentRoom) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('spreedme_room_participants')
			->set('lastPing', $qb->createNamedParameter(time()))
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('roomId', $qb->createNamedParameter($currentRoom)))
			->execute();
		return new JSONResponse();
	}
}
