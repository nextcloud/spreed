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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;

use OCA\Spreed\Util;

class SignallingController extends Controller {
	/** @var IConfig */
	private $config;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var string */
	private $userId;
	private static $endOfCandidatesFor = [];


	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IDBConnection $connection
	 * @param string $UserId
	 */
	public function __construct($appName,
								IRequest $request,
								IConfig $config,
								IDBConnection $connection,
								$UserId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->dbConnection = $connection;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $messages
	 * @return JSONResponse
	 */
	public function signalling($messages) {
		$response = [];
		$messages = json_decode($messages, true);
		foreach($messages as $message) {
			$ev = $message['ev'];
			$fn = $message['fn'];
			switch ($ev) {
				case 'message':
					if (!is_string($fn)) {
						break;
					}
					$decodedMessage = json_decode($fn, true);
					$decodedMessage['from'] = $this->userId;
					$this->dbConnection->beginTransaction();
					$qb = $this->dbConnection->getQueryBuilder();
					$qb->insert('spreedme_messages')
						->values(
							[
								'recipient' => $qb->createNamedParameter($decodedMessage['to']),
								'timestamp' => $qb->createNamedParameter(time()),
								'object' => $qb->createNamedParameter(json_encode($decodedMessage)),
							]
						)
						->execute();
					$this->dbConnection->commit();
					$this->dbConnection->close();

					break;
				case 'stunservers':
					$response = [];
					$stunServer = Util::getStunServer($this->config);
					if ($stunServer) {
						array_push($response, [
							'url' => 'stun:' . $stunServer,
						]);
					}
					break;
			}
		}

		return new JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function pullMessages() {
		set_time_limit(0);
		$eventSource = \OC::$server->createEventSource();

		while(true) {
			// Check if the connection is still active, if not: Kill all existing
			// messages and end the event source
			$qb = $this->dbConnection->getQueryBuilder();
			$currentRoom = $qb->select('*')
				->from('spreedme_room_participants')
				->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
				->andWhere($qb->expr()->gt('lastPing', $qb->createNamedParameter(time() - 30)))
				->execute()
				->fetchAll();
			if ($currentRoom === []) {
				$eventSource->close();

				// Delete all messages for the recipient
				$qb = $this->dbConnection->getQueryBuilder();
				$qb->delete('spreedme_messages')
					->where($qb->expr()->eq('recipient', $qb->createNamedParameter($this->userId)))
					->execute();
				break;
			}

			// Send list to client of connected users in the current room
			$qb = $this->dbConnection->getQueryBuilder();
			$usersInRoom = $qb->select('*')
				->from('spreedme_room_participants')
				->where($qb->expr()->eq('roomId', $qb->createNamedParameter($currentRoom[0]['roomId'])))
				->andWhere($qb->expr()->gt('lastPing', $qb->createNamedParameter(time() - 30)))
				->execute()
				->fetchAll();
			$eventSource->send('usersInRoom', $usersInRoom);

			// Query all messages and send them to the user
			$qb = $this->dbConnection->getQueryBuilder();
			$results = $qb->select('*')
				->from('spreedme_messages')
				->where('recipient = :recipient')
				->where($qb->expr()->eq('recipient', $qb->createNamedParameter($this->userId)))
				->execute()
				->fetchAll();

			foreach($results as $result) {
				$qb = $this->dbConnection->getQueryBuilder();
				$qb->delete('spreedme_messages')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($result['id'])))
					->execute();

				$object = json_decode($result['object'], true);

				$send = true;
				if ($object['type'] === 'endOfCandidates') {
					self::$endOfCandidatesFor[$object['from']] = true;
				}
				if ($object['type'] === 'candidate' && isset(self::$endOfCandidatesFor[$object['from']])) {
					$send = false;
				}
				if ($send === true) {
					$eventSource->send('message', $result['object']);
				}
			}
			$this->dbConnection->close();

			sleep(1);
		}
		exit();
	}

}
