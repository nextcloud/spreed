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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use OCP\Security\ISecureRandom;

class ApiController extends Controller {
	/** @var string */
	private $userId;
	/** @var IDBConnection */
	private $dbConnection;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var IManager */
	private $notificationManager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IDBConnection $dbConnection
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ISecureRandom $secureRandom
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param IManager $notificationManager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IDBConnection $dbConnection,
								IL10N $l10n,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ISecureRandom $secureRandom,
								ILogger $logger,
								Manager $manager,
								IManager $notificationManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->secureRandom = $secureRandom;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->notificationManager = $notificationManager;
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @NoAdminRequired
	 *
	 * @throws \Exception
	 * @return JSONResponse
	 */
	public function getRooms() {
		$rooms = $this->manager->getRoomsForParticipant($this->userId);

		$return = [];
		foreach ($rooms as $room) {
			// Sort by lastPing
			$participantPings = $room->getParticipants();
			uasort($participantPings, function(array $participant1, array $participant2) {
				if ($participant1['lastPing'] === $participant2['lastPing']) {
					return 0;
				}
				return ($participant1['lastPing'] > $participant2['lastPing']) ? -1 : 1;
			});

			$participantList = [];
			foreach ($participantPings as $participant => $lastPing) {
				$user = $this->userManager->get($participant);
				if ($user instanceof IUser) {
					$participantList[$participant] = $user->getDisplayName();
				}
			}

			$roomData = [
				'id' => $room->getId(),
				'type' => $room->getType(),
				'name' => $room->getName(),
				'displayName' => $room->getName(),
				'count' => $room->getNumberOfParticipants(time() - 10),
				'lastPing' => isset($participantPings[$this->userId]['lastPing']) ? $participantPings[$this->userId]['lastPing'] : 0,
				'sessionId' => isset($participantPings[$this->userId]['sessionId']) ? $participantPings[$this->userId]['sessionId'] : 0,
				'participants' => $participantList,
			];

			unset($participantList[$this->userId]);
			$numOtherParticipants = sizeof($participantList);

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

				case Room::GROUP_CALL:
					/// As name of the room use the names of the other participants
					if ($numOtherParticipants === 0) {
						// Only you
						$roomData['displayName'] = $this->l10n->t('You');
					} else {
						$roomData['displayName'] = implode($this->l10n->t(', '), $participantList);
					}
					break;

				default:
					// Invalid room type
					$this->logger->warning('Invalid room type found. Leaving room for everyone', [
						'app' => 'spreed',
					]);
					$room->deleteRoom();
			}

			$return[] = $roomData;
		}

		return new JSONResponse($return);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function getPeersInRoom($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$result = [];
		foreach ($room->getParticipants(time() - 10) as $participant => $data) {
			$result[] = [
				'userId' => $participant,
				'roomId' => $roomId,
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}
		return new JSONResponse($result);
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetUserName
	 * @return JSONResponse
	 */
	public function createOneToOneVideoCallRoom($targetUserName) {
		// Get the user
		$targetUser = $this->userManager->get($targetUserName);
		$currentUser = $this->userManager->get($this->userId);
		if(!($targetUser instanceof IUser)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// If room exists: Reuse that one, otherwise create a new one.
		try {
			$room = $this->manager->getOne2OneRoom($this->userId, $targetUser->getUID());
			return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_OK);
		} catch (RoomNotFoundException $e) {
			$room = $this->manager->createRoom(Room::ONE_TO_ONE_CALL, $this->secureRandom->generate(12));
			$room->addUser($currentUser);
			$room->addUser($targetUser);

			$notification = $this->notificationManager->createNotification();
			$notification->setApp('spreed')
				->setUser($targetUser->getUID())
				->setDateTime(new \DateTime())
				->setObject('room', $room->getId())
				->setSubject('invitation', [$this->userId]);
			$this->notificationManager->notify($notification);

			return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_CREATED);
		}
	}

	/**
	 * Initiates a group video call from the selected group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetGroupName
	 * @return JSONResponse
	 */
	public function createGroupVideoCallRoom($targetGroupName) {
		$targetGroup = $this->groupManager->get($targetGroupName);
		$currentUser = $this->userManager->get($this->userId);

		if(!($targetGroup instanceof IGroup)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$usersInGroup = $targetGroup->getUsers();
		// If the user who is creating this call is not part of this group add them
		if (!($targetGroup->inGroup($currentUser))) {
			$usersInGroup[] = $currentUser;
		}

		// Create the room
		$room = $this->manager->createRoom(Room::GROUP_CALL, $targetGroup->getGID());
		foreach ($usersInGroup as $user) {
			$room->addUser($user);
		}

		return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @param string $newParticipant
	 * @return JSONResponse
	 */
	public function addParticipantToRoom($roomId, $newParticipant) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$participants = $room->getParticipants();
		if (isset($participants[$newParticipant])) {
			return new JSONResponse([]);
		}

		$newUser = $this->userManager->get($newParticipant);
		if (!$newUser instanceof IUser) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			// In case a user is added to a one2one call, we create a new group call and add the participants manually
			$room = $this->manager->createRoom(Room::GROUP_CALL, $this->secureRandom->generate(12));
			foreach ($participants as $participant => $lastPing) {
				$user = $this->userManager->get($participant);
				if ($user instanceof IUser) {
					$room->addUser($user);
				}
			}
			return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_CREATED);
		}

		$room->addUser($newUser);
		return new JSONResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function leaveRoom($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
			$room->deleteRoom();
		} else {
			$currentUser = $this->userManager->get($this->userId);
			$room->removeUser($currentUser);
		}

		return new JSONResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function ping($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('spreed')
			->setUser($this->userId)
			->setObject('room', (string) $roomId);
		$this->notificationManager->markProcessed($notification);

		$room->ping($this->userId, time());
		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function joinRoom($roomId) {
		// Delete all messages from or to the current user
		$qb = $this->dbConnection->getQueryBuilder();
		$sessionIds = $qb->select('sessionId')
			->from('spreedme_room_participants')
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->execute()
			->fetchAll();
		foreach($sessionIds as $sessionId) {
			$sessionId = $sessionId['sessionId'];
			$qb->delete('spreedme_messages')
				->where($qb->expr()->eq('recipient', $qb->createNamedParameter($sessionId)))
				->orWhere($qb->expr()->eq('sender', $qb->createNamedParameter($sessionId)))
				->execute();
		}

		// Set the session ID for the new room ID
		$sessionId = $this->secureRandom->generate(255);
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('spreedme_room_participants')
			->set('sessionId', $qb->createNamedParameter($sessionId))
			->where($qb->expr()->eq('roomId', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->execute();

		// For all other rooms set the last access time to 0 since the user isn't
		// active anymore in the room. This ensures that the user is considered
		// not active in the other rooms anymore.
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('spreedme_room_participants')
			->set('lastPing', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('sessionId', $qb->createNamedParameter(0))
			->where($qb->expr()->neq('roomId', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->execute();

		return new JSONResponse([
			'sessionId' => $sessionId,
		]);
	}
}
