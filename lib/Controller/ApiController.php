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
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Notification\IManager as INotificationManager;

class ApiController extends Controller {
	/** @var string */
	private $userId;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var ISession */
	private $session;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var INotificationManager */
	private $notificationManager;
	/** @var IActivityManager */
	private $activityManager;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ISession $session
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param INotificationManager $notificationManager
	 * @param IActivityManager $activityManager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IL10N $l10n,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ISession $session,
								ILogger $logger,
								Manager $manager,
								INotificationManager $notificationManager,
								IActivityManager $activityManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->l10n = $l10n;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->session = $session;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->notificationManager = $notificationManager;
		$this->activityManager = $activityManager;
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
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

		return new JSONResponse($return);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function getRoom($roomId) {
		try {
			$room = $this->manager->getRoomById($roomId);
			return new JSONResponse($this->formatRoom($room));
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
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
			if ($participant1['lastPing'] === $participant2['lastPing']) {
				return 0;
			}
			return ($participant1['lastPing'] > $participant2['lastPing']) ? -1 : 1;
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
			'type' => $room->getType(),
			'name' => $room->getName(),
			'displayName' => $room->getName(),
			'isNameEditable' => ($room->getType() !== Room::ONE_TO_ONE_CALL),
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
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function getPeersInRoom($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var array[] $participants */
		$participants = $room->getParticipants(time() - 30);
		$result = [];
		foreach ($participants['users'] as $participant => $data) {
			if ($data['sessionId'] === '0') {
				// Use left the room
				continue;
			}

			$result[] = [
				'userId' => $participant,
				'roomId' => $roomId,
				'lastPing' => $data['lastPing'],
				'sessionId' => $data['sessionId'],
			];
		}

		foreach ($participants['guests'] as $data) {
			$result[] = [
				'userId' => '',
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
	public function createOneToOneRoom($targetUserName) {
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
			$room = $this->manager->createOne2OneRoom();
			$room->addUser($currentUser);

			$room->addUser($targetUser);
			$this->createNotification($currentUser, $targetUser, $room);

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
	public function createGroupRoom($targetGroupName) {
		$targetGroup = $this->groupManager->get($targetGroupName);
		$currentUser = $this->userManager->get($this->userId);

		if(!($targetGroup instanceof IGroup)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$usersInGroup = $targetGroup->getUsers();
		// If the user who is creating this call is not part of this group add them
		if (!$targetGroup->inGroup($currentUser)) {
			$usersInGroup[] = $currentUser;
		}

		// Create the room
		$room = $this->manager->createGroupRoom($targetGroup->getGID());
		foreach ($usersInGroup as $user) {
			$room->addUser($user);

			if ($currentUser->getUID() !== $user->getUID()) {
				$this->createNotification($currentUser, $user, $room);
			}
		}

		return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function createPublicRoom() {
		$currentUser = $this->userManager->get($this->userId);

		// Create the room
		$room = $this->manager->createPublicRoom();
		$room->addUser($currentUser);

		return new JSONResponse(['roomId' => $room->getId()], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @param string $roomName
	 * @return JSONResponse
	 */
	public function renameRoom($roomId, $roomName) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if (strlen($roomName) > 200) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if (!$room->setName($roomName)) {
			return new JSONResponse([], Http::STATUS_METHOD_NOT_ALLOWED);
		}
		return new JSONResponse([]);
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
		if (isset($participants['users'][$newParticipant])) {
			return new JSONResponse([]);
		}

		$currentUser = $this->userManager->get($this->userId);
		$newUser = $this->userManager->get($newParticipant);
		if (!$newUser instanceof IUser) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			// In case a user is added to a one2one call, we change the call to a group call
			$room->changeType(Room::GROUP_CALL);

			$room->addUser($newUser);
			$this->createNotification($currentUser, $newUser, $room);

			return new JSONResponse(['type' => $room->getType()]);
		}

		$room->addUser($newUser);
		$this->createNotification($currentUser, $newUser, $room);

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
	public function makePublic($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() !== Room::PUBLIC_CALL) {
			$room->changeType(Room::PUBLIC_CALL);
		}

		return new JSONResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function makePrivate($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::PUBLIC_CALL) {
			$room->changeType(Room::GROUP_CALL);
		}

		return new JSONResponse();
	}

	/**
	 * @PublicPage
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function ping($roomId) {
		try {
			$room = $this->manager->getRoomById($roomId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$sessionId = $this->session->get('spreed-session');
		$room->ping($this->userId, $sessionId, time());

		return new JSONResponse();
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param int $roomId
	 * @return JSONResponse
	 */
	public function joinRoom($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
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

		return new JSONResponse([
			'sessionId' => $newSessionId,
		]);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @return JSONResponse
	 */
	public function leave() {
		if ($this->userId !== null) {
			$this->manager->disconnectUserFromAllRooms($this->userId);
		} else {
			$sessionId = $this->session->get('spreed-session');
			$this->manager->removeSessionFromAllRooms($sessionId);
		}

		$this->session->remove('spreed-session');
		return new JSONResponse();
	}

	/**
	 * @param IUser $actor
	 * @param IUser $user
	 * @param Room $room
	 */
	protected function createNotification(IUser $actor, IUser $user, Room $room) {
		$notification = $this->notificationManager->createNotification();
		$dateTime = new \DateTime();
		try {
			$notification->setApp('spreed')
				->setUser($user->getUID())
				->setDateTime($dateTime)
				->setObject('room', $room->getId())
				->setSubject('invitation', [$actor->getUID()]);
			$this->notificationManager->notify($notification);
		} catch (\InvalidArgumentException $e) {
			// Error while creating the notification
			$this->logger->logException($e, ['app' => 'spreed']);
		}

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor($actor->getUID())
				->setAffectedUser($user->getUID())
				->setObject('room', $room->getId(), $room->getName())
				->setTimestamp($dateTime->getTimestamp())
				->setSubject('invitation', [
					'user' => $actor->getUID(),
					'room' => $room->getId(),
					'name' => $room->getName(),
				]);
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
			// Error while creating the activity
			$this->logger->logException($e, ['app' => 'spreed']);
		} catch (\BadMethodCallException $e) {
			// Error while sending the activity
			$this->logger->logException($e, ['app' => 'spreed']);
		}
	}
}
