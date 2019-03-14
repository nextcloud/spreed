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

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Exceptions\InvalidPasswordException;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Exceptions\UnauthorizedException;
use OCA\Spreed\GuestManager;
use OCA\Spreed\Manager;
use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroup;
use OCP\IGroupManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class RoomController extends AEnvironmentAwareController {
	/** @var string|null */
	private $userId;
	/** @var TalkSession */
	private $session;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var Manager */
	private $manager;
	/** @var GuestManager */
	private $guestManager;
	/** @var ChatManager */
	private $chatManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var MessageParser */
	private $messageParser;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IL10N */
	private $l10n;

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								TalkSession $session,
								IUserManager $userManager,
								IGroupManager $groupManager,
								Manager $manager,
								GuestManager $guestManager,
								ChatManager $chatManager,
								EventDispatcherInterface $dispatcher,
								MessageParser $messageParser,
								ITimeFactory $timeFactory,
								IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->session = $session;
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->manager = $manager;
		$this->guestManager = $guestManager;
		$this->chatManager = $chatManager;
		$this->dispatcher = $dispatcher;
		$this->messageParser = $messageParser;
		$this->timeFactory = $timeFactory;
		$this->l10n = $l10n;
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getRooms(): DataResponse {
		$this->dispatcher->dispatch(self::class . '::preGetRooms', new GenericEvent(null, [
			'userId' => $this->userId,
		]));

		$rooms = $this->manager->getRoomsForParticipant($this->userId, true);

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
	public function getSingleRoom(string $token): DataResponse {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId, true);

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
	 * @param Participant $currentParticipant
	 * @return array
	 * @throws RoomNotFoundException
	 */
	protected function formatRoom(Room $room, ?Participant $currentParticipant): array {
		$roomData = [
			'id' => $room->getId(),
			'token' => $room->getToken(),
			'type' => $room->getType(),
			'name' => '',
			'displayName' => '',
			'objectType' => '',
			'objectId' => '',
			'participantType' => Participant::GUEST,
			// Deprecated, use participantFlags instead.
			'participantInCall' => false,
			'participantFlags' => Participant::FLAG_DISCONNECTED,
			'readOnly' => Room::READ_WRITE,
			'count' => 0,
			'hasPassword' => $room->hasPassword(),
			'hasCall' => false,
			'lastActivity' => 0,
			'unreadMessages' => 0,
			'unreadMention' => false,
			'isFavorite' => false,
			'notificationLevel' => Participant::NOTIFY_NEVER,
			'lastPing' => 0,
			'sessionId' => '0',
			'participants' => [],
			'numGuests' => 0,
			'guestList' => '',
			'lastMessage' => [],
		];

		if (!$currentParticipant instanceof Participant) {
			return $roomData;
		}

		$lastActivity = $room->getLastActivity();
		if ($lastActivity instanceof \DateTimeInterface) {
			$lastActivity = $lastActivity->getTimestamp();
		} else {
			$lastActivity = 0;
		}

		$roomData = array_merge($roomData, [
			'name' => $room->getName(),
			'displayName' => $room->getDisplayName($currentParticipant->getUser()),
			'objectType' => $room->getObjectType(),
			'objectId' => $room->getObjectId(),
			'participantType' => $currentParticipant->getParticipantType(),
			// Deprecated, use participantFlags instead.
			'participantInCall' => ($currentParticipant->getInCallFlags() & Participant::FLAG_IN_CALL) !== 0,
			'participantFlags' => $currentParticipant->getInCallFlags(),
			'readOnly' => $room->getReadOnly(),
			'count' => $room->getNumberOfParticipants(false, $this->timeFactory->getTime() - 30),
			'hasCall' => $room->getActiveSince() instanceof \DateTimeInterface,
			'lastActivity' => $lastActivity,
			'isFavorite' => $currentParticipant->isFavorite(),
			'notificationLevel' => $currentParticipant->getNotificationLevel(),
		]);

		if ($roomData['notificationLevel'] === Participant::NOTIFY_DEFAULT) {
			if ($currentParticipant->isGuest()) {
				$roomData['notificationLevel'] = Participant::NOTIFY_NEVER;
			} else {
				$roomData['notificationLevel'] = $room->getType() === Room::ONE_TO_ONE_CALL ? Participant::NOTIFY_ALWAYS : Participant::NOTIFY_MENTION;
			}
		}

		$currentUser = $this->userManager->get($currentParticipant->getUser());
		if ($currentUser instanceof IUser) {
			$unreadSince = $this->chatManager->getUnreadMarker($room, $currentUser);
			if ($currentParticipant instanceof Participant) {
				$lastMention = $currentParticipant->getLastMention();
				$roomData['unreadMention'] = $lastMention !== null && $unreadSince < $lastMention;
			}
			$roomData['unreadMessages'] = $this->chatManager->getUnreadCount($room, $unreadSince);
		}

		$numActiveGuests = 0;
		$cleanGuests = false;
		$participantList = [];
		$participants = $room->getParticipants();
		uasort($participants, function(Participant $participant1, Participant $participant2) {
			return $participant2->getLastPing() - $participant1->getLastPing();
		});

		foreach ($participants as $participant) {
			if ($participant->isGuest()) {
				if ($participant->getLastPing() <= $this->timeFactory->getTime() - 100) {
					$cleanGuests = true;
				} else {
					$numActiveGuests++;
				}
			} else {
				$user = $this->userManager->get($participant->getUser());
				if ($user instanceof IUser) {
					$participantList[(string)$user->getUID()] = [
						'name' => $user->getDisplayName(),
						'type' => $participant->getParticipantType(),
						'call' => $participant->getInCallFlags(),
						'sessionId' => $participant->getSessionId(),
					];

					if ($room->getType() === Room::ONE_TO_ONE_CALL &&
						  $user->getUID() !== $currentParticipant->getUser()) {
						// FIXME This should not be done, but currently all the clients use it to get the avatar of the user …
						$roomData['name'] = $user->getUID();
					}
				}

				if ($participant->getSessionId() !== '0' && $participant->getLastPing() <= $this->timeFactory->getTime() - 100) {
					$room->leaveRoom($participant->getUser());
				}
			}
		}

		if ($cleanGuests) {
			$room->cleanGuestParticipants();
		}

		$lastMessage = $room->getLastMessage();
		if ($lastMessage instanceof IComment) {
			$lastMessage = $this->formatLastMessage($room, $currentParticipant, $lastMessage);
		} else {
			$lastMessage = [];
		}

		$roomData = array_merge($roomData, [
			'lastPing' => $currentParticipant->getLastPing(),
			'sessionId' => $currentParticipant->getSessionId(),
			'participants' => $participantList,
			'numGuests' => $numActiveGuests,
			'lastMessage' => $lastMessage,
		]);

		return $roomData;
	}

	/**
	 * @param Room $room
	 * @param Participant $participant
	 * @param IComment $lastMessage
	 * @return array
	 */
	protected function formatLastMessage(Room $room, Participant $participant, IComment $lastMessage): array {
		$message = $this->messageParser->createMessage($room, $participant, $lastMessage, $this->l10n);
		$this->messageParser->parseMessage($message);

		if (!$message->getVisibility()) {
			return [];
		}

		return [
			'id' => (int) $lastMessage->getId(),
			'actorType' => $message->getActorType(),
			'actorId' => $message->getActorId(),
			'actorDisplayName' => $message->getActorDisplayName(),
			'timestamp' => $lastMessage->getCreationDateTime()->getTimestamp(),
			'message' => $message->getMessage(),
			'messageParameters' => $message->getMessageParameters(),
			'systemMessage' => $message->getMessageType() === 'system' ? $lastMessage->getMessage() : '',
		];
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
	public function createRoom(int $roomType, string $invite = '', string $roomName = ''): DataResponse {
		switch ($roomType) {
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
	protected function createOneToOneRoom(string $targetUserName): DataResponse {
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
			$room->ensureOneToOneRoomIsFilled();
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
				'displayName' => $room->getDisplayName($currentUser->getUID()),
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
	protected function createGroupRoom(string $targetGroupName): DataResponse {
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

		\call_user_func_array([$room, 'addUsers'], $participants);

		return new DataResponse([
			'token' => $room->getToken(),
			'name' => $room->getName(),
			'displayName' => $room->getDisplayName($currentUser->getUID()),
		], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $roomName
	 * @param bool $public
	 * @return DataResponse
	 */
	protected function createEmptyRoom(string $roomName, bool $public = true): DataResponse {
		$roomName = trim($roomName);
		if ($roomName === '') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

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
			'displayName' => $room->getDisplayName($currentUser->getUID()),
		], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @return DataResponse
	 */
	public function addToFavorites(): DataResponse {
		$this->participant->setFavorite(true);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @return DataResponse
	 */
	public function removeFromFavorites(): DataResponse {
		$this->participant->setFavorite(false);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @param int $level
	 * @return DataResponse
	 */
	public function setNotificationLevel(int $level): DataResponse {

		if (!$this->participant->setNotificationLevel($level)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string $roomName
	 * @return DataResponse
	 */
	public function renameRoom(string $roomName): DataResponse {
		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$roomName = trim($roomName);

		if ($roomName === '' || strlen($roomName) > 200) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->room->setName($roomName);
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function deleteRoom(): DataResponse {
		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->room->deleteRoom();

		return new DataResponse([]);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * @return DataResponse
	 */
	public function getParticipants(): DataResponse {
		if ($this->participant->getParticipantType() === Participant::GUEST) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$participants = $this->room->getParticipantsLegacy();
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
				'userId' => '',
				'displayName' => $guestNames[$sessionHash] ?? '',
			]);
		}

		return new DataResponse($results);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @param string $newParticipant
	 * @param string $source
	 * @return DataResponse
	 */
	public function addParticipantToRoom(string $newParticipant, string $source = 'users'): DataResponse {
		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$participants = $this->room->getParticipantUserIds();

		$participantsToAdd = [];
		if ($source === 'users') {
			$newUser = $this->userManager->get($newParticipant);
			if (!$newUser instanceof IUser) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			if (\in_array($newParticipant, $participants, true)) {
				return new DataResponse([]);
			}

			$this->room->addUsers([
				'userId' => $newUser->getUID(),
			]);
		} else if ($source === 'groups') {
			$group = $this->groupManager->get($newParticipant);
			if (!$group instanceof IGroup) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$usersInGroup = $group->getUsers();
			foreach ($usersInGroup as $user) {
				if (\in_array($user->getUID(), $participants, true)) {
					continue;
				}

				$participantsToAdd[] = [
					'userId' => $user->getUID(),
				];
			}

			if (empty($participantsToAdd)) {
				return new DataResponse([]);
			}

			\call_user_func_array([$this->room, 'addUsers'], $participantsToAdd);
		} else if ($source === 'emails') {
			$data = [];
			if ($this->room->changeType(Room::PUBLIC_CALL)) {
				$data = ['type' => $this->room->getType()];
			}

			$this->guestManager->inviteByEmail($this->room, $newParticipant);

			return new DataResponse($data);
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @param string $newParticipant
	 * @return DataResponse
	 */
	public function inviteEmailToRoom(string $newParticipant): DataResponse {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$data = [];
		// In case a guest is added to a non-public call, we change the call to a public call
		if ($this->room->changeType(Room::PUBLIC_CALL)) {
			$data = ['type' => $this->room->getType()];
		}

		$this->guestManager->inviteByEmail($this->room, $newParticipant);

		return new DataResponse($data);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 *
	 * @param string $participant
	 * @return DataResponse
	 */
	public function removeParticipantFromRoom(string $participant): DataResponse {
		if ($this->participant->getUser() === $participant) {
			// Removing self, abusing moderator power
			return $this->removeSelfFromRoomLogic($this->room, $this->participant);
		}

		if (!$this->participant->hasModeratorPermissions()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($this->room->getType() === Room::ONE_TO_ONE_CALL) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$targetParticipant = $this->room->getParticipant($participant);
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

		$this->room->removeUser($targetUser, Room::PARTICIPANT_REMOVED);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @return DataResponse
	 */
	public function removeSelfFromRoom(): DataResponse {
		return $this->removeSelfFromRoomLogic($this->room, $this->participant);
	}

	protected function removeSelfFromRoomLogic(Room $room, Participant $participant): DataResponse {
		if ($room->getType() !== Room::ONE_TO_ONE_CALL) {
			if ($participant->hasModeratorPermissions(false)
				&& $room->getNumberOfParticipants() > 1
				&& $room->getNumberOfModerators() === 1) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		} else if ($room->getNumberOfParticipants() === 1) {
			$room->deleteRoom();
			return new DataResponse();
		}

		$currentUser = $this->userManager->get($participant->getUser());
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$room->removeUser($currentUser, Room::PARTICIPANT_LEFT);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string $participant
	 * @return DataResponse
	 */
	public function removeGuestFromRoom(string $participant): DataResponse {
		try {
			$targetParticipant = $this->room->getParticipantBySession($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$targetParticipant->isGuest()) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($targetParticipant->getSessionId() === $this->participant->getSessionId()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->room->removeParticipantBySession($targetParticipant, Room::PARTICIPANT_REMOVED);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function makePublic(): DataResponse {
		if (!$this->room->changeType(Room::PUBLIC_CALL)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function makePrivate(): DataResponse {
		if (!$this->room->changeType(Room::GROUP_CALL)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $state
	 * @return DataResponse
	 */
	public function setReadOnly(int $state): DataResponse {
		if (!$this->room->setReadOnly($state)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string $password
	 * @return DataResponse
	 */
	public function setPassword(string $password): DataResponse {
		if ($this->room->getType() !== Room::PUBLIC_CALL) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->room->setPassword($password);
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
	public function joinRoom(string $token, string $password = ''): DataResponse {
		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$user = $this->userManager->get($this->userId);
		try {
			if ($user instanceof IUser) {
				$newSessionId = $room->joinRoom($user, $password, $this->session->getPasswordForRoom($token) === $room->getToken());
			} else {
				$newSessionId = $room->joinRoomGuest($password, $this->session->getPasswordForRoom($token) === $room->getToken());
			}
		} catch (InvalidPasswordException $e) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		} catch (UnauthorizedException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->session->removePasswordForRoom($token);
		$this->session->setSessionForRoom($token, $newSessionId);
		$room->ping($this->userId, $newSessionId, $this->timeFactory->getTime());

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
	public function leaveRoom(string $token): DataResponse {
		$sessionId = $this->session->getSessionForRoom($token);
		$this->session->removeSessionForRoom($token);

		try {
			$room = $this->manager->getRoomForParticipantByToken($token, $this->userId);

			if ($this->userId === null) {
				$participant = $room->getParticipantBySession($sessionId);
				$room->removeParticipantBySession($participant, Room::PARTICIPANT_LEFT);
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
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string|null $participant
	 * @param string|null $sessionId
	 * @return DataResponse
	 */
	public function promoteModerator(?string $participant, ?string $sessionId): DataResponse {
		if ($participant !== null) {
			return $this->promoteUserToModerator($this->room, $participant);
		}

		return $this->promoteGuestToModerator($this->room, $sessionId);
	}

	protected function promoteUserToModerator(Room $room, string $participant): DataResponse {
		try {
			$targetParticipant = $room->getParticipant($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::USER) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$room->setParticipantType($participant, Participant::MODERATOR);

		return new DataResponse();
	}

	protected function promoteGuestToModerator(Room $room, string $sessionId): DataResponse {
		try {
			$targetParticipant = $room->getParticipantBySession($sessionId);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::GUEST) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$room->setParticipantTypeBySession($targetParticipant, Participant::GUEST_MODERATOR);

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string|null $participant
	 * @param string|null $sessionId
	 * @return DataResponse
	 */
	public function demoteModerator(?string $participant, ?string $sessionId): DataResponse {
		if ($participant !== null) {
			return $this->demoteUserFromModerator($this->room, $participant);
		}

		return $this->demoteGuestFromModerator($this->room, $sessionId);
	}

	protected function demoteUserFromModerator(Room $room, string $participant): DataResponse {
		if ($this->userId === $participant) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$targetParticipant = $room->getParticipant($participant);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::MODERATOR) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$room->setParticipantType($participant, Participant::USER);

		return new DataResponse();
	}

	protected function demoteGuestFromModerator(Room $room, string $sessionId): DataResponse {
		if ($this->session->getSessionForRoom($room->getToken()) === $sessionId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$targetParticipant = $room->getParticipantBySession($sessionId);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getParticipantType() !== Participant::GUEST_MODERATOR) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$room->setParticipantTypeBySession($targetParticipant, Participant::GUEST);

		return new DataResponse();
	}
}
