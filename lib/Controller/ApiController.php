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

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
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
	 * @NoCSRFRequired
	 *
	 * @throws \Exception
	 * @return JSONResponse
	 */
	public function getRooms() {
		$rooms = $this->manager->getRoomsForParticipant($this->userId);

		$return = [];
		foreach ($rooms as $room) {
			$roomData = [
				'name' => $room->getName(),
				'displayName' => $room->getName(),
				'count' => $room->getNumberOfParticipants(time() - 10),
				'validRoom' => true,
			];

			// First we get room users (except current user).
			$participantPings = $room->getParticipants();
			unset($participantPings[$this->userId]);

			/** @var IUser[] $usersInCall */
			$usersInCall = [];
			foreach ($participantPings as $participant => $lastPing) {
				$user = $this->userManager->get($participant);
				if ($user instanceof IUser) {
					$usersInCall[] = $user;
				}
			}

			$numOtherParticipants = sizeof($usersInCall);
			
			switch($room->getType()) {
				case Room::ONE_TO_ONE_CALL:
					// As name of the room use the name of the other person participating
					if ($numOtherParticipants === 1) {
						// Only one other participant
						$roomData['name'] = $usersInCall[0]->getUID();
						$roomData['displayName'] = $usersInCall[0]->getDisplayName();
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
					} else if ($numOtherParticipants === 1) {
						// Only one other participant
						$roomData['displayName'] = $usersInCall[0]->getDisplayName();
					} else if ($numOtherParticipants === 2) {
						// 2 other participants
						$roomData['displayName'] = $this->l10n->t('%1$s, %2$s', [
							$usersInCall[0]->getDisplayName(),
							$usersInCall[1]->getDisplayName(),
						]);
					} else {
						// More than 2 other participants
						$others = $numOtherParticipants - 2;
						$roomData['displayName'] = $this->l10n->n('%1$s, %2$s and %n more', '%1$s, %2$s and %n more', $others, [
							$usersInCall[0]->getDisplayName(),
							$usersInCall[1]->getDisplayName(),
						]);
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
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function getPeersInRoom($roomId) {
		try {
			$room = $this->manager->getRoomById($roomId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$data = [];
		foreach ($room->getParticipants() as $participant => $lastPing) {
			$data[] = [
				'userId' => $participant,
				'roomId' => $roomId,
				'lastPing' => $lastPing,
			];
		}
		return new JSONResponse($data);
	}

	/**
	 * Returns the private chat room for two users or if not existent a
	 * RoomNotFoundException
	 *
	 * @param string $user1
	 * @param string $user2
	 * @return int
	 * @throws RoomNotFoundException
	 */
	private function getPrivateChatRoomForUsers($user1, $user2) {
		$qb = $this->dbConnection->getQueryBuilder();
		$results = $qb->select('*')
			->from('spreedme_rooms', 'r1')
			->leftJoin('r1', 'spreedme_room_participants', 'p1', $qb->expr()->andX(
				$qb->expr()->eq('p1.userId', $qb->createNamedParameter($user1)),
				$qb->expr()->eq('p1.roomId', 'r1.id')
			))
			->where($qb->expr()->isNotNull('p2.userId'))
			->andWhere($qb->expr()->isNotNull('p1.userId'))
			->andWhere($qb->expr()->eq('r1.type', $qb->createNamedParameter('1')))
			->leftJoin('r1', 'spreedme_room_participants', 'p2', $qb->expr()->andX(
				$qb->expr()->eq('p2.userId', $qb->createNamedParameter($user2)),
				$qb->expr()->eq('p2.roomId', 'r1.id')
			))
			->execute()
			->fetchAll();

		//There should be only one result if there is any.
		if(count($results) >=  1) {
			return (int)$results[count($results)-1]['roomId'];
		}

		throw new RoomNotFoundException();
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
			$roomId = $this->getPrivateChatRoomForUsers($targetUser->getUID(), $this->userId);
			return new JSONResponse(['roomId' => $roomId], Http::STATUS_OK);
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
	 * @param int $currentRoom
	 * @return JSONResponse
	 */
	public function ping($currentRoom) {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('spreed')
			->setUser($this->userId)
			->setObject('room', $currentRoom);
		$this->notificationManager->markProcessed($notification);

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('spreedme_room_participants')
			->set('lastPing', $qb->createNamedParameter(time()))
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('roomId', $qb->createNamedParameter($currentRoom)))
			->execute();
		return new JSONResponse();
	}
}
