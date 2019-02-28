<?php
declare(strict_types=1);
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

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

class CallController extends OCSController {
	/** @var string */
	private $userId;
	/** @var TalkSession */
	private $session;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var Manager */
	private $manager;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								TalkSession $session,
								ITimeFactory $timeFactory,
								Manager $manager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->session = $session;
		$this->timeFactory = $timeFactory;
		$this->manager = $manager;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getPeersForCall(string $token): DataResponse {
		try {
			$room = $this->manager->getRoomForSession($this->userId, $this->session->getSessionForRoom($token));
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

		$result = [];
		$participants = $room->getParticipants($this->timeFactory->getTime() - 30);
		foreach ($participants as $participant) {
			if ($participant->getSessionId() === '0' || $participant->getInCallFlags() === Participant::FLAG_DISCONNECTED) {
				// User is not active in call
				continue;
			}

			$result[] = [
				'userId' => $participant->getUser(),
				'token' => $token,
				'lastPing' => $participant->getLastPing(),
				'sessionId' => $participant->getSessionId(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @param int|null $flags
	 * @return DataResponse
	 */
	public function joinCall(string $token, ?int $flags): DataResponse {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId === null) {
			if ($this->session->getSessionForRoom($token) === null) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			try {
				$participant = $room->getParticipantBySession($this->session->getSessionForRoom($token));
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		} else {
			try {
				$participant = $room->getParticipant($this->userId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		$room->ensureOneToOneRoomIsFilled();

		$sessionId = $participant->getSessionId();
		if ($sessionId === '0') {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($flags === null) {
			// Default flags: user is in room with audio/video.
			$flags = Participant::FLAG_IN_CALL | Participant::FLAG_WITH_AUDIO | Participant::FLAG_WITH_VIDEO;
		}

		$room->changeInCall($sessionId, $flags);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function leaveCall(string $token): DataResponse {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId === null) {
			if ($this->session->getSessionForRoom($token) === null) {
				return new DataResponse();
			}

			try {
				$participant = $room->getParticipantBySession($this->session->getSessionForRoom($token));
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		} else {
			try {
				$participant = $room->getParticipant($this->userId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}

		$sessionId = $participant->getSessionId();
		if ($sessionId === '0') {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$room->changeInCall($sessionId, Participant::FLAG_DISCONNECTED);

		return new DataResponse();
	}

}
