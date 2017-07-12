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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;

class CallController extends OCSController {
	/** @var string */
	private $userId;
	/** @var ISession */
	private $session;
	/** @var Manager */
	private $manager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param ISession $session
	 * @param ILogger $logger
	 * @param Manager $manager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IUserManager $userManager,
								ISession $session,
								ILogger $logger,
								Manager $manager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->session = $session;
		$this->manager = $manager;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getPeersForCall($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var array[] $participants */
		$participants = $room->getParticipants(time() - 30);
		$result = [];
		foreach ($participants['users'] as $participant => $data) {
			if ($data['sessionId'] === '0') {
				// User left the room
				continue;
			}

			$result[] = [
				'userId' => $participant,
				'token' => $token,
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}

		foreach ($participants['guests'] as $data) {
			$result[] = [
				'userId' => '',
				'token' => $token,
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function joinCall($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId !== null) {
			$sessionIds = $this->manager->getSessionIdsForUser($this->userId);
			$newSessionId = $room->enterRoomAsUser($this->userId);

			if (!empty($sessionIds)) {
				$this->manager->deleteMessagesForSessionIds($sessionIds);
			}
		} else {
			$newSessionId = $room->enterRoomAsGuest();
		}

		$this->session->set('spreed-session', $newSessionId);
		$room->ping($this->userId, $newSessionId, time());

		return new DataResponse([
			'sessionId' => $newSessionId,
		]);
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function pingCall($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$sessionId = $this->session->get('spreed-session');
		$room->ping($this->userId, $sessionId, time());

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function leaveCall($token) {
		if ($this->userId !== null) {
			// TODO: Currently we ignore $token, should be fixed at some point
			$this->manager->disconnectUserFromAllRooms($this->userId);
		} else {
			$sessionId = $this->session->get('spreed-session');
			$this->manager->removeSessionFromAllRooms($sessionId);
		}

		$this->session->remove('spreed-session');
		return new DataResponse();
	}

}
