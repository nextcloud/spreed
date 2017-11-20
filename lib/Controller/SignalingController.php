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
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\Signaling\Messages;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;

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
	/** @var IUserManager */
	private $userManager;

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
								IUserManager $userManager,
								$UserId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->session = $session;
		$this->dbConnection = $connection;
		$this->manager = $manager;
		$this->messages = $messages;
		$this->userManager = $userManager;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Only available for logged in users because guests can not use the apps
	 * right now.
	 *
	 * @return DataResponse
	 */
	public function getSettings() {
		return new DataResponse($this->config->getSettings($this->userId));
	}

	/**
	 * @PublicPage
	 *
	 * @param string $messages
	 * @return DataResponse
	 */
	public function signaling($messages) {
		$signaling = $this->config->getSignalingServers();
		if (!empty($signaling)) {
			return new DataResponse('Internal signaling disabled.', Http::STATUS_BAD_REQUEST);
		}

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
			}
		}

		return new DataResponse($response);
	}

	/**
	 * @PublicPage
	 * @return DataResponse
	 */
	public function pullMessages() {
		$signaling = $this->config->getSignalingServers();
		if (!empty($signaling)) {
			return new DataResponse('Internal signaling disabled.', Http::STATUS_BAD_REQUEST);
		}

		$data = [];
		$seconds = 30;
		$sessionId = '';

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

		try {
			// Add an update of the room participants at the end of the waiting
			$room = $this->manager->getRoomForSession($this->userId, $sessionId);
			$data[] = ['type' => 'usersInRoom', 'data' => $this->getUsersInRoom($room)];
		} catch (RoomNotFoundException $e) {
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
				// User is not active
				continue;
			}

			$usersInRoom[] = [
				'userId' => $participant,
				'roomId' => $room->getId(),
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
				'inCall' => $data['inCall'],
			];
		}

		foreach ($participants['guests'] as $data) {
			$usersInRoom[] = [
				'userId' => '',
				'roomId' => $room->getId(),
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
				'inCall' => $data['inCall'],
			];
		}

		return $usersInRoom;
	}

	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The backends are sending the custom header "Spreed-Signaling-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Spreed-Signaling-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @return bool
	 */
	private function validateBackendRequest($data) {
		$random = $_SERVER['HTTP_SPREED_SIGNALING_RANDOM'];
		if (empty($random) || strlen($random) < 32) {
			return false;
		}
		$checksum = $_SERVER['HTTP_SPREED_SIGNALING_CHECKSUM'];
		if (empty($checksum)) {
			return false;
		}
		$hash = hash_hmac('sha256', $random . $data, $this->config->getSignalingSecret());
		return hash_equals($hash, strtolower($checksum));
	}

	/**
	 * Backend API to query information required for standalone signaling
	 * servers.
	 *
	 * See sections "Backend validation" in
	 * https://github.com/nextcloud/spreed/wiki/Spreed-Signaling-API
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return JSONResponse
	 */
	public function backend() {
		$json = file_get_contents('php://input');
		if (!$this->validateBackendRequest($json)) {
			return new JSONResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_request',
					'message' => 'The request could not be authenticated.',
				],
			]);
		}

		$message = json_decode($json, true);
		switch ($message['type']) {
			case 'auth':
				// Query authentication information about a user.
				return $this->backendAuth($message['auth']);
			case 'room':
				// Query information about a room.
				return $this->backendRoom($message['room']);
			case 'ping':
				// Ping sessions connected to a room.
				return $this->backendPing($message['ping']);
			default:
				return new JSONResponse([
					'type' => 'error',
					'error' => [
						'code' => 'unknown_type',
						'message' => 'The given type ' . print_r($message, true) . ' is not supported.',
					],
				]);
		}
	}

	private function backendAuth($auth) {
		$params = $auth['params'];
		$userId = $params['userid'];
		if (!$this->config->validateSignalingTicket($userId, $params['ticket'])) {
			return new JSONResponse([
				'type' => 'error',
				'error' => [
					'code' => 'invalid_ticket',
					'message' => 'The given ticket is not valid for this user.',
				],
			]);
		}

		if (!empty($userId)) {
			$user = $this->userManager->get($userId);
			if (!$user instanceof IUser) {
				return new JSONResponse([
					'type' => 'error',
					'error' => [
						'code' => 'no_such_user',
						'message' => 'The given user does not exist.',
					],
				]);
			}
		}

		$response = [
			'type' => 'auth',
			'auth' => [
				'version' => '1.0',
			],
		];
		if (!empty($userId)) {
			$response['auth']['userid'] = $user->getUID();
			$response['auth']['user'] = [
				'displayname' => $user->getDisplayName(),
			];
		}
		return new JSONResponse($response);
	}

	private function backendRoom($roomRequest) {
		$roomId = $roomRequest['roomid'];
		$userId = $roomRequest['userid'];
		$sessionId = $roomRequest['sessionid'];

		try {
			$room = $this->manager->getRoomByToken($roomId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'The user is not invited to this room.',
				],
			]);
		}

		$participant = null;
		if (!empty($userId)) {
			// User trying to join room.
			try {
				$participant = $room->getParticipant($userId);
			} catch (ParticipantNotFoundException $e) {
				// Ignore, will check for public rooms below.
			}
		}

		if (empty($participant)) {
			// User was not invited to the room, check for access to public room.
			try {
				$participant = $room->getParticipantBySession($sessionId);
			} catch (ParticipantNotFoundException $e) {
				// Return generic error to avoid leaking which rooms exist.
				return new JSONResponse([
					'type' => 'error',
					'error' => [
						'code' => 'no_such_room',
						'message' => 'The user is not invited to this room.',
					],
				]);
			}
		}

		// Rooms get sorted by last ping time for users, so make sure to
		// update when a user joins a room.
		$room->ping($userId, $sessionId, time());

		$response = [
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $room->getToken(),
				'properties' => [
					'name' => $room->getName(),
					'type' => $room->getType(),
				],
			],
		];
		return new JSONResponse($response);
	}

	private function backendPing($request) {
		try {
			$room = $this->manager->getRoomByToken($request['roomid']);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([
				'type' => 'error',
				'error' => [
					'code' => 'no_such_room',
					'message' => 'No such room.',
				],
			]);
		}

		$now = time();
		foreach ($request['entries'] as $entry) {
			if (array_key_exists('userid', $entry)) {
				$room->ping($entry['userid'], $entry['sessionid'], $now);
			} else {
				$room->ping('', $entry['sessionid'], $now);
			}
		}

		$response = [
			'type' => 'room',
			'room' => [
				'version' => '1.0',
				'roomid' => $room->getToken(),
			],
		];
		return new JSONResponse($response);
	}

}
