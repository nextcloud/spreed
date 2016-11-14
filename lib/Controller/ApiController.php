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
								Manager $manager,
								IManager $notificationManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->secureRandom = $secureRandom;
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
		$qb = $this->dbConnection->getQueryBuilder();
		$rooms = $qb->select('*')
			->from('spreedme_rooms', 'r')
			->leftJoin('r', 'spreedme_room_participants', 'p', $qb->expr()->andX(
				$qb->expr()->eq('p.userId', $qb->createNamedParameter($this->userId)),
				$qb->expr()->eq('p.roomId', 'r.id')
			))
			->where($qb->expr()->isNotNull('p.userId'))
			->execute()
			->fetchAll();
		foreach($rooms as $key => $room) {
			$roomObject = $this->manager->getRoomById($room['id']);
			$validRoom = false;
			$usersInCall = [];

			// First we get room users (except current user).
			$participantsInCall = $roomObject->getParticipants();
			foreach($participantsInCall as $i => $participantInCall) {
				$uid = $participantsInCall[$i]['userId'];
		        if($uid === $this->userId){
		            //Delete current user from participantsInCall.
		            unset($participantsInCall[$i]);
		    	} else {
		    		$user = $this->userManager->get($uid);
		    		if($user === null) {
						// TODO: This should not really ever happen. Add some
						// error handling and fail here.
						continue;
					}
		    		$usersInCall[] = $user;
		    	}
			}

			switch($room['type']) {
				case Room::ONE_TO_ONE_CALL:
					// As name of the room use the name of the other person participating
					switch(count($usersInCall)) {
						case 1:
							// Only one other participant in the room. This is expected.
							$participant = $usersInCall[0];
							$rooms[$key]['name'] = $participant->getUID();
							$rooms[$key]['displayName'] = $participant->getDisplayName();
							$validRoom = true;
							break;
						default:
							$validRoom = false;
							// TODO: This should not really ever happen. Add some
							// error handling and fail here.
					}

					break;
				case Room::GROUP_CALL:
					/// As name of the room use the names of the other participants
					switch(count($usersInCall)) {
							case 0:
								// Only you
								$rooms[$key]['displayName'] = "You";
								break;
							case 1:
								// Only one more participant
								$participant = $usersInCall[0];
								$rooms[$key]['displayName'] = $participant->getDisplayName();
								$validRoom = true;
								break;
							case 2:
								// 2 more participants
								$participant = $usersInCall[0];
								$participant2 = $usersInCall[1];
								$rooms[$key]['displayName'] = "{$participant->getDisplayName()}, {$participant2->getDisplayName()}";
								$validRoom = true;
								break;
							default:
								// More than 2 other participants
								$participant = $usersInCall[0];
								$participant2 = $usersInCall[1];
								$others = count($usersInCall) - 2;
								$rooms[$key]['displayName'] = "{$participant->getDisplayName()}, {$participant2->getDisplayName()} & {$others} more";
								$validRoom = true;
								break;
						}

					break;
				default:
					// TODO: More sane handling and logging of the room. Because
					// This shouldn't happen.
					continue;

			}
			$rooms[$key]['validRoom'] = $validRoom;
			$rooms[$key]['count'] = $roomObject->getNumberOfParticipants(time() - 10);
		}

		return new JSONResponse($rooms);
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
		return new JSONResponse($room->getParticipants());
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
