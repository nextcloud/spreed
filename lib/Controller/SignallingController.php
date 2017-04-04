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

use OCA\Spreed\Config;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;

class SignallingController extends Controller {
	/** @var Config */
	private $config;
	/** @var ISession */
	private $session;
	/** @var Manager */
	private $manager;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var string */
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param Config $config
	 * @param ISession $session
	 * @param Manager $manager
	 * @param IDBConnection $connection
	 * @param string $UserId
	 */
	public function __construct($appName,
								IRequest $request,
								Config $config,
								ISession $session,
								Manager $manager,
								IDBConnection $connection,
								$UserId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->session = $session;
		$this->dbConnection = $connection;
		$this->manager = $manager;
		$this->userId = $UserId;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $messages
	 * @return JSONResponse
	 */
	public function signalling($messages) {
		$response = [];
		$messages = json_decode($messages, true);
		foreach($messages as $message) {
			$ev = $message['ev'];
			switch ($ev) {
				case 'message':
					$fn = $message['fn'];
					if (!is_string($fn)) {
						break;
					}
					$decodedMessage = json_decode($fn, true);
					if ($message['sessionId'] !== $this->session->get('spreed-session')) {
						break;
					}
					$decodedMessage['from'] = $message['sessionId'];
					$this->dbConnection->beginTransaction();
					$qb = $this->dbConnection->getQueryBuilder();
					$qb->insert('spreedme_messages')
						->values(
							[
								'sender' => $qb->createNamedParameter($message['sessionId']),
								'recipient' => $qb->createNamedParameter($decodedMessage['to']),
								'timestamp' => $qb->createNamedParameter(time()),
								'object' => $qb->createNamedParameter(json_encode($decodedMessage)),
								'sessionId' => $qb->createNamedParameter($message['sessionId']),
							]
						)
						->execute();
					$this->dbConnection->commit();
					$this->dbConnection->close();

					break;
				case 'stunservers':
					$response = [];
					$stunServer = $this->config->getStunServer();
					if ($stunServer) {
						$response[] = [
							'url' => 'stun:' . $stunServer,
						];
					}
					break;
				case 'turnservers':
					$response = [];
					$turnSettings = $this->config->getTurnSettings();
					if (!empty($turnSettings['server'])) {
						$protocols = explode(',', $turnSettings['protocols']);
						foreach ($protocols as $proto) {
							$response[] = [
								'url' => ['turn:' . $turnSettings['server'] . '?transport=' . $proto],
								'urls' => ['turn:' . $turnSettings['server'] . '?transport=' . $proto],
								'username' => $turnSettings['username'],
								'credential' => $turnSettings['password'],
							];
						}
					}
					break;
			}
		}

		return new JSONResponse($response);
	}

	/**
	 * @PublicPage
	 */
	public function pullMessages() {
		set_time_limit(0);
		$eventSource = \OC::$server->createEventSource();

		while(true) {
			if ($this->userId === null) {
				$sessionId = $this->session->get('spreed-session');

				if (empty($sessionId)) {
					// User is not active anywhere
					$eventSource->send('usersInRoom', []);
					$currentParticipant = false;
					break;
				} else {
					$qb = $this->dbConnection->getQueryBuilder();
					$qb->select('*')
						->from('spreedme_room_participants')
						->where($qb->expr()->eq('sessionId', $qb->createNamedParameter($sessionId)))
						->andWhere($qb->expr()->eq('userId', $qb->createNamedParameter((string)$this->userId)));
					$result = $qb->execute();
					$currentParticipant = $result->fetch();
					$result->closeCursor();
				}
			} else {
				$qb = $this->dbConnection->getQueryBuilder();
				$qb->select('*')
					->from('spreedme_room_participants')
					->where($qb->expr()->neq('sessionId', $qb->createNamedParameter('0')))
					->andWhere($qb->expr()->eq('userId', $qb->createNamedParameter((string)$this->userId)))
					->orderBy('lastPing', 'DESC')
					->setMaxResults(1);
				$result = $qb->execute();
				$currentParticipant = $result->fetch();
				$result->closeCursor();

				if ($currentParticipant === false) {
					$sessionId = null;
				} else {
					$sessionId = $currentParticipant['sessionId'];
				}
			}

			if ($sessionId === null) {
				// User is not active anywhere
				$eventSource->send('usersInRoom', []);
			} else {
				// Check if the connection is still active, if not: Kill all existing
				// messages and end the event source
				if ($currentParticipant) {
					try {
						$room = $this->manager->getRoomForParticipant($currentParticipant['roomId'], $this->userId);
						$eventSource->send('usersInRoom', $this->getUsersInRoom($room));
					} catch (RoomNotFoundException $e) {
						$eventSource->send('usersInRoom', []);
					}
				} else {
					$eventSource->send('usersInRoom', []);
				}

				// Query all messages and send them to the user
				$qb = $this->dbConnection->getQueryBuilder();
				$qb->select('*')
					->from('spreedme_messages')
					->where($qb->expr()->eq('recipient', $qb->createNamedParameter($sessionId)));
				$result = $qb->execute();
				$rows = $result->fetchAll();
				$result->closeCursor();

				foreach($rows as $row) {
					$qb = $this->dbConnection->getQueryBuilder();
					$qb->delete('spreedme_messages')
						->where($qb->expr()->eq('id', $qb->createNamedParameter($row['id'])));
					$qb->execute();
					$eventSource->send('message', $row['object']);
				}
			}

			$this->dbConnection->close();
			sleep(1);
		}

		$eventSource->close();
		exit;
	}

	/**
	 * @param Room $room
	 * @return array[]
	 */
	protected function getUsersInRoom(Room $room) {
		$usersInRoom = [];
		$participants = $room->getParticipants(time() - 30);

		foreach ($participants['users'] as $participant => $data) {
			if ($data['sessionId'] === '0') {
				// Use left the room
				continue;
			}

			$usersInRoom[] = [
				'userId' => $participant,
				'roomId' => $room->getId(),
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}

		foreach ($participants['guests'] as $data) {
			$usersInRoom[] = [
				'userId' => '',
				'roomId' => $room->getId(),
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}

		return $usersInRoom;
	}
}
