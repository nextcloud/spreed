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
use OCA\Spreed\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;

class CallController extends OCSController {
	/** @var string */
	private $userId;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var ISession */
	private $session;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param ISession $session
	 * @param ILogger $logger
	 * @param Manager $manager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IL10N $l10n,
								IUserManager $userManager,
								ISession $session,
								ILogger $logger,
								Manager $manager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->session = $session;
		$this->logger = $logger;
		$this->manager = $manager;
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getRooms() {
		$rooms = $this->manager->getRoomsForParticipant($this->userId);

		$return = [];
		foreach ($rooms as $room) {
			try {
				$return[] = $this->formatRoom($room);
			} catch (RoomNotFoundException $e) {
			}
		}

		return new DataResponse($return);
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getRoom($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			return new DataResponse($this->formatRoom($room));
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param Room $room
	 * @return array
	 * @throws RoomNotFoundException
	 */
	protected function formatRoom(Room $room) {
		// Sort by lastPing
		/** @var array[] $participants */
		$participants = $room->getParticipants();
		$sortParticipants = function(array $participant1, array $participant2) {
			return $participant2['lastPing'] - $participant1['lastPing'];
		};
		uasort($participants['users'], $sortParticipants);
		uasort($participants['guests'], $sortParticipants);

		$participantList = [];
		foreach ($participants['users'] as $participant => $lastPing) {
			$user = $this->userManager->get($participant);
			if ($user instanceof IUser) {
				$participantList[$participant] = $user->getDisplayName();
			}
		}

		$roomData = [
			'id' => $room->getId(),
			'token' => $room->getToken(),
			'type' => $room->getType(),
			'name' => $room->getName(),
			'displayName' => $room->getName(),
			'isNameEditable' => $room->getType() !== Room::ONE_TO_ONE_CALL,
			'count' => $room->getNumberOfParticipants(time() - 30),
			'lastPing' => isset($participants['users'][$this->userId]['lastPing']) ? $participants['users'][$this->userId]['lastPing'] : 0,
			'sessionId' => isset($participants['users'][$this->userId]['sessionId']) ? $participants['users'][$this->userId]['sessionId'] : '0',
			'participants' => $participantList,
		];

		$activeGuests = array_filter($participants['guests'], function($data) {
			return $data['lastPing'] > time() - 30;
		});

		$numActiveGuests = count($activeGuests);
		if ($numActiveGuests !== count($participants['guests'])) {
			$room->cleanGuestParticipants();
		}

		if ($this->userId !== null) {
			unset($participantList[$this->userId]);
			$numOtherParticipants = count($participantList);
			$numGuestParticipants = $numActiveGuests;
		} else {
			$numOtherParticipants = count($participantList);
			$numGuestParticipants = $numActiveGuests - 1;
		}

		$guestString = '';
		switch ($room->getType()) {
			case Room::ONE_TO_ONE_CALL:
				// As name of the room use the name of the other person participating
				if ($numOtherParticipants === 1) {
					// Only one other participant
					reset($participantList);
					$roomData['name'] = key($participantList);
					$roomData['displayName'] = $participantList[$roomData['name']];
				} else {
					// Invalid user count, there must be exactly 2 users in each one2one room
					$this->logger->warning('one2one room found with invalid participant count. Leaving room for everyone', [
						'app' => 'spreed',
					]);
					$room->deleteRoom();
				}
				break;

			/** @noinspection PhpMissingBreakStatementInspection */
			case Room::PUBLIC_CALL:
				if ($this->userId === null && $numGuestParticipants) {
					$guestString = $this->l10n->n('%n other guest', '%n other guests', $numGuestParticipants);
				} else if ($numGuestParticipants) {
					$guestString = $this->l10n->n('%n guest', '%n guests', $numGuestParticipants);
				}

				// no break;

			case Room::GROUP_CALL:
				if ($room->getName() === '') {
					// As name of the room use the names of the other participants
					if ($this->userId === null) {
						$participantList[] = $this->l10n->t('You');
					} else if ($numOtherParticipants === 0) {
						$participantList = [$this->l10n->t('You')];
					}

					if ($guestString !== '') {
						$participantList[] = $guestString;
					}

					$roomData['displayName'] = implode($this->l10n->t(', '), $participantList);
				}
				break;

			default:
				// Invalid room type
				$this->logger->warning('Invalid room type found. Leaving room for everyone', [
					'app' => 'spreed',
				]);
				$room->deleteRoom();
				throw new RoomNotFoundException();
		}

		$roomData['guestList'] = $guestString;

		return $roomData;
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getPeersInRoom($token) {
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
	 * @return DataResponse
	 */
	public function leaveCall() {
		if ($this->userId !== null) {
			$this->manager->disconnectUserFromAllRooms($this->userId);
		} else {
			$sessionId = $this->session->get('spreed-session');
			$this->manager->removeSessionFromAllRooms($sessionId);
		}

		$this->session->remove('spreed-session');
		return new DataResponse();
	}

}
