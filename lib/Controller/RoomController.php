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
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Events\UserEvent;
use OCA\Talk\Exceptions\ForbiddenException;
use OCA\Talk\Exceptions\InvalidPasswordException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\BreakoutRoomService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\SessionService;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\HintException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
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

	protected ?string $userId;
	protected IAppManager $appManager;
	protected TalkSession $session;
	protected IUserManager $userManager;
	protected IGroupManager $groupManager;
	protected Manager $manager;
	protected ICloudIdManager $cloudIdManager;
	protected RoomService $roomService;
	protected BreakoutRoomService $breakoutRoomService;
	protected ParticipantService $participantService;
	protected SessionService $sessionService;
	protected GuestManager $guestManager;
	protected IUserStatusManager $statusManager;
	protected ChatManager $chatManager;
	protected IEventDispatcher $dispatcher;
	protected MessageParser $messageParser;
	protected ITimeFactory $timeFactory;
	protected AvatarService $avatarService;
	protected IL10N $l10n;
	protected IConfig $config;
	protected Config $talkConfig;
	protected IThrottler $throttler;
	protected LoggerInterface $logger;

	protected array $commonReadMessages = [];

	public function __construct(string $appName,
								?string $UserId,
								IRequest $request,
								IAppManager $appManager,
								TalkSession $session,
								IUserManager $userManager,
								IGroupManager $groupManager,
								Manager $manager,
								RoomService $roomService,
								BreakoutRoomService $breakoutRoomService,
								ParticipantService $participantService,
								SessionService $sessionService,
								GuestManager $guestManager,
								IUserStatusManager $statusManager,
								ChatManager $chatManager,
								IEventDispatcher $dispatcher,
								MessageParser $messageParser,
								ITimeFactory $timeFactory,
								AvatarService $avatarService,
								IL10N $l10n,
								IConfig $config,
								Config $talkConfig,
								ICloudIdManager $cloudIdManager,
								IThrottler $throttler,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->session = $session;
		$this->appManager = $appManager;
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->manager = $manager;
		$this->roomService = $roomService;
		$this->breakoutRoomService = $breakoutRoomService;
		$this->participantService = $participantService;
		$this->sessionService = $sessionService;
		$this->guestManager = $guestManager;
		$this->statusManager = $statusManager;
		$this->chatManager = $chatManager;
		$this->dispatcher = $dispatcher;
		$this->messageParser = $messageParser;
		$this->timeFactory = $timeFactory;
		$this->avatarService = $avatarService;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->talkConfig = $talkConfig;
		$this->cloudIdManager = $cloudIdManager;
		$this->throttler = $throttler;
		$this->logger = $logger;
	}

	protected function getTalkHashHeader(): array {
		return [
			'X-Nextcloud-Talk-Hash' => sha1(
				$this->config->getSystemValueString('version') . '#' .
				$this->config->getAppValue('spreed', 'installed_version', '') . '#' .
				$this->config->getAppValue('spreed', 'stun_servers', '') . '#' .
				$this->config->getAppValue('spreed', 'turn_servers', '') . '#' .
				$this->config->getAppValue('spreed', 'signaling_servers', '') . '#' .
				$this->config->getAppValue('spreed', 'signaling_mode', '') . '#' .
				$this->config->getAppValue('spreed', 'allowed_groups', '') . '#' .
				$this->config->getAppValue('spreed', 'start_calls', '') . '#' .
				$this->config->getAppValue('spreed', 'start_conversations', '') . '#' .
				$this->config->getAppValue('spreed', 'has_reference_id', '') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_groups', '[]') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_dialin_info') . '#' .
				$this->config->getAppValue('spreed', 'sip_bridge_shared_secret') . '#' .
				$this->config->getAppValue('theming', 'cachebuster', '1')
			)];
	}

	/**
	 * Get all currently existent rooms which the user has joined
	 *
	 * @NoAdminRequired
	 *
	 * @param int $noStatusUpdate When the user status should not be automatically set to online set to 1 (default 0)
	 * @param bool $includeStatus
	 * @return DataResponse
	 */
	public function getRooms(int $noStatusUpdate = 0, bool $includeStatus = false): DataResponse {
		$event = new UserEvent($this->userId);
		$this->dispatcher->dispatch(self::EVENT_BEFORE_ROOMS_GET, $event);

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
		$readPrivacy = $this->talkConfig->getUserReadPrivacy($this->userId);
		if ($readPrivacy === Participant::PRIVACY_PUBLIC) {
			$roomIds = array_map(static function (Room $room) {
				return $room->getId();
			}, $rooms);
			$this->commonReadMessages = $this->participantService->getLastCommonReadChatMessageForMultipleRooms($roomIds);
		}

		$statuses = [];
		if ($this->userId !== null
			&& $includeStatus
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

		return new DataResponse($return, Http::STATUS_OK, $this->getTalkHashHeader());
	}

	/**
	 * Get listed rooms with optional search term
	 *
	 * @NoAdminRequired
	 *
	 * @param string $searchTerm search term
	 * @return DataResponse
	 */
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
	 *
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 * @BruteForceProtection(action=talkRoomToken)
	 *
	 * @return DataResponse
	 */
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

	/**
	 * @PublicPage
	 * @BruteForceProtection(action=talkRoomToken)
	 *
	 * @param string $token
	 * @return DataResponse
	 */
	public function getSingleRoom(string $token): DataResponse {
		try {
			$isSIPBridgeRequest = $this->validateSIPBridgeRequest($token);
		} catch (UnauthorizedException $e) {
			$ip = $this->request->getRemoteAddress();
			$action = 'talkSipBridgeSecret';
			$this->throttler->sleepDelay($ip, $action);
			$this->throttler->registerAttempt($action, $ip);
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
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

			return new DataResponse($this->formatRoom($room, $participant, [], $isSIPBridgeRequest), Http::STATUS_OK, $this->getTalkHashHeader());
		} catch (RoomNotFoundException $e) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
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
	 * @param string $data
	 * @return bool True if the request is from the SIP bridge and valid, false if not from SIP bridge
	 * @throws UnauthorizedException when the request tried to sign as SIP bridge but is not valid
	 */
	private function validateSIPBridgeRequest(string $data): bool {
		$random = $this->request->getHeader('TALK_SIPBRIDGE_RANDOM');
		$checksum = $this->request->getHeader('TALK_SIPBRIDGE_CHECKSUM');

		if ($random === '' && $checksum === '') {
			return false;
		}

		if (strlen($random) < 32) {
			throw new UnauthorizedException('Invalid random provided');
		}

		if (empty($checksum)) {
			throw new UnauthorizedException('Invalid checksum provided');
		}

		$secret = $this->talkConfig->getSIPSharedSecret();
		if (empty($secret)) {
			throw new UnauthorizedException('No shared SIP secret provided');
		}
		$hash = hash_hmac('sha256', $random . $data, $secret);

		if (hash_equals($hash, strtolower($checksum))) {
			return true;
		}

		throw new UnauthorizedException('Invalid HMAC provided');
	}

	protected function formatRoom(Room $room, ?Participant $currentParticipant, ?array $statuses = null, bool $isSIPBridgeRequest = false, bool $isListingBreakoutRooms = false): array {
		return $this->formatRoomV4($room, $currentParticipant, $statuses, $isSIPBridgeRequest, $isListingBreakoutRooms);
	}

	protected function formatRoomV4(Room $room, ?Participant $currentParticipant, ?array $statuses, bool $isSIPBridgeRequest, bool $isListingBreakoutRooms): array {
		$roomData = [
			'id' => $room->getId(),
			'token' => $room->getToken(),
			'type' => $room->getType(),
			'name' => '',
			'displayName' => '',
			'objectType' => '',
			'objectId' => '',
			'participantType' => Participant::GUEST,
			'participantFlags' => Participant::FLAG_DISCONNECTED,
			'readOnly' => Room::READ_WRITE,
			'hasPassword' => $room->hasPassword(),
			'hasCall' => false,
			'callStartTime' => 0,
			'callRecording' => Room::RECORDING_NONE,
			'canStartCall' => false,
			'lastActivity' => 0,
			'lastReadMessage' => 0,
			'unreadMessages' => 0,
			'unreadMention' => false,
			'unreadMentionDirect' => false,
			'isFavorite' => false,
			'canLeaveConversation' => false,
			'canDeleteConversation' => false,
			'notificationLevel' => Participant::NOTIFY_NEVER,
			'notificationCalls' => Participant::NOTIFY_CALLS_OFF,
			'lobbyState' => Webinary::LOBBY_NONE,
			'lobbyTimer' => 0,
			'lastPing' => 0,
			'sessionId' => '0',
			'lastMessage' => [],
			'sipEnabled' => Webinary::SIP_DISABLED,
			'actorType' => '',
			'actorId' => '',
			'attendeeId' => 0,
			'permissions' => Attendee::PERMISSIONS_CUSTOM,
			'attendeePermissions' => Attendee::PERMISSIONS_CUSTOM,
			'callPermissions' => Attendee::PERMISSIONS_CUSTOM,
			'defaultPermissions' => Attendee::PERMISSIONS_CUSTOM,
			'canEnableSIP' => false,
			'attendeePin' => '',
			'description' => '',
			'lastCommonReadMessage' => 0,
			'listable' => Room::LISTABLE_NONE,
			'callFlag' => Participant::FLAG_DISCONNECTED,
			'messageExpiration' => 0,
			'avatarVersion' => $this->avatarService->getAvatarVersion($room),
			'breakoutRoomMode' => BreakoutRoom::MODE_NOT_CONFIGURED,
			'breakoutRoomStatus' => BreakoutRoom::STATUS_STOPPED,
			'showHistory' => false,
		];

		$lastActivity = $room->getLastActivity();
		if ($lastActivity instanceof \DateTimeInterface) {
			$lastActivity = $lastActivity->getTimestamp();
		} else {
			$lastActivity = 0;
		}

		$lobbyTimer = $room->getLobbyTimer();
		if ($lobbyTimer instanceof \DateTimeInterface) {
			$lobbyTimer = $lobbyTimer->getTimestamp();
		} else {
			$lobbyTimer = 0;
		}

		if ($isSIPBridgeRequest
			|| ($isListingBreakoutRooms && !$currentParticipant instanceof Participant)
			|| ($room->getListable() !== Room::LISTABLE_NONE && !$currentParticipant instanceof Participant)
		) {
			return array_merge($roomData, [
				'name' => $room->getName(),
				'displayName' => $room->getDisplayName($isSIPBridgeRequest || $this->userId === null ? '' : $this->userId),
				'objectType' => $room->getObjectType(),
				'objectId' => $room->getObjectId(),
				'readOnly' => $room->getReadOnly(),
				'hasCall' => $room->getActiveSince() instanceof \DateTimeInterface,
				'lastActivity' => $lastActivity,
				'callFlag' => $room->getCallFlag(),
				'lobbyState' => $room->getLobbyState(),
				'lobbyTimer' => $lobbyTimer,
				'sipEnabled' => $room->getSIPEnabled(),
				'listable' => $room->getListable(),
				'breakoutRoomMode' => $room->getBreakoutRoomMode(),
				'breakoutRoomStatus' => $room->getBreakoutRoomStatus(),
				'callStartTime' => $room->getActiveSince() instanceof \DateTimeInterface ? $room->getActiveSince()->getTimestamp() : 0,
				'callRecording' => $room->getCallRecording(),
			]);
		}

		if (!$currentParticipant instanceof Participant) {
			return $roomData;
		}

		$attendee = $currentParticipant->getAttendee();
		$userId = $attendee->getActorType() === Attendee::ACTOR_USERS ? $attendee->getActorId() : '';

		$roomData = array_merge($roomData, [
			'name' => $room->getName(),
			'displayName' => $room->getDisplayName($userId),
			'objectType' => $room->getObjectType(),
			'objectId' => $room->getObjectId(),
			'participantType' => $attendee->getParticipantType(),
			'readOnly' => $room->getReadOnly(),
			'hasCall' => $room->getActiveSince() instanceof \DateTimeInterface,
			'callStartTime' => $room->getActiveSince() instanceof \DateTimeInterface ? $room->getActiveSince()->getTimestamp() : 0,
			'callRecording' => $room->getCallRecording(),
			'lastActivity' => $lastActivity,
			'callFlag' => $room->getCallFlag(),
			'isFavorite' => $attendee->isFavorite(),
			'notificationLevel' => $attendee->getNotificationLevel(),
			'notificationCalls' => $attendee->getNotificationCalls(),
			'lobbyState' => $room->getLobbyState(),
			'lobbyTimer' => $lobbyTimer,
			'actorType' => $attendee->getActorType(),
			'actorId' => $attendee->getActorId(),
			'attendeeId' => $attendee->getId(),
			'permissions' => $currentParticipant->getPermissions(),
			'attendeePermissions' => $attendee->getPermissions(),
			'callPermissions' => $room->getCallPermissions(),
			'defaultPermissions' => $room->getDefaultPermissions(),
			'description' => $room->getDescription(),
			'listable' => $room->getListable(),
			'messageExpiration' => $room->getMessageExpiration(),
			'breakoutRoomMode' => $room->getBreakoutRoomMode(),
			'breakoutRoomStatus' => $room->getBreakoutRoomStatus(),
			'showHistory' => $room->getShowHistory(),
		]);

		if ($currentParticipant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			if (isset($this->commonReadMessages[$room->getId()])) {
				$roomData['lastCommonReadMessage'] = $this->commonReadMessages[$room->getId()];
			} else {
				$roomData['lastCommonReadMessage'] = $this->chatManager->getLastCommonReadMessage($room);
			}
		}

		if ($this->talkConfig->isSIPConfigured()) {
			$roomData['sipEnabled'] = $room->getSIPEnabled();
			if ($room->getSIPEnabled() !== Webinary::SIP_DISABLED) {
				// Generate a PIN if the attendee is a user and doesn't have one.
				$this->participantService->generatePinForParticipant($room, $currentParticipant);

				$roomData['attendeePin'] = $attendee->getPin();
			}
		}

		$session = $currentParticipant->getSession();
		if ($session instanceof Session) {
			$roomData = array_merge($roomData, [
				'participantFlags' => $session->getInCall(),
				'lastPing' => $session->getLastPing(),
				'sessionId' => $session->getSessionId(),
			]);
		}

		if ($roomData['notificationLevel'] === Participant::NOTIFY_DEFAULT) {
			if ($currentParticipant->isGuest()) {
				$roomData['notificationLevel'] = Participant::NOTIFY_NEVER;
			} elseif ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$roomData['notificationLevel'] = Participant::NOTIFY_ALWAYS;
			} else {
				$adminSetting = (int) $this->config->getAppValue('spreed', 'default_group_notification', (string) Participant::NOTIFY_DEFAULT);
				if ($adminSetting === Participant::NOTIFY_DEFAULT) {
					$roomData['notificationLevel'] = Participant::NOTIFY_MENTION;
				} else {
					$roomData['notificationLevel'] = $adminSetting;
				}
			}
		}

		if ($room->getLobbyState() === Webinary::LOBBY_NON_MODERATORS &&
			!$currentParticipant->hasModeratorPermissions() &&
			!($currentParticipant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
			// No participants and chat messages for users in the lobby.
			$roomData['hasCall'] = false;
			return $roomData;
		}

		$roomData['canStartCall'] = $currentParticipant->canStartCall($this->config);

		if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
			$currentUser = $this->userManager->get($attendee->getActorId());
			if ($currentUser instanceof IUser) {
				$lastReadMessage = $attendee->getLastReadMessage();
				if ($lastReadMessage === -1) {
					/*
					 * Because the migration from the old comment_read_markers was
					 * not possible in a programmatic way with a reasonable O(1) or O(n)
					 * but only with O(user×chat), we do the conversion here.
					 */
					$lastReadMessage = $this->chatManager->getLastReadMessageFromLegacy($room, $currentUser);
					$this->participantService->updateLastReadMessage($currentParticipant, $lastReadMessage);
				}
				if ($room->getLastMessage() && $lastReadMessage === (int) $room->getLastMessage()->getId()) {
					// When the last message is the last read message, there are no unread messages,
					// so we can save the query.
					$roomData['unreadMessages'] = 0;
				} else {
					$roomData['unreadMessages'] = $this->chatManager->getUnreadCount($room, $lastReadMessage);
				}

				$lastMention = $attendee->getLastMentionMessage();
				$lastMentionDirect = $attendee->getLastMentionDirect();
				$roomData['unreadMention'] = $lastMention !== 0 && $lastReadMessage < $lastMention;
				$roomData['unreadMentionDirect'] = $lastMentionDirect !== 0 && $lastReadMessage < $lastMentionDirect;
				$roomData['lastReadMessage'] = $lastReadMessage;

				$roomData['canDeleteConversation'] = $room->getType() !== Room::TYPE_ONE_TO_ONE
					&& $currentParticipant->hasModeratorPermissions(false);
				$roomData['canLeaveConversation'] = true;
				$roomData['canEnableSIP'] =
					$this->talkConfig->isSIPConfigured()
					&& !preg_match(Room::SIP_INCOMPATIBLE_REGEX, $room->getToken())
					&& ($room->getType() === Room::TYPE_GROUP || $room->getType() === Room::TYPE_PUBLIC)
					&& $currentParticipant->hasModeratorPermissions(false)
					&& $this->talkConfig->canUserEnableSIP($currentUser);
			}
		}

		// FIXME This should not be done, but currently all the clients use it to get the avatar of the user …
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			$participants = json_decode($room->getName(), true);
			foreach ($participants as $participant) {
				if ($participant !== $attendee->getActorId()) {
					$roomData['name'] = $participant;

					if ($statuses === null
						&& $this->userId !== null
						&& $this->appManager->isEnabledForUser('user_status')) {
						$statuses = $this->statusManager->getUserStatuses([$participant]);
					}

					if (isset($statuses[$participant])) {
						$roomData['status'] = $statuses[$participant]->getStatus();
						$roomData['statusIcon'] = $statuses[$participant]->getIcon();
						$roomData['statusMessage'] = $statuses[$participant]->getMessage();
						$roomData['statusClearAt'] = $statuses[$participant]->getClearAt();
					} elseif (!empty($statuses)) {
						$roomData['status'] = IUserStatus::OFFLINE;
						$roomData['statusIcon'] = null;
						$roomData['statusMessage'] = null;
						$roomData['statusClearAt'] = null;
					}
				}
			}
		}

		$lastMessage = $room->getLastMessage();
		if ($lastMessage instanceof IComment) {
			$roomData['lastMessage'] = $this->formatLastMessage($room, $currentParticipant, $lastMessage);
		} else {
			$roomData['lastMessage'] = [];
		}

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

		return $message->toArray($this->getResponseFormat());
	}

	/**
	 * Initiates a one-to-one video call from the current user to the recipient
	 *
	 * @NoAdminRequired
	 *
	 * @param int $roomType
	 * @param string $invite
	 * @param string $roomName
	 * @param string $source
	 * @return DataResponse
	 */
	public function createRoom(int $roomType, string $invite = '', string $roomName = '', string $source = ''): DataResponse {
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
					return $this->createEmptyRoom($roomName, false);
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
	 * @NoAdminRequired
	 *
	 * @param string $targetUserId
	 * @return DataResponse
	 */
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
	 * @NoAdminRequired
	 *
	 * @param string $targetGroupName
	 * @return DataResponse
	 */
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
	 * @NoAdminRequired
	 *
	 * @param string $targetCircleId
	 * @return DataResponse
	 */
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

	/**
	 * @NoAdminRequired
	 *
	 * @param string $roomName
	 * @param bool $public
	 * @return DataResponse
	 */
	protected function createEmptyRoom(string $roomName, bool $public = true): DataResponse {
		$currentUser = $this->userManager->get($this->userId);
		if (!$currentUser instanceof IUser) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$roomType = $public ? Room::TYPE_PUBLIC : Room::TYPE_GROUP;

		// Create the room
		try {
			$room = $this->roomService->createConversation($roomType, $roomName, $currentUser);
		} catch (InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($room, $this->participantService->getParticipant($room, $currentUser->getUID(), false)), Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @return DataResponse
	 */
	public function addToFavorites(): DataResponse {
		$this->participantService->updateFavoriteStatus($this->participant, true);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @return DataResponse
	 */
	public function removeFromFavorites(): DataResponse {
		$this->participantService->updateFavoriteStatus($this->participant, false);
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
		try {
			$this->participantService->updateNotificationLevel($this->participant, $level);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInParticipant
	 *
	 * @param int $level
	 * @return DataResponse
	 */
	public function setNotificationCalls(int $level): DataResponse {
		try {
			$this->participantService->updateNotificationCalls($this->participant, $level);
		} catch (\InvalidArgumentException $e) {
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
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$roomName = trim($roomName);

		if ($roomName === '' || mb_strlen($roomName) > 255) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->roomService->setName($this->room, $roomName);
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string $description
	 * @return DataResponse
	 */
	public function setDescription(string $description): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->roomService->setDescription($this->room, $description);
		} catch (\LengthException $exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function deleteRoom(): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->roomService->deleteRoom($this->room);

		return new DataResponse([]);
	}

	/**
	 * @PublicPage
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 *
	 * @param bool $includeStatus
	 * @return DataResponse
	 */
	public function getParticipants(bool $includeStatus = false): DataResponse {
		if ($this->participant->getAttendee()->getParticipantType() === Participant::GUEST) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$maxPingAge = $this->timeFactory->getTime() - Session::SESSION_TIMEOUT_KILL;
		$participants = $this->participantService->getSessionsAndParticipantsForRoom($this->room);
		$results = $headers = $statuses = [];

		if ($this->userId !== null
			&& $includeStatus
			&& count($participants) < 100
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
					$result['statusClearAt'] = $statuses[$userId]->getClearAt();
				} elseif (isset($headers['X-Nextcloud-Has-User-Statuses'])) {
					$result['status'] = IUserStatus::OFFLINE;
					$result['statusIcon'] = null;
					$result['statusMessage'] = null;
					$result['statusClearAt'] = null;
				}
			} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GUESTS) {
				if ($result['lastPing'] <= $maxPingAge) {
					$cleanGuests = true;
					continue;
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

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @param string $newParticipant
	 * @param string $source
	 * @return DataResponse
	 */
	public function addParticipantToRoom(string $newParticipant, string $source = 'users'): DataResponse {
		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE || $this->room->getObjectType() === 'share:password') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
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

		// add the remaining users in batch
		$this->participantService->addUsers($this->room, $participantsToAdd, $addedBy);

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
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
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

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param int $attendeeId
	 * @return DataResponse
	 */
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

		if ($this->room->getType() === Room::TYPE_ONE_TO_ONE) {
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

	/**
	 * @NoAdminRequired
	 * @RequireLoggedInModeratorParticipant
	 *
	 * @return DataResponse
	 */
	public function makePublic(): DataResponse {
		if (!$this->roomService->setType($this->room, Room::TYPE_PUBLIC)) {
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
		if (!$this->roomService->setType($this->room, Room::TYPE_GROUP)) {
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

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $scope
	 * @return DataResponse
	 */
	public function setListable(int $scope): DataResponse {
		if (!$this->roomService->setListable($this->room, $scope)) {
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
		if ($this->room->getType() !== Room::TYPE_PUBLIC) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->roomService->setPassword($this->room, $password);
		} catch (HintException $e) {
			return new DataResponse([
				'message' => $e->getHint(),
			], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @BruteForceProtection(action=talkRoomPassword)
	 *
	 * @param string $token
	 * @param string $password
	 * @param bool $force
	 * @return DataResponse
	 */
	public function joinRoom(string $token, string $password = '', bool $force = true): DataResponse {
		$sessionId = $this->session->getSessionForRoom($token);
		try {
			$room = $this->manager->getRoomForUserByToken($token, $this->userId, $sessionId);
		} catch (RoomNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		/** @var Participant|null $previousSession */
		$previousParticipant = null;
		/** @var Session|null $previousSession */
		$previousSession = null;
		if ($this->userId !== null) {
			try {
				$previousParticipant = $this->participantService->getParticipant($room, $this->userId, $sessionId);
				$previousSession = $previousParticipant->getSession();
			} catch (ParticipantNotFoundException $e) {
			}
		} else {
			try {
				$previousParticipant = $this->participantService->getParticipantBySession($room, $sessionId);
				$previousSession = $previousParticipant->getSession();
			} catch (ParticipantNotFoundException $e) {
			}
		}

		if ($previousSession instanceof Session && $previousSession->getSessionId() !== '0') {
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

		$user = $this->userManager->get($this->userId);
		try {
			$result = $this->roomService->verifyPassword($room, (string) $this->session->getPasswordForRoom($token));
			if ($user instanceof IUser) {
				$participant = $this->participantService->joinRoom($this->roomService, $room, $user, $password, $result['result']);
				$this->participantService->generatePinForParticipant($room, $participant);
			} else {
				$participant = $this->participantService->joinRoomAsNewGuest($this->roomService, $room, $password, $result['result'], $previousParticipant);
			}
		} catch (InvalidPasswordException $e) {
			$response = new DataResponse([], Http::STATUS_FORBIDDEN);
			$response->throttle(['token' => $token]);
			return $response;
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle(['token' => $token]);
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

	/**
	 * @PublicPage
	 * @RequireRoom
	 * @BruteForceProtection(action=talkSipBridgeSecret)
	 *
	 * @param string $pin
	 * @return DataResponse
	 */
	public function getParticipantByDialInPin(string $pin): DataResponse {
		try {
			if (!$this->validateSIPBridgeRequest($this->room->getToken())) {
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle();
				return $response;
			}
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle();
			return $response;
		}

		try {
			$participant = $this->participantService->getParticipantByPin($this->room, $pin);
		} catch (ParticipantNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse($this->formatRoom($this->room, $participant));
	}

	/**
	 * @PublicPage
	 * @RequireRoom
	 * @BruteForceProtection(action=talkSipBridgeSecret)
	 *
	 * @return DataResponse
	 */
	public function createGuestByDialIn(): DataResponse {
		try {
			if (!$this->validateSIPBridgeRequest($this->room->getToken())) {
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle();
				return $response;
			}
		} catch (UnauthorizedException $e) {
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle();
			return $response;
		}

		if ($this->room->getSIPEnabled() !== Webinary::SIP_ENABLED_NO_PIN) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$participant = $this->participantService->joinRoomAsNewGuest($this->roomService, $this->room, '', true);

		return new DataResponse($this->formatRoom($this->room, $participant));
	}

	/**
	 * @PublicPage
	 *
	 * @param string $token
	 * @return DataResponse
	 */
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

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param int $attendeeId
	 * @return DataResponse
	 */
	public function promoteModerator(int $attendeeId): DataResponse {
		return $this->changeParticipantType($attendeeId, true);
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param int $attendeeId
	 * @return DataResponse
	 */
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

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param int $permissions
	 * @return DataResponse
	 */
	public function setPermissions(string $mode, int $permissions): DataResponse {
		if (!$this->roomService->setPermissions($this->room, $mode, Attendee::PERMISSIONS_MODIFY_SET, $permissions, true)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param int $attendeeId
	 * @param string $method
	 * @param int $permissions
	 * @return DataResponse
	 */
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

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 *
	 * @param string $method
	 * @param int $permissions
	 * @return DataResponse
	 */
	public function setAllAttendeesPermissions(string $method, int $permissions): DataResponse {
		if (!$this->roomService->setPermissions($this->room, 'call', $method, $permissions, false)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->formatRoom($this->room, $this->participant));
	}

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $state
	 * @param int|null $timer
	 * @return DataResponse
	 */
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

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int $state
	 * @return DataResponse
	 */
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

	/**
	 * @NoAdminRequired
	 * @RequireModeratorParticipant
	 *
	 * @param int|null $attendeeId attendee id
	 * @return DataResponse
	 */
	public function resendInvitations(?int $attendeeId): DataResponse {
		$participants = [];

		// targeting specific participant
		if ($attendeeId !== null) {
			try {
				$participants[] = $this->participantService->getParticipantByAttendeeId($this->room, $attendeeId);
			} catch (ParticipantNotFoundException $e) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		} else {
			$participants = $this->participantService->getActorsByType($this->room, Attendee::ACTOR_EMAILS);
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

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 */
	public function setMessageExpiration(int $seconds): DataResponse {
		if ($seconds < 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
		$this->roomService->setMessageExpiration($this->room, $seconds);
		return new DataResponse();
	}

	/**
	 * @PublicPage
	 * @RequireModeratorParticipant
	 */
	public function setShowHistory(int $showHistory): DataResponse {
		$this->roomService->setShowHistory($this->room, $showHistory);

		return new DataResponse();
	}
}
