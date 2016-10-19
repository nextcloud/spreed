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
use OCA\Spreed\Room;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
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
	/** @var ISecureRandom */
	private $secureRandom;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IDBConnection $dbConnection
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param ISecureRandom $secureRandom
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IDBConnection $dbConnection,
								IL10N $l10n,
								IUserManager $userManager,
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->dbConnection = $dbConnection;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * @param int $roomId
	 * @return array
	 */
	private function getActivePeers($roomId) {
		$qb = $this->dbConnection->getQueryBuilder();
		return $qb->select('*')
			->from('spreedme_room_participants')
			->where($qb->expr()->eq('roomId', $qb->createNamedParameter($roomId)))
			->andWhere($qb->expr()->gt('lastPing', $qb->createNamedParameter(time() - 10)))
			->execute()
			->fetchAll();
	}

	/**
	 * Get all participants for a room
	 *
	 * @param int $roomId
	 * @return array
	 */
	private function getRoomParticipants($roomId) {
		$qb = $this->dbConnection->getQueryBuilder();
		return $qb->select('*')
			->from('spreedme_room_participants')
			->where($qb->expr()->eq('roomId', $qb->createNamedParameter($roomId)))
			->execute()
			->fetchAll();
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
			$validRoom = false;
			switch($room['type']) {
				case Room::ONE_TO_ONE_CALL:
					// As name of the room use the name of the other person participating
					$participantsInCall = $this->getRoomParticipants($room['id']);

					switch(count($participantsInCall)) {
						case 1:
							// Empty call, this means the other person has left
							// the room. For now ignore this situation
							$validRoom = false;
							continue;
						case 2:
							// Two people are in the room. This is expected, now
							// read out the other recipient in the room.
							foreach($participantsInCall as $participant) {
								if($participant['userId'] !== $this->userId) {
									$rooms[$key]['name'] = $participant['userId'];
								}
							}
							$validRoom = true;
							break;
						default:
							$validRoom = false;
							// TODO: This should not really ever happen. Add some
							// error handling and fail here.
					}

					break;
				default:
					// TODO: More sane handling and logging of the room. Because
					// This shouldn't happen.
					continue;

			}
			$rooms[$key]['validRoom'] = $validRoom;
			$rooms[$key]['count'] = count($this->getActivePeers($room['id']));
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
		return new JSONResponse($this->getActivePeers($roomId));
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
			->leftJoin('r1', 'spreedme_room_participants', 'p2', $qb->expr()->andX(
				$qb->expr()->eq('p2.userId', $qb->createNamedParameter($user2)),
				$qb->expr()->eq('p2.roomId', 'r1.id')
			))
			->execute()
			->fetchAll();
		if(count($results) > 1) {
			return (int)$results[count($results)-1]['roomId'];
		}

		throw new RoomNotFoundException();
	}

	/**
	 * Initiates a one-to-one video call from the urrent user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetUserName
	 * @return JSONResponse
	 */
	public function createOneToOneVideoCallRoom($targetUserName) {
		// Get the user
		$targetUser = $this->userManager->get($targetUserName);
		if(!($targetUser instanceof IUser)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// If room exists: Reuse that one, otherwise create a new one.
		try {
			$roomId = $this->getPrivateChatRoomForUsers($targetUser->getUID(), $this->userId);
			return new JSONResponse(['roomId' => $roomId], Http::STATUS_CONFLICT);
		} catch (RoomNotFoundException $e) {
			// Create the room
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('spreedme_rooms')
				->values(
					[
						'name' => $qb->createNamedParameter($this->secureRandom->generate(12)),
						'type' => $qb->createNamedParameter(Room::ONE_TO_ONE_CALL),
					]
				)
				->execute();
			$roomId = $qb->getLastInsertId();

			// Add both users to new room
			$usersToAdd = [
				$targetUser->getUID(),
				$this->userId,
			];
			foreach($usersToAdd as $user) {
				$qb = $this->dbConnection->getQueryBuilder();
				$qb->insert('spreedme_room_participants')
					->values(
						[
							'userId' => $qb->createNamedParameter($user),
							'roomId' => $qb->createNamedParameter($roomId),
							'lastPing' => $qb->createNamedParameter('0'),
						]
					)
					->execute();
			}

			return new JSONResponse(['roomId' => $roomId], Http::STATUS_CREATED);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $currentRoom
	 * @return JSONResponse
	 */
	public function ping($currentRoom) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('spreedme_room_participants')
			->set('lastPing', $qb->createNamedParameter(time()))
			->where($qb->expr()->eq('userId', $qb->createNamedParameter($this->userId)))
			->andWhere($qb->expr()->eq('roomId', $qb->createNamedParameter($currentRoom)))
			->execute();
		return new JSONResponse();
	}
}
