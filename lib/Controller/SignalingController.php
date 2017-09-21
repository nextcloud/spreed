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
use OCA\Spreed\Signaling\Messages;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;

class SignalingController extends OCSController {
	/** @var Config */
	private $config;
	/** @var ISession */
	private $session;
	/** @var Manager */
	private $manager;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var Messages */
	private $messages;
	/** @var string|null */
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param Config $config
	 * @param ISession $session
	 * @param Manager $manager
	 * @param IDBConnection $connection
	 * @param Messages $messages
	 * @param string $UserId
	 */
	public function __construct($appName,
								IRequest $request,
								Config $config,
								ISession $session,
								Manager $manager,
								IDBConnection $connection,
								Messages $messages,
								$UserId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->session = $session;
		$this->dbConnection = $connection;
		$this->manager = $manager;
		$this->messages = $messages;
		$this->userId = $UserId;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $messages
	 * @return DataResponse
	 */
	public function signaling($messages) {
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

					$this->messages->addMessage($message['sessionId'], $decodedMessage['to'], json_encode($decodedMessage));

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

		return new DataResponse($response);
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 */
	public function pullMessages() {
		$data = [];
		$seconds = 30;

		while ($seconds > 0) {
			if ($this->userId === null) {
				$sessionId = $this->session->get('spreed-session');
			} else {
				$sessionId = $this->manager->getCurrentSessionId($this->userId);
			}

			if ($sessionId === null) {
				// User is not active anywhere
				return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
			}

			// Query all messages and send them to the user
			$data = $this->messages->getAndDeleteMessages($sessionId);
			$messageCount = count($data);
			$data = array_filter($data, function($message) {
				return $message['data'] !== 'refresh-participant-list';
			});

			if ($messageCount !== count($data)) {
				try {
					$room = $this->manager->getRoomForSession($this->userId, $sessionId);
					$data[] = ['type' => 'usersInRoom', 'data' => $this->getUsersInRoom($room)];
				} catch (RoomNotFoundException $e) {
					return new DataResponse([['type' => 'usersInRoom', 'data' => []]], Http::STATUS_NOT_FOUND);
				}
			}

			$this->dbConnection->close();
			if (empty($data)) {
				$seconds--;
			} else {
				break;
			}
			sleep(1);
		}

		return new DataResponse($data);
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
