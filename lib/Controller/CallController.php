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

use OCA\Spreed\Exceptions\InvalidPasswordException;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Signaling\Messages;
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
	/** @var Messages */
	private $messages;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param ISession $session
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param Messages $messages
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IUserManager $userManager,
								ISession $session,
								ILogger $logger,
								Manager $manager,
								Messages $messages) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->session = $session;
		$this->manager = $manager;
		$this->messages = $messages;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getPeersForCall($token) {
		try {
			$room = $this->manager->getRoomForSession($this->userId, $this->session->get('spreed-session'));
		} catch (RoomNotFoundException $e) {
			if ($this->userId === null) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			// For logged in users we search for rooms where they are real participants
			try {
				$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
				$room->getParticipant($this->userId);
			} catch (RoomNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		if ($room->getToken() !== $token) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var array[] $participants */
		$participants = $room->getParticipants(time() - 30);
		$result = [];
		foreach ($participants['users'] as $participant => $data) {
			if ($data['sessionId'] === '0' || !$data['inCall']) {
				// User is not active in call
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
			if (!$this->session->exists('spreed-session')) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
			$sessionId = $this->session->get('spreed-session');
		} else {
			try {
				$participant = $room->getParticipant($this->userId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$sessionId = $participant->getSessionId();
			if ($sessionId === '0') {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		$room->changeInCall($sessionId, true);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function pingCall($token) {
		if (!$this->session->exists('spreed-session')) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

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
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId !== null) {
			if (!$this->session->exists('spreed-session')) {
				return new DataResponse();
			}
			$sessionId = $this->session->get('spreed-session');
		} else {
			try {
				$participant = $room->getParticipant($this->userId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$sessionId = $participant->getSessionId();
			if ($sessionId === '0') {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		$room->changeInCall($sessionId, false);

		return new DataResponse();
	}

}
