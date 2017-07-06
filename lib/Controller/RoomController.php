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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Notification\IManager as INotificationManager;

class RoomController extends OCSController {
	/** @var string */
	private $userId;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
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
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param INotificationManager $notificationManager
	 * @param IActivityManager $activityManager
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ILogger $logger,
								Manager $manager,
								INotificationManager $notificationManager,
								IActivityManager $activityManager) {
		parent::__construct($appName, $request);
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->notificationManager = $notificationManager;
		$this->activityManager = $activityManager;
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetUserName
	 * @return DataResponse
	 */
	public function createOneToOneRoom($targetUserName) {
		// Get the user
		$targetUser = $this->userManager->get($targetUserName);
		$currentUser = $this->userManager->get($this->userId);
		if(!($targetUser instanceof IUser)) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// If room exists: Reuse that one, otherwise create a new one.
		try {
			$room = $this->manager->getOne2OneRoom($this->userId, $targetUser->getUID());
			return new DataResponse(['token' => $room->getToken()], Http::STATUS_OK);
		} catch (RoomNotFoundException $e) {
			$room = $this->manager->createOne2OneRoom();
			$room->addUser($currentUser);

			$room->addUser($targetUser);
			$this->createNotification($currentUser, $targetUser, $room);

			return new DataResponse(['token' => $room->getToken()], Http::STATUS_CREATED);
		}
	}

	/**
	 * Initiates a group video call from the selected group
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetGroupName
	 * @return DataResponse
	 */
	public function createGroupRoom($targetGroupName) {
		$targetGroup = $this->groupManager->get($targetGroupName);
		$currentUser = $this->userManager->get($this->userId);

		if(!($targetGroup instanceof IGroup)) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
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

		return new DataResponse(['token' => $room->getToken()], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function createPublicRoom() {
		$currentUser = $this->userManager->get($this->userId);

		// Create the room
		$room = $this->manager->createPublicRoom();
		$room->addUser($currentUser);

		return new DataResponse(['token' => $room->getToken()], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @param string $roomName
	 * @return DataResponse
	 */
	public function renameRoom($roomId, $roomName) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (strlen($roomName) > 200) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if (!$room->setName($roomName)) {
			return new DataResponse([], Http::STATUS_METHOD_NOT_ALLOWED);
		}
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @param string $newParticipant
	 * @return DataResponse
	 */
	public function addParticipantToRoom($roomId, $newParticipant) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$participants = $room->getParticipants();
		if (isset($participants['users'][$newParticipant])) {
			return new DataResponse([]);
		}

		$currentUser = $this->userManager->get($this->userId);
		$newUser = $this->userManager->get($newParticipant);
		if (!$newUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			// In case a user is added to a one2one call, we change the call to a group call
			$room->changeType(Room::GROUP_CALL);

			$room->addUser($newUser);
			$this->createNotification($currentUser, $newUser, $room);

			return new DataResponse(['type' => $room->getType()]);
		}

		$room->addUser($newUser);
		$this->createNotification($currentUser, $newUser, $room);

		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return DataResponse
	 */
	public function removeSelfFromRoom($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
			$room->deleteRoom();
		} else {
			$currentUser = $this->userManager->get($this->userId);
			$room->removeUser($currentUser);
		}

		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return DataResponse
	 */
	public function makePublic($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() !== Room::PUBLIC_CALL) {
			$room->changeType(Room::PUBLIC_CALL);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $roomId
	 * @return DataResponse
	 */
	public function makePrivate($roomId) {
		try {
			$room = $this->manager->getRoomForParticipant($roomId, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::PUBLIC_CALL) {
			$room->changeType(Room::GROUP_CALL);
		}

		return new DataResponse();
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
