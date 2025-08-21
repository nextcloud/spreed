<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCA\Talk\Events\BeforeSignalingRoomPropertiesSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\SelectHelper;
use OCA\Talk\Model\Session;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RecordingService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\Server;

class Room {
	/**
	 * Regex that matches SIP incompatible rooms:
	 * 1. duplicate digit: …11…
	 * 2. leading zero: 0…
	 * 3. non-digit: …a…
	 */
	public const SIP_INCOMPATIBLE_REGEX = '/((\d)(?=\2+)|^0|\D)/';

	public const TYPE_UNKNOWN = -1;
	public const TYPE_ONE_TO_ONE = 1;
	public const TYPE_GROUP = 2;
	public const TYPE_PUBLIC = 3;
	public const TYPE_CHANGELOG = 4;
	public const TYPE_ONE_TO_ONE_FORMER = 5;
	public const TYPE_NOTE_TO_SELF = 6;

	public const OBJECT_TYPE_EMAIL = 'emails';
	public const OBJECT_TYPE_EVENT = 'event';
	public const OBJECT_TYPE_EXTENDED_CONVERSATION = 'extended_conversation';
	public const OBJECT_TYPE_FILE = 'file';
	public const OBJECT_TYPE_INSTANT_MEETING = 'instant_meeting';
	public const OBJECT_TYPE_NOTE_TO_SELF = 'note_to_self';
	/**
	 * @deprecated No longer used for new conversations
	 */
	public const OBJECT_TYPE_PHONE_LEGACY = 'phone';
	public const OBJECT_TYPE_PHONE_PERSIST = 'phone_persist';
	public const OBJECT_TYPE_PHONE_TEMPORARY = 'phone_temporary';
	public const OBJECT_TYPE_SAMPLE = 'sample';
	public const OBJECT_TYPE_VIDEO_VERIFICATION = 'share:password';

	public const OBJECT_ID_PHONE_OUTGOING = 'phone';
	public const OBJECT_ID_PHONE_INCOMING = 'direct-dialin';

	public const RECORDING_NONE = 0;
	public const RECORDING_VIDEO = 1;
	public const RECORDING_AUDIO = 2;
	public const RECORDING_VIDEO_STARTING = 3;
	public const RECORDING_AUDIO_STARTING = 4;
	public const RECORDING_FAILED = 5;


	public const READ_WRITE = 0;
	public const READ_ONLY = 1;

	/**
	 * Only visible when joined
	 */
	public const LISTABLE_NONE = 0;

	/**
	 * Searchable by all regular users and moderators, even when not joined, excluding users created with the Guests app
	 */
	public const LISTABLE_USERS = 1;

	/**
	 * Searchable by everyone, which includes users created with the Guests app, even when not joined
	 */
	public const LISTABLE_ALL = 2;

	public const START_CALL_EVERYONE = 0;
	public const START_CALL_USERS = 1;
	public const START_CALL_MODERATORS = 2;
	public const START_CALL_NOONE = 3;

	public const DESCRIPTION_MAXIMUM_LENGTH = 2000;

	public const HAS_FEDERATION_NONE = 0;
	public const HAS_FEDERATION_TALKv1 = 1;

	public const MENTION_PERMISSIONS_EVERYONE = 0;
	public const MENTION_PERMISSIONS_MODERATORS = 1;

	protected ?string $currentUser = null;
	protected ?Participant $participant = null;

	/**
	 * @psalm-param self::TYPE_* $type
	 * @psalm-param RecordingService::CONSENT_REQUIRED_* $recordingConsent
	 * @psalm-param int-mask-of<self::HAS_FEDERATION_*> $hasFederation
	 * @psalm-param self::RECORDING_* $callRecording
	 * @psalm-param self::MENTION_PERMISSIONS_* $mentionPermissions
	 */
	public function __construct(
		private Manager $manager,
		private IDBConnection $db,
		private IEventDispatcher $dispatcher,
		private ITimeFactory $timeFactory,
		private int $id,
		private int $type,
		private int $readOnly,
		private int $listable,
		private int $messageExpiration,
		private int $lobbyState,
		private int $sipEnabled,
		private ?int $assignedSignalingServer,
		private string $token,
		private string $name,
		private string $description,
		private string $password,
		private string $avatar,
		private string $remoteServer,
		private string $remoteToken,
		private int $defaultPermissions,
		private int $callPermissions,
		private int $callFlag,
		private ?\DateTime $activeSince,
		private ?\DateTime $lastActivity,
		private int $lastMessageId,
		private ?IComment $lastMessage,
		private ?\DateTime $lobbyTimer,
		private string $objectType,
		private string $objectId,
		private int $breakoutRoomMode,
		private int $breakoutRoomStatus,
		private int $callRecording,
		private int $recordingConsent,
		private int $hasFederation,
		private int $mentionPermissions,
		private string $liveTranscriptionLanguageId,
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return int
	 * @psalm-return Room::TYPE_*
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * @param int $type
	 * @psalm-param Room::TYPE_* $type
	 */
	public function setType(int $type): void {
		$this->type = $type;
	}

	public function getReadOnly(): int {
		return $this->readOnly;
	}

	/**
	 * @param int $readOnly Currently it is only allowed to change between
	 *                      `self::READ_ONLY` and `self::READ_WRITE`
	 *                      Also it's only allowed on rooms of type
	 *                      `self::TYPE_GROUP` and `self::TYPE_PUBLIC`
	 */
	public function setReadOnly(int $readOnly): void {
		$this->readOnly = $readOnly;
	}

	public function getListable(): int {
		return $this->listable;
	}

	/**
	 * @param int $newState New listable scope from self::LISTABLE_*
	 *                      Also it's only allowed on rooms of type
	 *                      `self::TYPE_GROUP` and `self::TYPE_PUBLIC`
	 */
	public function setListable(int $newState): void {
		$this->listable = $newState;
	}

	public function getMessageExpiration(): int {
		return $this->messageExpiration;
	}

	public function setMessageExpiration(int $messageExpiration): void {
		$this->messageExpiration = $messageExpiration;
	}

	public function getLobbyState(bool $validateTime = true): int {
		if ($validateTime) {
			$this->validateTimer();
		}
		return $this->lobbyState;
	}

	public function setLobbyState(int $lobbyState): void {
		$this->lobbyState = $lobbyState;
	}

	public function getLobbyTimer(bool $validateTime = true): ?\DateTime {
		if ($validateTime) {
			$this->validateTimer();
		}
		return $this->lobbyTimer;
	}

	public function setLobbyTimer(?\DateTime $lobbyTimer): void {
		$this->lobbyTimer = $lobbyTimer;
	}

	protected function validateTimer(): void {
		if ($this->lobbyTimer !== null && $this->lobbyTimer < $this->timeFactory->getDateTime()) {
			/** @var RoomService $roomService */
			$roomService = Server::get(RoomService::class);
			$roomService->setLobby($this, Webinary::LOBBY_NONE, null, true);
		}
	}

	public function getSIPEnabled(): int {
		return $this->sipEnabled;
	}

	public function setSIPEnabled(int $sipEnabled): void {
		$this->sipEnabled = $sipEnabled;
	}

	public function getAssignedSignalingServer(): ?int {
		return $this->assignedSignalingServer;
	}

	public function setAssignedSignalingServer(?int $assignedSignalingServer): void {
		$this->assignedSignalingServer = $assignedSignalingServer;
	}

	public function getToken(): string {
		return $this->token;
	}

	public function getName(): string {
		if ($this->type === self::TYPE_ONE_TO_ONE) {
			if ($this->name === '') {
				// TODO use DI
				$participantService = Server::get(ParticipantService::class);
				// Fill the room name with the participants for 1-to-1 conversations
				$users = $participantService->getParticipantUserIds($this);
				sort($users);
				/** @var RoomService $roomService */
				$roomService = Server::get(RoomService::class);
				$roomService->setName($this, json_encode($users), '');
			} elseif (!str_starts_with($this->name, '["')) {
				// TODO use DI
				$participantService = Server::get(ParticipantService::class);
				// Not the json array, but the old fallback when someone left
				$users = $participantService->getParticipantUserIds($this);
				if (count($users) !== 2) {
					$users[] = $this->name;
				}
				sort($users);
				/** @var RoomService $roomService */
				$roomService = Server::get(RoomService::class);
				$roomService->setName($this, json_encode($users), '');
			}
		}
		return $this->name;
	}

	public function setName(string $name): void {
		$this->name = $name;
	}

	public function getSecondParticipant(string $userId): string {
		if ($this->getType() !== self::TYPE_ONE_TO_ONE) {
			throw new \InvalidArgumentException('Not a one-to-one room');
		}
		$participants = json_decode($this->getName(), true);

		foreach ($participants as $uid) {
			if ($uid !== $userId) {
				return $uid;
			}
		}

		return $this->getName();
	}

	public function getDisplayName(string $userId, bool $forceName = false): string {
		return $this->manager->resolveRoomDisplayName($this, $userId, $forceName);
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function setDescription(string $description): void {
		$this->description = $description;
	}

	public function resetActiveSince(): void {
		$this->activeSince = null;
		$this->callFlag = Participant::FLAG_DISCONNECTED;
	}

	public function getDefaultPermissions(): int {
		return $this->defaultPermissions;
	}

	public function setDefaultPermissions(int $defaultPermissions): void {
		$this->defaultPermissions = $defaultPermissions;
	}

	/**
	 * @deprecated
	 */
	public function getCallPermissions(): int {
		return Attendee::PERMISSIONS_DEFAULT;
	}

	/**
	 * @deprecated
	 */
	public function setCallPermissions(int $callPermissions): void {
		$this->callPermissions = $callPermissions;
	}

	public function getCallFlag(): int {
		return $this->callFlag;
	}

	public function getActiveSince(): ?\DateTime {
		return $this->activeSince;
	}

	public function getLastActivity(): ?\DateTime {
		return $this->lastActivity;
	}

	public function setLastActivity(\DateTime $now): void {
		$this->lastActivity = $now;
	}

	public function getLastMessageId(): int {
		return $this->lastMessageId;
	}

	public function setLastMessageId(int $lastMessageId): void {
		$this->lastMessageId = $lastMessageId;
	}

	public function getLastMessage(): ?IComment {
		if ($this->isFederatedConversation()) {
			return null;
		}

		if ($this->lastMessageId && $this->lastMessage === null) {
			$this->lastMessage = $this->manager->loadLastCommentInfo($this->lastMessageId);
			if ($this->lastMessage === null) {
				$this->lastMessageId = 0;
			}
		}

		return $this->lastMessage;
	}

	public function setLastMessage(IComment $message): void {
		$this->lastMessage = $message;
		$this->lastMessageId = (int)$message->getId();
	}

	public function getObjectType(): string {
		return $this->objectType;
	}

	public function getObjectId(): string {
		return $this->objectId;
	}

	public function hasPassword(): bool {
		return $this->password !== '';
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function setPassword(string $password): void {
		$this->password = $password;
	}

	public function setAvatar(string $avatar): void {
		$this->avatar = $avatar;
	}

	public function getAvatar(): string {
		return $this->avatar;
	}

	public function getRemoteServer(): string {
		return $this->remoteServer;
	}

	/**
	 * Whether the conversation is a "proxy conversation" or the original hosted conversation
	 * @return bool
	 */
	public function isFederatedConversation(): bool {
		return $this->remoteServer !== '';
	}

	public function getRemoteToken(): string {
		return $this->remoteToken;
	}

	public function setParticipant(?string $userId, Participant $participant): void {
		// FIXME Also used with cloudId, need actorType checking?
		$this->currentUser = $userId;
		$this->participant = $participant;
	}

	/**
	 * Return the room properties to send to the signaling server.
	 *
	 * @param string $userId
	 * @param bool $roomModified
	 * @return array
	 */
	public function getPropertiesForSignaling(string $userId, bool $roomModified = true): array {
		$properties = [
			'name' => $this->getDisplayName($userId),
			'type' => $this->getType(),
			'lobby-state' => $this->getLobbyState(),
			'lobby-timer' => $this->getLobbyTimer(),
			'read-only' => $this->getReadOnly(),
			'listable' => $this->getListable(),
			'active-since' => $this->getActiveSince(),
			'sip-enabled' => $this->getSIPEnabled(),
		];

		if ($roomModified) {
			$properties['description'] = $this->getDescription();
		} else {
			$properties['participant-list'] = 'refresh';
		}

		$event = new BeforeSignalingRoomPropertiesSentEvent($this, $userId, $properties);
		$this->dispatcher->dispatchTyped($event);
		return $event->getProperties();
	}

	/**
	 * @param string|null $userId
	 * @param string|null|false $sessionId Set to false if you don't want to load a session (and save resources),
	 *                                     string to try loading a specific session
	 *                                     null to try loading "any"
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 * @deprecated
	 */
	public function getParticipant(?string $userId, $sessionId = null): Participant {
		if (!is_string($userId) || $userId === '') {
			throw new ParticipantNotFoundException('Not a user');
		}

		if ($this->currentUser === $userId && $this->participant instanceof Participant) {
			if (!$sessionId
				|| ($this->participant->getSession() instanceof Session
					&& $this->participant->getSession()->getSessionId() === $sessionId)) {
				return $this->participant;
			}
		}

		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->where($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($this->getId())))
			->setMaxResults(1);

		if ($sessionId !== false) {
			if ($sessionId !== null) {
				$helper->selectSessionsTable($query);
				$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
					$query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)),
					$query->expr()->eq('a.id', 's.attendee_id')
				));
			} else {
				$helper->selectSessionsTable($query); // FIXME PROBLEM
				$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq('a.id', 's.attendee_id'));
			}
		}

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new ParticipantNotFoundException('User is not a participant');
		}

		if ($this->currentUser === $userId) {
			$this->participant = $this->manager->createParticipantObject($this, $row);
			return $this->participant;
		}

		return $this->manager->createParticipantObject($this, $row);
	}

	public function setActiveSince(\DateTime $since, int $callFlag): void {
		if (!$this->activeSince) {
			$this->activeSince = $since;
		}
		$this->callFlag |= $callFlag;
	}

	public function getBreakoutRoomMode(): int {
		return $this->breakoutRoomMode;
	}

	public function setBreakoutRoomMode(int $mode): void {
		$this->breakoutRoomMode = $mode;
	}

	public function getBreakoutRoomStatus(): int {
		return $this->breakoutRoomStatus;
	}

	public function setBreakoutRoomStatus(int $status): void {
		$this->breakoutRoomStatus = $status;
	}

	/**
	 * @psalm-return self::RECORDING_*
	 */
	public function getCallRecording(): int {
		return $this->callRecording;
	}

	/**
	 * @psalm-param self::RECORDING_* $callRecording
	 */
	public function setCallRecording(int $callRecording): void {
		$this->callRecording = $callRecording;
	}

	/**
	 * @return RecordingService::CONSENT_REQUIRED_*
	 */
	public function getRecordingConsent(): int {
		return $this->recordingConsent;
	}

	/**
	 * @param int $recordingConsent
	 * @psalm-param RecordingService::CONSENT_REQUIRED_* $recordingConsent
	 */
	public function setRecordingConsent(int $recordingConsent): void {
		$this->recordingConsent = $recordingConsent;
	}

	/**
	 * @psalm-return int-mask-of<self::HAS_FEDERATION_*>
	 */
	public function hasFederatedParticipants(): int {
		return $this->hasFederation;
	}

	/**
	 * @param int $hasFederation
	 * @psalm-param int-mask-of<self::HAS_FEDERATION_*> $hasFederation (bit map)
	 */
	public function setFederatedParticipants(int $hasFederation): void {
		$this->hasFederation = $hasFederation;
	}

	/**
	 * @psalm-return self::MENTION_PERMISSIONS_*
	 */
	public function getMentionPermissions(): int {
		return $this->mentionPermissions;
	}

	/**
	 * @psalm-param self::MENTION_PERMISSIONS_* $mentionPermissions
	 */
	public function setMentionPermissions(int $mentionPermissions): void {
		$this->mentionPermissions = $mentionPermissions;
	}

	public function setObjectId(string $objectId): void {
		$this->objectId = $objectId;
	}

	public function setObjectType(string $objectType): void {
		$this->objectType = $objectType;
	}

	public function getLiveTranscriptionLanguageId(): string {
		return $this->liveTranscriptionLanguageId;
	}

	public function setLiveTranscriptionLanguageId(string $liveTranscriptionLanguageId): void {
		$this->liveTranscriptionLanguageId = $liveTranscriptionLanguageId;
	}
}
