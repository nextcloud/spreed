<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserStatus\IManager;
use OCP\UserStatus\IUserStatus;

/**
 * @psalm-import-type TalkRoomLastMessage from ResponseDefinitions
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class RoomFormatter {
	public function __construct(
		protected Config $talkConfig,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected ChatManager $chatManager,
		protected MessageParser $messageParser,
		protected IConfig $serverConfig,
		protected ITimeFactory $timeFactory,
		protected IAppManager $appManager,
		protected IManager $userStatusManager,
		protected IUserManager $userManager,
		protected ProxyCacheMessageService $pcmService,
		protected UserConverter $userConverter,
		protected IL10N $l10n,
		protected ?string $userId,
	) {
	}

	/**
	 * @return TalkRoom
	 */
	public function formatRoom(
		string $responseFormat,
		array $commonReadMessages,
		Room $room,
		?Participant $currentParticipant,
		?array $statuses = null,
		bool $isSIPBridgeRequest = false,
		bool $isListingBreakoutRooms = false,
	): array {
		return $this->formatRoomV4(
			$responseFormat,
			$commonReadMessages,
			$room,
			$currentParticipant,
			$statuses,
			$isSIPBridgeRequest,
			$isListingBreakoutRooms,
		);
	}

	/**
	 * @param array<int, int> $commonReadMessages
	 * @return TalkRoom
	 */
	public function formatRoomV4(
		string $responseFormat,
		array $commonReadMessages,
		Room $room,
		?Participant $currentParticipant,
		?array $statuses,
		bool $isSIPBridgeRequest,
		bool $isListingBreakoutRooms,
	): array {
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
			'isCustomAvatar' => $this->avatarService->isCustomAvatar($room),
			'breakoutRoomMode' => BreakoutRoom::MODE_NOT_CONFIGURED,
			'breakoutRoomStatus' => BreakoutRoom::STATUS_STOPPED,
			'recordingConsent' => $this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_OPTIONAL ? $room->getRecordingConsent() : $this->talkConfig->recordingConsentRequired(),
			'lastUnreadMentionMessage' => '',
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
				'displayName' => $room->getDisplayName($isListingBreakoutRooms || $isSIPBridgeRequest || $this->userId === null ? '' : $this->userId, $isListingBreakoutRooms || $isSIPBridgeRequest),
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
			'recordingConsent' => $this->talkConfig->recordingConsentRequired() === RecordingService::CONSENT_REQUIRED_OPTIONAL ? $room->getRecordingConsent() : $this->talkConfig->recordingConsentRequired(),
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
		]);

		if ($currentParticipant->getAttendee()->getReadPrivacy() === Participant::PRIVACY_PUBLIC) {
			if (isset($commonReadMessages[$room->getId()])) {
				$roomData['lastCommonReadMessage'] = $commonReadMessages[$room->getId()];
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
			} elseif ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$roomData['notificationLevel'] = Participant::NOTIFY_ALWAYS;
			} else {
				$adminSetting = (int)$this->serverConfig->getAppValue('spreed', 'default_group_notification', (string)Participant::NOTIFY_DEFAULT);
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
			$roomData['canLeaveConversation'] = true;
			return $roomData;
		}

		$roomData['canStartCall'] = $currentParticipant->canStartCall($this->serverConfig);

		if ($attendee->getActorType() === Attendee::ACTOR_USERS) {
			$currentUser = $this->userManager->get($attendee->getActorId());
			if ($room->isFederatedConversation()) {
				$roomData['lastReadMessage'] = $attendee->getLastReadMessage();
				$roomData['unreadMention'] = (bool)$attendee->getLastMentionMessage();
				$roomData['unreadMentionDirect'] = (bool)$attendee->getLastMentionDirect();
				$roomData['unreadMessages'] = $attendee->getUnreadMessages();
			} elseif ($currentUser instanceof IUser) {
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
				if ($room->getLastMessage() && $lastReadMessage === (int)$room->getLastMessage()->getId()) {
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
				$roomData['lastUnreadMentionMessage'] = $lastMentionDirect ? $this->chatManager->getComment($room, (string)$lastMentionDirect)->getMessage() : '';
				$roomData['lastReadMessage'] = $lastReadMessage;

				$roomData['canDeleteConversation'] = $room->getType() !== Room::TYPE_ONE_TO_ONE
					&& $room->getType() !== Room::TYPE_ONE_TO_ONE_FORMER
					&& $currentParticipant->hasModeratorPermissions(false);
				$roomData['canLeaveConversation'] = $room->getType() !== Room::TYPE_NOTE_TO_SELF;
				$roomData['canEnableSIP'] =
					$this->talkConfig->isSIPConfigured()
					&& !preg_match(Room::SIP_INCOMPATIBLE_REGEX, $room->getToken())
					&& ($room->getType() === Room::TYPE_GROUP || $room->getType() === Room::TYPE_PUBLIC)
					&& $currentParticipant->hasModeratorPermissions(false)
					&& $this->talkConfig->canUserEnableSIP($currentUser);
			}
		} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			$lastReadMessage = $attendee->getLastReadMessage();
			$lastMention = $attendee->getLastMentionMessage();
			$lastMentionDirect = $attendee->getLastMentionDirect();
			$roomData['lastReadMessage'] = $lastReadMessage;
			$roomData['unreadMessages'] = $this->chatManager->getUnreadCount($room, $lastReadMessage);
			$roomData['unreadMention'] = $lastMention !== 0 && $lastReadMessage < $lastMention;
			$roomData['unreadMentionDirect'] = $lastMentionDirect !== 0 && $lastReadMessage < $lastMentionDirect;
			$roomData['lastUnreadMentionMessage'] = $lastMentionDirect ? $this->chatManager->getComment($room, (string)$lastMentionDirect)->getMessage() : '';
		} else {
			$roomData['lastReadMessage'] = $attendee->getLastReadMessage();
		}

		if ($room->isFederatedConversation()) {
			$roomData['remoteServer'] = $room->getRemoteServer();
			$roomData['remoteToken'] = $room->getRemoteToken();
		}

		// FIXME This should not be done, but currently all the clients use it to get the avatar of the user …
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			$participants = json_decode($room->getName(), true);
			foreach ($participants as $participant) {
				if ($participant !== $attendee->getActorId()) {
					$roomData['name'] = (string)$participant;

					if ($statuses === null
						&& $this->userId !== null
						&& $this->appManager->isEnabledForUser('user_status')) {
						$statuses = $this->userStatusManager->getUserStatuses([$participant]);
					}

					if (isset($statuses[$participant])) {
						$roomData['status'] = $statuses[$participant]->getStatus();
						$roomData['statusIcon'] = $statuses[$participant]->getIcon();
						$roomData['statusMessage'] = $statuses[$participant]->getMessage();
						$roomData['statusClearAt'] = $statuses[$participant]->getClearAt()?->getTimestamp();
					} elseif (!empty($statuses)) {
						$roomData['status'] = IUserStatus::OFFLINE;
						$roomData['statusIcon'] = null;
						$roomData['statusMessage'] = null;
						$roomData['statusClearAt'] = null;
					}
				}
			}
		}

		$roomData['lastMessage'] = [];
		$lastMessage = $room->getLastMessage();
		if (!$room->isFederatedConversation() && $lastMessage instanceof IComment) {
			$roomData['lastMessage'] = $this->formatLastMessage(
				$responseFormat,
				$room,
				$currentParticipant,
				$lastMessage,
			);
		} elseif ($room->isFederatedConversation()) {
			$roomData['lastCommonReadMessage'] = 0;
			try {
				$cachedMessage = $this->pcmService->findByRemote(
					$room->getRemoteServer(),
					$room->getRemoteToken(),
					$room->getLastMessageId(),
				);
				$roomData['lastMessage'] = $this->userConverter->convertAttendee($room, $cachedMessage->jsonSerialize(), 'actorType', 'actorId', 'actorDisplayName');
			} catch (DoesNotExistException) {
			}
		}

		if ($room->isFederatedConversation()) {
			$roomData['attendeeId'] = (int) $attendee->getRemoteId();
			$roomData['canLeaveConversation'] = true;
		}

		return $roomData;
	}

	/**
	 * @return TalkRoomLastMessage|array<empty>
	 */
	public function formatLastMessage(
		string $responseFormat,
		Room $room,
		Participant $participant,
		IComment $lastMessage,
	): array {
		$message = $this->messageParser->createMessage($room, $participant, $lastMessage, $this->l10n);
		$this->messageParser->parseMessage($message);

		if (!$message->getVisibility()) {
			return [];
		}

		$now = $this->timeFactory->getDateTime();
		$expireDate = $message->getComment()->getExpireDate();
		if ($expireDate instanceof \DateTime && $expireDate < $now) {
			return [];
		}

		return $message->toArray($responseFormat);
	}
}
