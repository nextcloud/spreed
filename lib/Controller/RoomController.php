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

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Events\UserEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\InvalidPasswordException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireModeratorOrNoLobby;
use OCA\Talk\Middleware\Attribute\RequireModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\Middleware\Attribute\RequireRoom;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\BreakoutRoomService;
use OCA\Talk\Service\ChecksumVerificationService;
use OCA\Talk\Service\NoteToSelfService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\HintException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\Bruteforce\IThrottler;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;
use Psr\Log\LoggerInterface;

class RoomController extends AEnvironmentAwareController {
	public const EVENT_BEFORE_ROOMS_GET = self::class . '::preGetRooms';

	protected array $commonReadMessages = [];

	public function __construct(
		string $appName,
		protected ?string $userId,
		IRequest $request,
		protected IAppManager $appManager,
		protected TalkSession $session,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
		protected Manager $manager,
		protected RoomService $roomService,
		protected BreakoutRoomService $breakoutRoomService,
		protected NoteToSelfService $noteToSelfService,
		protected ParticipantService $participantService,
		protected SessionService $sessionService,
		protected GuestManager $guestManager,
		protected IUserStatusManager $statusManager,
		protected IEventDispatcher $dispatcher,
		protected ITimeFactory $timeFactory,
		protected ChecksumVerificationService $checksumVerificationService,
		protected RoomFormatter $roomFormatter,
		protected IConfig $config,
		protected Config $talkConfig,
		protected ICloudIdManager $cloudIdManager,
		protected IThrottler $throttler,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	protected function getTalkHashHeader(): array {
		return [
			'X-Nextcloud-Talk-Hash' => sha1(
				$this->config->getSystemValueString('version') . '#' .
				$this->config->getAppValue('spreed', 'installed_version') . '#' .
				$this->config->getAppValue('spreed', 'stun_servers') . '#' .
				$this->config->getAppValue('spreed', 'turn_servers') . '#' .
				$this->config->getAppValue('spreed', 'signaling_servers') . '#' .
				$this->config->getAppValue('spreed', 'signaling_mode') . '#' .
				$this->config->getAppValue('spreed', 'signaling_ticket_secret') . '#' .
				$this->config->getAppValue('spreed', 'signaling_token_alg', 'ES256') . '#' .
				$this->config->getAppValue('spreed', 'signaling_token_privkey_' . $this->config->getAppValue('spreed', 'signaling_token_alg', 'ES256')) . '#' .
				$this->config->getAppValue('spreed', 'signaling_token_pubkey_' . $this->config->getAppValue('spreed', 'signaling_token_alg', 'ES256')) . '#' .
				$this->config->getAppValue('spreed', 'call_recording') . '#' .
				$this->config->getAppValue('spreed', 'recording_servers') . '#' .
				$this->config->getAppValue('spreed', 'allowed_groups') . '#' .
				$this->config->getAppValue('spreed', 'start_calls') . '#' .
				$this->config->getAppValue('spreed', 'start_conversations') . '#' .
				$this->config->getAppValue('spreed', 'default_permissions') . '#' .
				$this->config->getAppValue('spreed', 'breakout_rooms') . '#' .
				$this->config->getAppValue('spreed', 'federation_enabled') . '#' .
				$this->config->getAppValue('spreed', 'enable_matterbridge') . '#' .
				$this->config->getAppValue('spreed', 'has_reference_id') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_groups', '[]') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_dialin_info') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_shared_secret') . '#' .
				$this->config->getAppValue('spreed', 'recording_consent') . '#' .
				$this->config->getAppValue('theming', 'cachebuster', '1')
			)];
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @param int $noStatusUpdate When the user status should not be automatically set to online set to 1 (default 0)
	 * @param bool $includeStatus
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getRooms(int $noStatusUpdate = 0, bool $includeStatus = false, int $modifiedSince = 0): DataResponse {
		$nextModifiedSince = $this->timeFactory->getTime();

		$event = new UserEvent($this->userId);
		$this->dispatcher->dispatch(self::EVENT_BEFORE_ROOMS_GET, $event);
		$event = new BeforeRoomsFetchEvent($this->userId);
		$this->dispatcher->dispatchTyped($event);

		if ($noStatusUpdate === 0) {
			$isMobileApp = $this->request->isUserAgent([
				IRequest::USER_AGENT_TALK_ANDROID,
				IRequest::USER_AGENT_TALK_IOS,
			]);

			if ($isMobileApp) {
				// Bump the user status again
				$event = new UserLiveStatusEvent(
					$this->userManager->get($this->userId),
					IUserStatus::ONLINE,
					$this->timeFactory->getTime()
				);
				$this->dispatcher->dispatchTyped($event);
			}
		}

		$sessionIds = $this->session->getAllActiveSessions();
		$rooms = $this->manager->getRoomsForUser($this->userId, $sessionIds, true);

		if ($modifiedSince !== 0) {
			$rooms = array_filter($rooms, static function (Room $room) use ($includeStatus, $modifiedSince): bool {
				return ($includeStatus && $room->getType() === Room::TYPE_ONE_TO_ONE)
					|| ($room->getLastActivity() && $room->getLastActivity()->getTimestamp() >= $modifiedSince);
			});
		}

		$readPrivacy = $this->talkConfig->getUserReadPrivacy($this->userId);
		if ($readPrivacy === Participant::PRIVACY_PUBLIC) {
			$roomIds = array_map(static function (Room $room) {
				return $room->getId();
			}, $rooms);
			$this->commonReadMessages = $this->participantService->getLastCommonReadChatMessageForMultipleRooms($roomIds);
		}

		$statuses = [];
		if ($includeStatus
			&& $this->appManager->isEnabledForUser('user_status')) {
			$userIds = array_filter(array_map(function (Room $room) {
				if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
					$participants = json_decode($room->getName(), true);
					foreach ($participants as $participant) {
						if ($participant !== $this->userId) {
							return $participant;
						}
					}
				}
				return null;
			}, $rooms));

			$statuses = $this->statusManager->getUserStatuses($userIds);
		}

		$return = [];
		foreach ($rooms as $room) {
			try {
				$return[] = $this->formatRoom($room, $this->participantService->getParticipant($room, $this->userId), $statuses);
			} catch (ParticipantNotFoundException $e) {
				// for example in case the room was deleted concurrently,
				// the user is not a participant anymore
			}
		}

		$headers = $this->getTalkHashHeader();
		$headers['X-Nextcloud-Talk-Modified-Before'] = (string) $nextModifiedSince;

		return new DataResponse($return, Http::STATUS_OK, $headers);
	}

	/**
	 * Get listed rooms with optional search term
	 *
	 * @param string $searchTerm search term
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getListedRooms(string $searchTerm = ''): DataResponse {
		$rooms = $this->manager->getListedRoomsForUser($this->userId, $searchTerm);

		$return = [];
		foreach ($rooms as $room) {
			$return[] = $this->formatRoom($room, null);
		}

		return new DataResponse($return, Http::STATUS_OK);
	}

	/**
	 * Get all (for moderators and in case of "free selection) or the assigned breakout room
	 */
	#[NoAdminRequired]
	#[BruteForceProtection(action: 'talkRoomToken')]
	#[RequireLoggedInParticipant]
	public function getBreakoutRooms(): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->getBreakoutRooms($this->room, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$return = [];
		foreach ($rooms as $room) {
			try {
				$participant = $this->participantService->getParticipant($room, $this->userId);
			} catch (ParticipantNotFoundException $e) {
				$participant = null;
			}

			$return[] = $this->formatRoom($room, $participant, null, false, true);
		}


		return new DataResponse($return);
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomToken')]
	#[BruteForceProtection(action: 'talkSipBridgeSecret')]
	public function getSingleRoom(string $token): DataResponse {
		try {
			$isSIPBridgeRequest = $this->validateSIPBridgeRequest($token);
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'talkSipBridgeSecret']);
			return $response;
		}

		// The SIP bridge only needs room details (public, sip enabled, lobby state, etc)
		$includeLastMessage = !$isSIPBridgeRequest;

		try {
			$sessionId = $this->session->getSessionForRoom($token);
			$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId, $includeLastMessage, $isSIPBridgeRequest);

			$participant = null;
			try {
				$participant = $this->participantService->getParticipant($room, $this->userId, $sessionId);
			} catch (ParticipantNotFoundException $e) {
				try {
					$participant = $this->participantService->getParticipantBySession($room, $sessionId);
				} catch (ParticipantNotFoundException $e) {
				}
			}
			$statuses = [];
			if ($this->userId !== null
				&& $this->appManager->isEnabledForUser('user_status')) {
				$userIds = array_filter(array_map(function (Room $room) {
					if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
						$participants = json_decode($room->getName(), true);
						foreach ($participants as $participant) {
							if ($participant !== $this->userId) {
								return $participant;
							}
						}
					}
					return null;
				}, [$room]));

				$statuses = $this->statusManager->getUserStatuses($userIds);
			}

			return new DataResponse($this->formatRoom($room, $participant, $statuses, $isSIPBridgeRequest), Http::STATUS_OK, $this->getTalkHashHeader());
		} catch (RoomNotFoundException $e) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);
			return $response;
		}
	}

	/**
	 * Get the "Note to self" conversation for the user
	 *
	 * It will be automatically created when it is currently missing
	 */
	#[NoAdminRequired]
	public function getNoteToSelfConversation(): DataResponse {
		$room = $this->noteToSelfService->ensureNoteToSelfExistsForUser($this->userId);
		$participant = $this->participantService->getParticipant($room, $this->userId, false);
		return new DataResponse($this->formatRoom($room, $participant), Http::STATUS_OK, $this->getTalkHashHeader());
	}

	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The SIP bridge is sending the custom header "Talk-SIPBridge-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-SIPBridge-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $token
	 * @return bool True if the request is from the SIP bridge and valid, false if not from SIP bridge
	 * @throws UnauthorizedException when the request tried to sign as SIP bridge but is not valid
	 */
	private function validateSIPBridgeRequest(string $token): bool {
		$random = $this->request->getHeader('TALK_SIPBRIDGE_RANDOM');
		$checksum = $this->request->getHeader('TALK_SIPBRIDGE_CHECKSUM');
		$secret = $this->talkConfig->getSIPSharedSecret();
		return $this->checksumVerificationService->validateRequest($random, $checksum, $secret, $token);
	}

	protected function formatRoom(Room $room, ?Participant $currentParticipant, ?array $statuses = null, bool $isSIPBridgeRequest = false, bool $isListingBreakoutRooms = false): array {
		return $this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			$this->commonReadMessages,
			$room,
			$currentParticipant,
			$statuses,
			$isSIPBridgeRequest,
			$isListingBreakoutRooms,
		);
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @param int $roomType
	 * @param string $invite
	 * @param string $roomName
	 * @param string $source
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function createRoom(int $roomType, string $invite = '', string $roomName = '', string $source = '', string $objectType = '', string $objectId = ''): DataResponse {
		if ($roomType !== Room::TYPE_ONE_TO_ONE) {
			/** @var IUser $user */
			$user = $this->userManager->get($this->userId);

			if ($this->talkConfig->isNotAllowedToCreateConversations($user)) {
				return new DataResponse([], Http::STATUS_FORBIDDEN);
			}
		}

		switch ($roomType) {
			case Room::TYPE_ONE_TO_ONE:
				return $this->createOneToOneRoom($invite);
			case Room::TYPE_GROUP:
				if ($invite === '') {
					return $this->createEmptyRoom($roomName, false, $objectType, $objectId);
				}
				if ($source === 'circles') {
					return $this->createCircleRoom($invite);
				}
				return $this->createGroupRoom($invite);
			case Room::TYPE_PUBLIC:
				return $this->createEmptyRoom($roomName);
		}

		return new DataResponse([], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @param string $targetUserId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	protected function createOneToOneRoom(string $targetUserId): DataResponse {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetUserId === MatterbridgeManager::BRIDGE_BOT_USERID) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$targetUser = $this->userManager->get($targetUserId);
		if (!$targetUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			// We are only doing this manually here to be able to return different status codes
			// Actually createOneToOneConversation also checks it.
			$room = $this->manager->getOne2OneRoom($currentUser->getUID(), $targetUser->getUID());
			$this->participantService->ensureOneToOneRoomIsFilled($room);
			return new DataResponse(
				$this->formatRoom($room, $this->participantService->getParticipant($room, $currentUser->getUID(), false)),
				Http::STATUS_OK
			);
		} catch (RoomNotFoundException $e) {
		}

		try {
			$room = $this->roomService->createOneToOneConversation($currentUser, $targetUser);
			return new DataResponse(
				$this->formatRoom($room, $this->participantService->getParticipant($room, $currentUser->getUID(), false)),
				Http::STATUS_CREATED
			);
		} catch (InvalidArgumentException $e) {
			// Same current and target user
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Initiates a group video call from the selected group
	 *
	 * @param string $targetGroupName
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	protected function createGroupRoom(string $targetGroupName): DataResponse {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$targetGroup = $this->groupManager->get($targetGroupName);
		if (!$targetGroup instanceof IGroup) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Create the room
		$name = $this->roomService->prepareConversationName($targetGroup->getDisplayName());
		$room = $this->roomService->createConversation(Room::TYPE_GROUP, $name, $currentUser);
		$this->participantService->addGroup($room, $targetGroup);

		return new DataResponse($this->formatRoom($room, $this->participantService->getParticipant($room, $currentUser->getUID(), false)), Http::STATUS_CREATED);
	}

	/**
	 * Initiates a group video call from the selected circle
	 *
	 * @param string $targetCircleId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	protected function createCircleRoom(string $targetCircleId): DataResponse {
		if (!$this->appManager->isEnabledForUser('circles')) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$circle = $this->participantService->getCircle($targetCircleId, $this->userId);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Create the room
		$name = $this->roomService->prepareConversationName($circle->getName());
		$room = $this->roomService->createConversation(Room::TYPE_GROUP, $name, $currentUser);
		$this->participantService->addCircle($room, $circle);

		return new DataResponse($this->formatRoom($room, $this->participantService->getParticipant($room, $currentUser->getUID(), false)), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	protected function createEmptyRoom(string $roomName, bool $public = true, string $objectType = '', string $objectId = ''): DataResponse {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$roomType = $public ? Room::TYPE_PUBLIC : Room::TYPE_GROUP;
		/** @var Room|null $parentRoom */
		$parentRoom = null;

		if ($objectType === BreakoutRoom::PARENT_OBJECT_TYPE) {
			try {
				$parentRoom = $this->manager->getRoomForUserByToken($objectId, $this->userId);
				$parentRoomParticipant = $this->participantService->getParticipant($parentRoom, $this->userId);

				if (!$parentRoomParticipant->hasModeratorPermissions()) {
					return new DataResponse(['error' => 'permissions'], Http::STATUS_BAD_REQUEST);
				}
				if ($parentRoom->getBreakoutRoomMode() === BreakoutRoom::MODE_NOT_CONFIGURED) {
					return new DataResponse(['error' => 'mode'], Http::STATUS_BAD_REQUEST);
				}

				// Overwriting the type with the parent type.
				$roomType = $parentRoom->getType();
			} catch (RoomNotFoundException $e) {
				return new DataResponse(['error' => 'room'], Http::STATUS_BAD_REQUEST);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse(['error' => 'permissions'], Http::STATUS_BAD_REQUEST);
			}
		} elseif ($objectType !== '') {
			return new DataResponse(['error' => 'object'], Http::STATUS_BAD_REQUEST);
		}

		// Create the room
		try {
			$room = $this->roomService->createConversation($roomType, $roomName, $currentUser, $objectType, $objectId);
		} catch (InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$currentParticipant = $this->participantService->getParticipant($room, $currentUser->getUID(), false);
		if ($objectType === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Enforce the lobby state when breakout rooms are disabled
			if ($parentRoom instanceof Room && $parentRoom->getBreakoutRoomStatus() === BreakoutRoom::STATUS_STOPPED) {
				$this->roomService->setLobby($room, Webinary::LOBBY_NON_MODERATORS, null, false, false);
			}

			$participants = $this->participantService->getParticipantsForRoom($parentRoom);
			$moderators = array_filter($participants, static function (Participant $participant) use ($currentParticipant) {
				return $participant->hasModeratorPermissions()
					&& $participant->getAttendee()->getId() !== $currentParticipant->getAttendee()->getId();
			});
			if (!empty($moderators)) {
				$this->breakoutRoomService->addModeratorsToBreakoutRooms([$room], $moderators);
			}
		}

		return new DataResponse($this->formatRoom($room, $currentParticipant), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function addToFavorites(): DataResponse {
		$this->participantService->updateFavoriteStatus($this->participant, true);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function removeFromFavorites(): DataResponse {
		$this->participantService->updateFavoriteStatus($this->participant, false);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function setNotificationLevel(int $level): DataResponse {
		try {
			$this->participantService->updateNotificationLevel($this->participant, $level);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function setNotificationCalls(int $level): DataResponse {
		try {
			$this->participantService->updateNotificationCalls($this->participant, $level);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function renameRoom(string $roomName): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE || $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$roomName = trim($roomName);

		if ($roomName === '' || mb_strlen($roomName) > 255) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->roomService->setName($this->room, $roomName);
		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setDescription(string $description): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE || $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->roomService->setDescription($this->room, $description);
		} catch (\LengthException $exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function deleteRoom(): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE || $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->roomService->deleteRoom($this->room);

		return new DataResponse([]);
	}

	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function getParticipants(bool $includeStatus = false): DataResponse {
		if ($this->participant->getAttendee()->getParticipantType() === Participant::GUEST) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$participants = $this->participantService->getSessionsAndParticipantsForRoom($this->room);

		return $this->formatParticipantList($participants, $includeStatus);
	}

	#[PublicPage]
	#[RequireModeratorOrNoLobby]
	#[RequireParticipant]
	public function getBreakoutRoomParticipants(bool $includeStatus = false): DataResponse {
		if ($this->participant->getAttendee()->getParticipantType() === Participant::GUEST) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$breakoutRooms = $this->breakoutRoomService->getBreakoutRooms($this->room, $this->participant);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$breakoutRooms[] = $this->room;
		$participants = $this->participantService->getSessionsAndParticipantsForRooms($breakoutRooms);

		return $this->formatParticipantList($participants, $includeStatus);
	}

	/**
	 * @param Participant[] $participants
	 * @param bool $includeStatus
	 * @return DataResponse
	 */
	protected function formatParticipantList(array $participants, bool $includeStatus): DataResponse {
		$results = $headers = $statuses = [];
		$maxPingAge = $this->timeFactory->getTime() - Session::SESSION_TIMEOUT_KILL;

		if ($this->userId !== null
			&& $includeStatus
			&& count($participants) < Config::USER_STATUS_INTEGRATION_LIMIT
			&& $this->appManager->isEnabledForUser('user_status')) {
			$userIds = array_filter(array_map(static function (Participant $participant) {
				if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
					return $participant->getAttendee()->getActorId();
				}
				return null;
			}, $participants));

			$statuses = $this->statusManager->getUserStatuses($userIds);

			$headers['X-Nextcloud-Has-User-Statuses'] = true;
		}

		$cleanGuests = false;
		foreach ($participants as $participant) {
			$attendeeId = $participant->getAttendee()->getId();
			if (isset($results[$attendeeId])) {
				$session = $participant->getSession();
				if (!$session instanceof Session) {
					// If the user has an entry already and this has no session we don't need it anymore.
					continue;
				}

				if ($session->getLastPing() <= $maxPingAge) {
					if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GUESTS) {
						$cleanGuests = true;
					} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
						$this->participantService->leaveRoomAsSession($this->room, $participant);
					}
					// Session expired, ignore
					continue;
				}

				// Combine the session values: All inCall bit flags, newest lastPing and any sessionId (for online checking)
				$results[$attendeeId]['inCall'] |= $session->getInCall();
				$results[$attendeeId]['lastPing'] = max($results[$attendeeId]['lastPing'], $session->getLastPing());
				$results[$attendeeId]['sessionIds'][] = $session->getSessionId();
				continue;
			}

			$result = [
				'roomToken' => $participant->getRoom()->getToken(),
				'inCall' => Participant::FLAG_DISCONNECTED,
				'lastPing' => 0,
				'sessionIds' => [],
				'participantType' => $participant->getAttendee()->getParticipantType(),
				'attendeeId' => $attendeeId,
				'actorId' => $participant->getAttendee()->getActorId(),
				'actorType' => $participant->getAttendee()->getActorType(),
				'displayName' => $participant->getAttendee()->getActorId(),
				'permissions' => $participant->getPermissions(),
				'attendeePermissions' => $participant->getAttendee()->getPermissions(),
				'attendeePin' => '',
			];
			if ($this->talkConfig->isSIPConfigured()
				&& $this->room->getSIPEnabled() !== Webinary::SIP_DISABLED
				&& ($this->participant->hasModeratorPermissions(false)
					|| $this->participant->getAttendee()->getId() === $participant->getAttendee()->getId())) {
				// Generate a PIN if the attendee is a user and doesn't have one.
				$this->participantService->generatePinForParticipant($this->room, $participant);

				$result['attendeePin'] = (string) $participant->getAttendee()->getPin();
			}

			if ($participant->getSession() instanceof Session) {
				$result['inCall'] = $participant->getSession()->getInCall();
				$result['lastPing'] = $participant->getSession()->getLastPing();
				$result['sessionIds'] = [$participant->getSession()->getSessionId()];
			}

			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$userId = $participant->getAttendee()->getActorId();
				if ($result['lastPing'] > 0 && $result['lastPing'] <= $maxPingAge) {
					$this->participantService->leaveRoomAsSession($this->room, $participant);
				}

				$result['displayName'] = $participant->getAttendee()->getDisplayName();
				if (!$result['displayName']) {
					$userDisplayName = $this->userManager->getDisplayName($userId);
					if ($userDisplayName === null) {
						continue;
					}
					$result['displayName'] = $userDisplayName;
				}

				if (isset($statuses[$userId])) {
					$result['status'] = $statuses[$userId]->getStatus();
					$result['statusIcon'] = $statuses[$userId]->getIcon();
					$result['statusMessage'] = $statuses[$userId]->getMessage();
					$result['statusClearAt'] = $statuses[$userId]->getClearAt()?->getTimestamp();
				} elseif (isset($headers['X-Nextcloud-Has-User-Statuses'])) {
					$result['status'] = IUserStatus::OFFLINE;
					$result['statusIcon'] = null;
					$result['statusMessage'] = null;
					$result['statusClearAt'] = null;
				}
			} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GUESTS) {
				if ($participant->getAttendee()->getParticipantType() === Participant::GUEST
					&& ($participant->getAttendee()->getPermissions() === Attendee::PERMISSIONS_DEFAULT
						|| $participant->getAttendee()->getPermissions() === Attendee::PERMISSIONS_CUSTOM)) {
					// Guests without an up-to-date session are filtered out. We
					// only keep there attendees in the database, so that the
					// comments show the display name. Only when they have
					// non-default permissions we show them, so permissions can
					// be reset or removed
					if ($result['lastPing'] <= $maxPingAge) {
						$cleanGuests = true;
						continue;
					}
				}

				$result['displayName'] = $participant->getAttendee()->getDisplayName();
			} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GROUPS) {
				$result['displayName'] = $participant->getAttendee()->getDisplayName();
			} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_CIRCLES) {
				$result['displayName'] = $participant->getAttendee()->getDisplayName();
			}

			$results[$attendeeId] = $result;
		}

		if ($cleanGuests) {
			$this->participantService->cleanGuestParticipants($this->room);
		}

		return new DataResponse(array_values($results), Http::STATUS_OK, $headers);
	}

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function addParticipantToRoom(string $newParticipant, string $source = 'users'): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE
			|| $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER
			|| $this->room->getType() === Room::TYPE_NOTE_TO_SELF
			|| $this->room->getObjectType() === 'share:password') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($source !== 'users' && $this->room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Can only add users to breakout rooms
			return new DataResponse(['error' => 'source'], Http::STATUS_BAD_REQUEST);
		}

		$participants = $this->participantService->getParticipantsForRoom($this->room);
		$participantsByUserId = [];
		$remoteParticipantsByFederatedId = [];
		foreach ($participants as $participant) {
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$participantsByUserId[$participant->getAttendee()->getActorId()] = $participant;
			} elseif ($participant->getAttendee()->getAccessToken() === Attendee::ACTOR_FEDERATED_USERS) {
				$remoteParticipantsByFederatedId[$participant->getAttendee()->getActorId()] = $participant;
			}
		}

		// list of participants to attempt adding,
		// existing ones will be filtered later below
		$participantsToAdd = [];

		if ($source === 'users') {
			if ($newParticipant === MatterbridgeManager::BRIDGE_BOT_USERID) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$newUser = $this->userManager->get($newParticipant);
			if (!$newUser instanceof IUser) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$participantsToAdd[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $newUser->getUID(),
				'displayName' => $newUser->getDisplayName(),
			];
		} elseif ($source === 'groups') {
			$group = $this->groupManager->get($newParticipant);
			if (!$group instanceof IGroup) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$this->participantService->addGroup($this->room, $group, $participants);
		} elseif ($source === 'circles') {
			if (!$this->appManager->isEnabledForUser('circles')) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}

			try {
				$circle = $this->participantService->getCircle($newParticipant, $this->userId);
			} catch (\Exception $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}

			$this->participantService->addCircle($this->room, $circle, $participants);
		} elseif ($source === 'emails') {
			$data = [];
			if ($this->roomService->setType($this->room, Room::TYPE_PUBLIC)) {
				$data = ['type' => $this->room->getType()];
			}

			$participant = $this->participantService->inviteEmailAddress($this->room, $newParticipant);

			$this->guestManager->sendEmailInvitation($this->room, $participant);

			return new DataResponse($data);
		} elseif ($source === 'remotes') {
			if (!$this->talkConfig->isFederationEnabled()) {
				return new DataResponse([], Http::STATUS_NOT_IMPLEMENTED);
			}
			try {
				$newUser = $this->cloudIdManager->resolveCloudId($newParticipant);
			} catch (\InvalidArgumentException $e) {
				$this->logger->error($e->getMessage(), [
					'exception' => $e,
				]);
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}

			$participantsToAdd[] = [
				'actorType' => Attendee::ACTOR_FEDERATED_USERS,
				'actorId' => $newUser->getId(),
				'displayName' => $newUser->getDisplayId(),
			];
		} else {
			$this->logger->error('Trying to add participant from unsupported source ' . $source);
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		// attempt adding the listed users to the room
		// existing users with USER_SELF_JOINED will get converted to regular USER participants
		foreach ($participantsToAdd as $index => $participantToAdd) {
			$existingParticipant = $participantsByUserId[$participantToAdd['actorId']] ?? null;
			if ($participantToAdd['actorType'] === Attendee::ACTOR_FEDERATED_USERS) {
				$existingParticipant = $remoteParticipantsByFederatedId[$participantToAdd['actorId']] ?? null;
			}

			if ($existingParticipant !== null) {
				unset($participantsToAdd[$index]);
				if ($existingParticipant->getAttendee()->getParticipantType() !== Participant::USER_SELF_JOINED) {
					// user is already a regular participant, skip
					continue;
				}
				$this->participantService->updateParticipantType($this->room, $existingParticipant, Participant::USER);
			}
		}

		$addedBy = $this->userManager->get($this->userId);

		if ($source === 'users' && $this->room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			$parentRoom = $this->manager->getRoomByToken($this->room->getObjectId());

			// Also add to parent room in case the user is missing
			try {
				$this->participantService->getParticipantByActor(
					$parentRoom,
					Attendee::ACTOR_USERS,
					$newParticipant
				);
			} catch (ParticipantNotFoundException $e) {
				$this->participantService->addUsers($parentRoom, $participantsToAdd, $addedBy);
			}

			// Remove from previous breakout room in case the user is moved
			try {
				$this->breakoutRoomService->removeAttendeeFromBreakoutRoom($parentRoom, Attendee::ACTOR_USERS, $newParticipant);
			} catch (\InvalidArgumentException $e) {
				return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
			}
		}

		// add the remaining users in batch
		$this->participantService->addUsers($this->room, $participantsToAdd, $addedBy);

		return new DataResponse([]);
	}

	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function removeSelfFromRoom(): DataResponse {
		return $this->removeSelfFromRoomLogic($this->room, $this->participant);
	}

	protected function removeSelfFromRoomLogic(Room $room, Participant $participant): DataResponse {
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE && $room->getType() !== Room::TYPE_ONE_TO_ONE_FORMER) {
			if ($participant->hasModeratorPermissions(false)
				&& $this->participantService->getNumberOfUsers($room) > 1
				&& $this->participantService->getNumberOfModerators($room) === 1) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		}

		if ($room->getType() !== Room::TYPE_CHANGELOG &&
			$room->getObjectType() !== 'file' &&
			$this->participantService->getNumberOfUsers($room) === 1 &&
			\in_array($participant->getAttendee()->getParticipantType(), [
				Participant::USER,
				Participant::MODERATOR,
				Participant::OWNER,
			], true)) {
			$this->roomService->deleteRoom($room);
			return new DataResponse();
		}

		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->participantService->removeUser($room, $currentUser, Room::PARTICIPANT_LEFT);

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function removeAttendeeFromRoom(int $attendeeId): DataResponse {
		try {
			$targetParticipant = $this->participantService->getParticipantByAttendeeId($this->room, $attendeeId);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($targetParticipant->getAttendee()->getActorType() === Attendee::ACTOR_USERS
			&& $targetParticipant->getAttendee()->getActorId() === MatterbridgeManager::BRIDGE_BOT_USERID) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE || $this->room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($this->participant->getAttendee()->getId() === $targetParticipant->getAttendee()->getId()) {
			return $this->removeSelfFromRoomLogic($this->room, $targetParticipant);
		}

		if ($targetParticipant->getAttendee()->getParticipantType() === Participant::OWNER) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->participantService->removeAttendee($this->room, $targetParticipant, Room::PARTICIPANT_REMOVED);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function makePublic(): DataResponse {
		if (!$this->roomService->setType($this->room, Room::TYPE_PUBLIC)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function makePrivate(): DataResponse {
		if (!$this->roomService->setType($this->room, Room::TYPE_GROUP)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function setReadOnly(int $state): DataResponse {
		if (!$this->roomService->setReadOnly($this->room, $state)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($state === Room::READ_ONLY) {
			$participants = $this->participantService->getParticipantsInCall($this->room);

			// kick out all participants out of the call
			foreach ($participants as $participant) {
				$this->participantService->changeInCall($this->room, $participant, Participant::FLAG_DISCONNECTED);
			}
		}

		return new DataResponse();
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function setListable(int $scope): DataResponse {
		if (!$this->roomService->setListable($this->room, $scope)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setPassword(string $password): DataResponse {
		if ($this->room->getType() !== Room::TYPE_PUBLIC) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			if (!$this->roomService->setPassword($this->room, $password)) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		} catch (HintException $e) {
			return new DataResponse([
				'message' => $e->getHint(),
			], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkRoomPassword')]
	#[BruteForceProtection(action: 'talkRoomToken')]
	public function joinRoom(string $token, string $password = '', bool $force = true): DataResponse {
		$sessionId = $this->session->getSessionForRoom($token);
		try {
			// The participant is just joining, so enforce to not load any session
			$room = $this->manager->getRoomForUserByToken($token, $this->userId, null);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var Participant|null $previousSession */
		$previousParticipant = null;
		/** @var Session|null $previousSession */
		$previousSession = null;

		if ($sessionId !== null) {
			try {
				if ($this->userId !== null) {
					$previousParticipant = $this->participantService->getParticipant($room, $this->userId, $sessionId);
				} else {
					$previousParticipant = $this->participantService->getParticipantBySession($room, $sessionId);
				}
				$previousSession = $previousParticipant->getSession();
			} catch (ParticipantNotFoundException $e) {
			}

			if ($previousSession instanceof Session && $previousSession->getSessionId() === $sessionId) {
				if ($force === false && $previousSession->getInCall() !== Participant::FLAG_DISCONNECTED) {
					// Previous session is/was active in the call, show a warning
					return new DataResponse([
						'sessionId' => $previousSession->getSessionId(),
						'inCall' => $previousSession->getInCall(),
						'lastPing' => $previousSession->getLastPing(),
					], Http::STATUS_CONFLICT);
				}

				if ($previousSession->getInCall() !== Participant::FLAG_DISCONNECTED) {
					$this->participantService->changeInCall($room, $previousParticipant, Participant::FLAG_DISCONNECTED);
				}

				$this->participantService->leaveRoomAsSession($room, $previousParticipant, true);
			}
		}

		$user = $this->userManager->get($this->userId);
		try {
			$result = $this->roomService->verifyPassword($room, (string) $this->session->getPasswordForRoom($token));
			if ($user instanceof IUser) {
				$participant = $this->participantService->joinRoom($this->roomService, $room, $user, $password, $result['result']);
				$this->participantService->generatePinForParticipant($room, $participant);
			} else {
				$participant = $this->participantService->joinRoomAsNewGuest($this->roomService, $room, $password, $result['result'], $previousParticipant);
			}
			$this->throttler->resetDelay($this->request->getRemoteAddress(), 'talkRoomPassword', ['token' => $token, 'action' => 'talkRoomPassword']);
			$this->throttler->resetDelay($this->request->getRemoteAddress(), 'talkRoomToken', ['token' => $token, 'action' => 'talkRoomToken']);
		} catch (InvalidPasswordException $e) {
			$response = new DataResponse([], Http::STATUS_FORBIDDEN);
			$response->throttle(['token' => $token, 'action' => 'talkRoomPassword']);
			return $response;
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $token, 'action' => 'talkRoomToken']);
			return $response;
		}

		$this->session->removePasswordForRoom($token);
		$session = $participant->getSession();
		if ($session instanceof Session) {
			$this->session->setSessionForRoom($token, $session->getSessionId());
			$this->sessionService->updateLastPing($session, $this->timeFactory->getTime());
		}

		return new DataResponse($this->formatRoom($room, $participant));
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkSipBridgeSecret')]
	#[RequireRoom]
	public function getParticipantByDialInPin(string $pin): DataResponse {
		try {
			if (!$this->validateSIPBridgeRequest($this->room->getToken())) {
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle(['action' => 'talkSipBridgeSecret']);
				return $response;
			}
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'talkSipBridgeSecret']);
			return $response;
		}

		try {
			$participant = $this->participantService->getParticipantByPin($this->room, $pin);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse($this->formatRoom($this->room, $participant));
	}

	#[PublicPage]
	#[BruteForceProtection(action: 'talkSipBridgeSecret')]
	#[RequireRoom]
	public function createGuestByDialIn(): DataResponse {
		try {
			if (!$this->validateSIPBridgeRequest($this->room->getToken())) {
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle(['action' => 'talkSipBridgeSecret']);
				return $response;
			}
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'talkSipBridgeSecret']);
			return $response;
		}

		if ($this->room->getSIPEnabled() !== Webinary::SIP_ENABLED_NO_PIN) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$participant = $this->participantService->joinRoomAsNewGuest($this->roomService, $this->room, '', true);

		return new DataResponse($this->formatRoom($this->room, $participant));
	}

	#[PublicPage]
	#[RequireParticipant]
	public function setSessionState(int $state): DataResponse {
		try {
			$this->sessionService->updateSessionState($this->participant->getSession(), $state);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	public function leaveRoom(string $token): DataResponse {
		$sessionId = $this->session->getSessionForRoom($token);
		$this->session->removeSessionForRoom($token);

		try {
			$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
			$participant = $this->participantService->getParticipantBySession($room, $sessionId);
			$this->participantService->leaveRoomAsSession($room, $participant);
		} catch (RoomNotFoundException $e) {
		} catch (ParticipantNotFoundException $e) {
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function promoteModerator(int $attendeeId): DataResponse {
		return $this->changeParticipantType($attendeeId, true);
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function demoteModerator(int $attendeeId): DataResponse {
		return $this->changeParticipantType($attendeeId, false);
	}

	/**
	 * Toggle a user/guest to moderator/guest-moderator or vice-versa based on
	 * attendeeId
	 *
	 * @param int $attendeeId
	 * @param bool $promote Shall the attendee be promoted or demoted
	 * @return DataResponse
	 */
	protected function changeParticipantType(int $attendeeId, bool $promote): DataResponse {
		try {
			$targetParticipant = $this->participantService->getParticipantByAttendeeId($this->room, $attendeeId);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$attendee = $targetParticipant->getAttendee();

		if ($attendee->getActorType() === Attendee::ACTOR_USERS
			&& $attendee->getActorId() === MatterbridgeManager::BRIDGE_BOT_USERID) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// Prevent users/moderators modifying themselves
		if ($attendee->getActorType() === $this->participant->getAttendee()->getActorType()) {
			if ($attendee->getActorId() === $this->participant->getAttendee()->getActorId()) {
				return new DataResponse([], Http::STATUS_FORBIDDEN);
			}
		} elseif ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
			// Can not promote/demote groups
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($promote === $targetParticipant->hasModeratorPermissions()) {
			// Prevent concurrent changes
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($attendee->getParticipantType() === Participant::USER) {
			$newType = Participant::MODERATOR;
		} elseif ($attendee->getParticipantType() === Participant::GUEST) {
			$newType = Participant::GUEST_MODERATOR;
		} elseif ($attendee->getParticipantType() === Participant::MODERATOR) {
			$newType = Participant::USER;
		} elseif ($attendee->getParticipantType() === Participant::GUEST_MODERATOR) {
			$newType = Participant::GUEST;
		} else {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->participantService->updateParticipantType($this->room, $targetParticipant, $newType);

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setPermissions(string $mode, int $permissions): DataResponse {
		if (!$this->roomService->setPermissions($this->room, $mode, Attendee::PERMISSIONS_MODIFY_SET, $permissions, true)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setAttendeePermissions(int $attendeeId, string $method, int $permissions): DataResponse {
		try {
			$targetParticipant = $this->participantService->getParticipantByAttendeeId($this->room, $attendeeId);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$result = $this->participantService->updatePermissions($this->room, $targetParticipant, $method, $permissions);
		} catch (ForbiddenException $e) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if (!$result) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setAllAttendeesPermissions(string $method, int $permissions): DataResponse {
		if (!$this->roomService->setPermissions($this->room, 'call', $method, $permissions, false)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function setLobby(int $state, ?int $timer = null): DataResponse {
		$timerDateTime = null;
		if ($timer !== null && $timer > 0) {
			try {
				$timerDateTime = $this->timeFactory->getDateTime('@' . $timer);
				$timerDateTime->setTimezone(new \DateTimeZone('UTC'));
			} catch (\Exception $e) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		}

		if ($this->room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Do not allow manual changing the lobby in breakout rooms
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if (!$this->roomService->setLobby($this->room, $state, $timerDateTime)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($state === Webinary::LOBBY_NON_MODERATORS) {
			$participants = $this->participantService->getParticipantsInCall($this->room);
			foreach ($participants as $participant) {
				if ($participant->hasModeratorPermissions()) {
					continue;
				}

				$this->participantService->changeInCall($this->room, $participant, Participant::FLAG_DISCONNECTED);
			}
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function setSIPEnabled(int $state): DataResponse {
		$user = $this->userManager->get($this->userId);
		if (!$user instanceof IUser) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->talkConfig->canUserEnableSIP($user)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		if (!$this->talkConfig->isSIPConfigured()) {
			return new DataResponse([], Http::STATUS_PRECONDITION_FAILED);
		}

		if (!$this->roomService->setSIPEnabled($this->room, $state)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	#[NoAdminRequired]
	#[RequireModeratorParticipant]
	public function resendInvitations(?int $attendeeId): DataResponse {
		/** @var Participant[] $participants */
		$participants = [];

		// targeting specific participant
		if ($attendeeId !== null) {
			try {
				$participants[] = $this->participantService->getParticipantByAttendeeId($this->room, $attendeeId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		} else {
			$participants = $this->participantService->getParticipantsByActorType($this->room, Attendee::ACTOR_EMAILS);
		}

		foreach ($participants as $participant) {
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS) {
				// generate PIN if applicable
				$this->participantService->generatePinForParticipant($this->room, $participant);
				$this->guestManager->sendEmailInvitation($this->room, $participant);
			}
		}
		return new DataResponse();
	}

	#[PublicPage]
	#[RequireModeratorParticipant]
	public function setMessageExpiration(int $seconds): DataResponse {
		if ($seconds < 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->roomService->setMessageExpiration($this->room, $seconds);
		} catch (\InvalidArgumentException $exception) {
			return new DataResponse(['error' => $exception], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}
}
