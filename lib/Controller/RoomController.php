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

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Exceptions\InvalidPasswordException;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroup;
use OCP\IGroupManager;

class RoomController extends OCSController {
	/** @var string */
	private $userId;
	/** @var TalkSession */
	private $session;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var ILogger */
	private $logger;
	/** @var Manager */
	private $manager;
	/** @var GuestManager */
	private $guestManager;
	/** @var ChatManager */
	private $chatManager;
	/** @var IL10N */
	private $l10n;

	/**
	 * @param string $appName
	 * @param string $UserId
	 * @param IRequest $request
	 * @param TalkSession $session
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ILogger $logger
	 * @param Manager $manager
	 * @param GuestManager $guestManager
	 * @param ChatManager $chatManager
	 * @param IL10N $l10n
	 */
	public function __construct($appName,
								$UserId,
								IRequest $request,
								TalkSession $session,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ILogger $logger,
								Manager $manager,
								GuestManager $guestManager,
								ChatManager $chatManager,
								IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->session = $session;
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->manager = $manager;
		$this->guestManager = $guestManager;
		$this->chatManager = $chatManager;
		$this->l10n = $l10n;
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
				$return[] = $this->formatRoom($room, $room->getParticipant($this->userId));
			} catch (RoomNotFoundException $e) {
			} catch (\RuntimeException $e) {
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

			$participant = null;
			try {
				$participant = $room->getParticipant($this->userId);
			} catch (ParticipantNotFoundException $e) {
				try {
					$participant = $room->getParticipantBySession($this->session->getSessionForRoom($token));
				} catch (ParticipantNotFoundException $e) {
				}
			}

			return new DataResponse($this->formatRoom($room, $participant));
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param Room $room
	 * @param Participant $participant
	 * @return array
	 * @throws RoomNotFoundException
	 */
	protected function formatRoom(Room $room, Participant $participant = null) {

		if ($participant instanceof Participant) {
			$participantType = $participant->getParticipantType();
			$participantInCall = $participant->isInCall();
		} else {
			$participantType = Participant::GUEST;
			$participantInCall = false;
		}

		$roomData = [
			'id' => $room->getId(),
			'token' => $room->getToken(),
			'type' => $room->getType(),
			'name' => $room->getName(),
			'displayName' => $room->getName(),
			'participantType' => $participantType,
			'participantInCall' => $participantInCall,
			'count' => $room->getNumberOfParticipants(false, time() - 30),
			'hasPassword' => $room->hasPassword(),
			'hasCall' => $room->getActiveSince() instanceof \DateTimeInterface,
			'unreadMessages' => 0,
		];

		if (!$participant instanceof Participant) {
			// Unauthenticated user without a session (didn't enter password)
			$roomData = array_merge($roomData, [
				'lastPing' => 0,
				'sessionId' => '0',
				'participants' => [],
				'numGuests' => 0,
				'hasCall' => false,
				'guestList' => '',
			]);

			return $roomData;
		}

		$currentUser = $this->userManager->get($this->userId);
		if ($currentUser instanceof IUser) {
			$roomData['unreadMessages'] = $this->chatManager->getUnreadCount($room->getId(), $currentUser);
		}

		// Sort by lastPing
		/** @var array[] $participants */
		$participants = $room->getParticipants();
		$sortParticipants = function(array $participant1, array $participant2) {
			return $participant2['lastPing'] - $participant1['lastPing'];
		};
		uasort($participants['users'], $sortParticipants);
		uasort($participants['guests'], $sortParticipants);

		$participantList = [];
		foreach ($participants['users'] as $userId => $data) {
			$user = $this->userManager->get($userId);
			if ($user instanceof IUser) {
				$participantList[(string) $user->getUID()] = [
					'name' => $user->getDisplayName(),
					'type' => $data['participantType'],
					'call' => $data['inCall'],
				];
			}
		}

		$inactiveUsers = array_filter($participants['users'], function($data) {
			return $data['lastPing'] <= time() - 30 &&
				$data['sessionId'] !== 0 && $data['sessionId'] !== '' && $data['sessionId'] !== '0';
		});

		foreach ($inactiveUsers as $inactiveUserId => $data) {
			$room->leaveRoom($inactiveUserId);
		}

		$activeGuests = array_filter($participants['guests'], function($data) {
			return $data['lastPing'] > time() - 30;
		});

		$numActiveGuests = count($activeGuests);
		if ($numActiveGuests !== count($participants['guests'])) {
			$room->cleanGuestParticipants();
		}

		$roomData = array_merge($roomData, [
			'lastPing' => $participant->getLastPing(),
			'sessionId' => $participant->getSessionId(),
			'participants' => $participantList,
			'numGuests' => $numActiveGuests,
		]);

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
					$roomData['displayName'] = $participantList[$roomData['name']]['name'];
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
					$participantList = array_map(function($participant) {
						return $participant['name'];
					}, $participantList);
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
				throw new RoomNotFoundException('The room type is unknown');
		}

		$roomData['guestList'] = $guestString;

		return $roomData;
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param int $roomType
	 * @param string $invite
	 * @param string $roomName
	 * @return DataResponse
	 */
	public function createRoom($roomType, $invite = '', $roomName = '') {
		switch ((int) $roomType) {
			case Room::ONE_TO_ONE_CALL:
				return $this->createOneToOneRoom($invite);
			case Room::GROUP_CALL:
				if ($invite === '') {
					return $this->createEmptyRoom($roomName, false);
				}
				return $this->createGroupRoom($invite);
			case Room::PUBLIC_CALL:
				return $this->createEmptyRoom($roomName);
		}

		return new DataResponse([], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param string $targetUserName
	 * @return DataResponse
	 */
	protected function createOneToOneRoom($targetUserName) {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$targetUser = $this->userManager->get($targetUserName);
		if (!$targetUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId === $targetUserName) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		// If room exists: Reuse that one, otherwise create a new one.
		try {
			$room = $this->manager->getOne2OneRoom($this->userId, $targetUser->getUID());
			return new DataResponse(['token' => $room->getToken()], Http::STATUS_OK);
		} catch (RoomNotFoundException $e) {
			$room = $this->manager->createOne2OneRoom();
			$room->addUsers([
				'userId' => $currentUser->getUID(),
				'participantType' => Participant::OWNER,
			], [
				'userId' => $targetUser->getUID(),
				'participantType' => Participant::OWNER,
			]);

			return new DataResponse([
				'token' => $room->getToken(),
				'name' => $room->getName(),
				'displayName' => $targetUser->getDisplayName(),
			], Http::STATUS_CREATED);
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
	protected function createGroupRoom($targetGroupName) {
		$targetGroup = $this->groupManager->get($targetGroupName);
		$currentUser = $this->userManager->get($this->userId);

		if (!$targetGroup instanceof IGroup) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Create the room
		$room = $this->manager->createGroupRoom($targetGroup->getGID());
		$room->addUsers([
			'userId' => $currentUser->getUID(),
			'participantType' => Participant::OWNER,
		]);

		$usersInGroup = $targetGroup->getUsers();
		$participants = [];
		foreach ($usersInGroup as $user) {
			if ($currentUser->getUID() === $user->getUID()) {
				// Owner is already added.
				continue;
			}

			$participants[] = [
				'userId' => $user->getUID(),
			];
		}

		call_user_func_array([$room, 'addUsers'], $participants);

		return new DataResponse([
			'token' => $room->getToken(),
			'name' => $room->getName(),
			'displayName' => $room->getName(),
		], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $roomName
	 * @param bool $public
	 * @return DataResponse
	 */
	protected function createEmptyRoom($roomName, $public = true) {
		$currentUser = $this->userManager->get($this->userId);

		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Create the room
		if ($public) {
			$room = $this->manager->createPublicRoom($roomName);
		} else {
			$room = $this->manager->createGroupRoom($roomName);
		}
		$room->addUsers([
			'userId' => $currentUser->getUID(),
			'participantType' => Participant::OWNER,
		]);

		return new DataResponse([
			'token' => $room->getToken(),
			'name' => $room->getName(),
			'displayName' => $room->getName(),
		], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $roomName
	 * @return DataResponse
	 */
	public function renameRoom($token, $roomName) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
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
	 * @param string $token
	 * @return DataResponse
	 */
	public function deleteRoom($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$room->deleteRoom();

		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getParticipants($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$participants = $room->getParticipants();
		$results = [];

		foreach ($participants['users'] as $userId => $participant) {
			$user = $this->userManager->get((string) $userId);
			if (!$user instanceof IUser) {
				continue;
			}

			$results[] = array_merge($participant, [
				'userId' => (string) $userId,
				'displayName' => (string) $user->getDisplayName(),
			]);
		}

		$guestSessions = [];
		foreach ($participants['guests'] as $participant) {
			$guestSessions[] = sha1($participant['sessionId']);
		}
		$guestNames = $this->guestManager->getNamesBySessionHashes($guestSessions);

		foreach ($participants['guests'] as $participant) {
			$sessionHash = sha1($participant['sessionId']);
			$results[] = array_merge($participant, [
				'participantType' => Participant::GUEST,
				'userId' => '',
				'displayName' => isset($guestNames[$sessionHash]) ? $guestNames[$sessionHash] : '',
			]);
		}

		return new DataResponse($results);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $newParticipant
	 * @return DataResponse
	 */
	public function addParticipantToRoom($token, $newParticipant) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$participants = $room->getParticipants();
		if (isset($participants['users'][$newParticipant])) {
			return new DataResponse([]);
		}

		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$newUser = $this->userManager->get($newParticipant);
		if (!$newUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$data = [];
		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			// In case a user is added to a one2one call, we change the call to a group call
			$room->changeType(Room::GROUP_CALL);

			$data = ['type' => $room->getType()];
		}

		$room->addUsers([
			'userId' => $newUser->getUID(),
		]);

		return new DataResponse($data);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $participant
	 * @return DataResponse
	 */
	public function removeParticipantFromRoom($token, $participant) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$currentParticipant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($currentParticipant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			$room->deleteRoom();
			return new DataResponse([]);
		}

		try {
			$targetParticipant = $room->getParticipant($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() === Participant::OWNER) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$targetUser = $this->userManager->get($participant);
		if (!$targetUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$room->removeUser($targetUser);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function removeSelfFromRoom($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$room->getParticipant($this->userId); // Check if the participant is part of the room
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($room->getType() === Room::ONE_TO_ONE_CALL || $room->getNumberOfParticipants() === 1) {
			$room->deleteRoom();
		} else {
			$currentUser = $this->userManager->get($this->userId);
			if (!$currentUser instanceof IUser) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$room->removeUser($currentUser);
		}

		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $participant
	 * @return DataResponse
	 */
	public function removeGuestFromRoom($token, $participant) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$currentParticipant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($currentParticipant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$targetParticipant = $room->getParticipantBySession($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::GUEST) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$room->removeParticipantBySession($targetParticipant);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function makePublic($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($room->getType() !== Room::PUBLIC_CALL) {
			$room->changeType(Room::PUBLIC_CALL);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function makePrivate($token) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($room->getType() === Room::PUBLIC_CALL) {
			$room->changeType(Room::GROUP_CALL);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $password
	 * @return DataResponse
	 */
	public function setPassword($token, $password) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$participant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($participant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($room->getType() !== Room::PUBLIC_CALL) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$room->setPassword($password);
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @param string $password
	 * @return DataResponse
	 */
	public function joinRoom($token, $password = '') {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			if ($this->userId !== null) {
				$newSessionId = $room->joinRoom($this->userId, $password, $this->session->getPasswordForRoom($token) === $room->getToken());
			} else {
				$newSessionId = $room->joinRoomGuest($password, $this->session->getPasswordForRoom($token) === $room->getToken());
			}
		} catch (InvalidPasswordException $e) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->session->removePasswordForRoom($token);
		$this->session->setSessionForRoom($token, $newSessionId);
		$room->ping($this->userId, $newSessionId, time());

		return new DataResponse([
			'sessionId' => $newSessionId,
		]);
	}

	/**
	 * @PublicPage
	 * @UseSession
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function leaveRoom($token) {
		$sessionId = $this->session->getSessionForRoom($token);
		$this->session->removeSessionForRoom($token);

		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);

			if ($this->userId === null) {
				$participant = $room->getParticipantBySession($sessionId);
				$room->removeParticipantBySession($participant);
			} else {
				$participant = $room->getParticipant($this->userId);
				$room->leaveRoom($participant->getUser());
			}
		} catch (RoomNotFoundException $e) {
		} catch (ParticipantNotFoundException $e) {
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $participant
	 * @return DataResponse
	 */
	public function promoteModerator($token, $participant) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$currentParticipant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!in_array($currentParticipant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$targetParticipant = $room->getParticipant($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::USER) {
			return new DataResponse([], Http::STATUS_PRECONDITION_FAILED);
		}

		$room->setParticipantType($participant, Participant::MODERATOR);

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $participant
	 * @return DataResponse
	 */
	public function demoteModerator($token, $participant) {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
			$currentParticipant = $room->getParticipant($this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->userId === $participant) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if (!in_array($currentParticipant->getParticipantType(), [Participant::OWNER, Participant::MODERATOR], true)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$targetParticipant = $room->getParticipant($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::MODERATOR) {
			return new DataResponse([], Http::STATUS_PRECONDITION_FAILED);
		}

		$room->setParticipantType($participant, Participant::USER);

		return new DataResponse();
	}
}
