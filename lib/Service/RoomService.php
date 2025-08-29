<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\ARoomSyncedEvent;
use OCA\Talk\Events\BeforeCallEndedEvent;
use OCA\Talk\Events\BeforeCallStartedEvent;
use OCA\Talk\Events\BeforeLobbyModifiedEvent;
use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Events\BeforeRoomModifiedEvent;
use OCA\Talk\Events\BeforeRoomSyncedEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallStartedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\RoomPasswordVerifyEvent;
use OCA\Talk\Events\RoomSyncedEvent;
use OCA\Talk\Exceptions\InvalidRoomException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\RoomProperty\AvatarException;
use OCA\Talk\Exceptions\RoomProperty\BreakoutRoomModeException;
use OCA\Talk\Exceptions\RoomProperty\BreakoutRoomStatusException;
use OCA\Talk\Exceptions\RoomProperty\CallRecordingException;
use OCA\Talk\Exceptions\RoomProperty\CreationException;
use OCA\Talk\Exceptions\RoomProperty\DefaultPermissionsException;
use OCA\Talk\Exceptions\RoomProperty\DescriptionException;
use OCA\Talk\Exceptions\RoomProperty\ListableException;
use OCA\Talk\Exceptions\RoomProperty\LobbyException;
use OCA\Talk\Exceptions\RoomProperty\MentionPermissionsException;
use OCA\Talk\Exceptions\RoomProperty\MessageExpirationException;
use OCA\Talk\Exceptions\RoomProperty\NameException;
use OCA\Talk\Exceptions\RoomProperty\PasswordException;
use OCA\Talk\Exceptions\RoomProperty\ReadOnlyException;
use OCA\Talk\Exceptions\RoomProperty\RecordingConsentException;
use OCA\Talk\Exceptions\RoomProperty\SipConfigurationException;
use OCA\Talk\Exceptions\RoomProperty\TypeException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Calendar\IManager;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\HintException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\IHasher;
use OCP\Server;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class RoomService {

	public function __construct(
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected IDBConnection $db,
		protected ITimeFactory $timeFactory,
		protected IShareManager $shareManager,
		protected Config $config,
		protected IHasher $hasher,
		protected IEventDispatcher $dispatcher,
		protected IJobList $jobList,
		protected EmojiService $emojiService,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
		protected IManager $calendarManager,
	) {
	}

	/**
	 * @param IUser $actor
	 * @param IUser $targetUser
	 * @return Room
	 * @throws InvalidArgumentException when both users are the same
	 */
	public function createOneToOneConversation(IUser $actor, IUser $targetUser): Room {
		if ($actor->getUID() === $targetUser->getUID()) {
			throw new InvalidArgumentException('invite');
		}

		try {
			// If room exists: Reuse that one, otherwise create a new one.
			$room = $this->manager->getOne2OneRoom($actor->getUID(), $targetUser->getUID());
			$this->participantService->ensureOneToOneRoomIsFilled($room, $actor->getUID());
		} catch (RoomNotFoundException) {
			if (!$this->shareManager->currentUserCanEnumerateTargetUser($actor, $targetUser)) {
				throw new RoomNotFoundException();
			}

			$users = [$actor->getUID(), $targetUser->getUID()];
			sort($users);
			$room = $this->manager->createRoom(Room::TYPE_ONE_TO_ONE, json_encode($users));

			$this->participantService->addUsers($room, [
				[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $actor->getUID(),
					'displayName' => $actor->getDisplayName(),
					'participantType' => Participant::OWNER,
				],
			], $actor);
		}

		return $room;
	}

	/**
	 * @return Room
	 * @throws CreationException
	 * @throws PasswordException empty or invalid password
	 */
	public function createConversation(
		int $type,
		string $name,
		?IUser $owner = null,
		string $objectType = '',
		string $objectId = '',
		string $password = '',
		int $readOnly = Room::READ_WRITE,
		int $listable = Room::LISTABLE_NONE,
		int $messageExpiration = 0,
		int $lobbyState = Webinary::LOBBY_NONE,
		?int $lobbyTimer = null,
		int $sipEnabled = Webinary::SIP_DISABLED,
		int $permissions = Attendee::PERMISSIONS_DEFAULT,
		int $recordingConsent = RecordingService::CONSENT_REQUIRED_NO,
		int $mentionPermissions = Room::MENTION_PERMISSIONS_EVERYONE,
		string $description = '',
		?string $emoji = null,
		?string $avatarColor = null,
		bool $allowInternalTypes = true,
	): Room {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new CreationException(CreationException::REASON_NAME);
		}

		$types = [
			Room::TYPE_GROUP,
			Room::TYPE_PUBLIC,
		];
		if ($allowInternalTypes) {
			$types[] = Room::TYPE_CHANGELOG;
			$types[] = Room::TYPE_NOTE_TO_SELF;
		}
		if (!\in_array($type, $types, true)) {
			throw new CreationException(CreationException::REASON_TYPE);
		}

		$objectType = trim($objectType);
		if (isset($objectType[64])) {
			throw new CreationException(CreationException::REASON_OBJECT_TYPE);
		}

		$objectId = trim($objectId);
		if (isset($objectId[64])) {
			throw new CreationException(CreationException::REASON_OBJECT_ID);
		}

		$objectTypes = [
			'',
			// Kept to keep older clients working
			Room::OBJECT_TYPE_PHONE_LEGACY,
			Room::OBJECT_TYPE_PHONE_PERSIST,
			Room::OBJECT_TYPE_PHONE_TEMPORARY,
			Room::OBJECT_TYPE_EVENT,
			Room::OBJECT_TYPE_EXTENDED_CONVERSATION,
			Room::OBJECT_TYPE_INSTANT_MEETING,
		];
		if ($allowInternalTypes) {
			$objectTypes[] = BreakoutRoom::PARENT_OBJECT_TYPE;
			$objectTypes[] = Room::OBJECT_TYPE_EMAIL;
			$objectTypes[] = Room::OBJECT_TYPE_FILE;
			$objectTypes[] = Room::OBJECT_TYPE_NOTE_TO_SELF;
			$objectTypes[] = Room::OBJECT_TYPE_SAMPLE;
			$objectTypes[] = Room::OBJECT_TYPE_VIDEO_VERIFICATION;
		}

		if (!in_array($objectType, $objectTypes, true)) {
			throw new CreationException(CreationException::REASON_OBJECT_TYPE);
		}

		if (($objectType !== '' && $objectId === '')
			|| ($objectType === '' && $objectId !== '')) {
			throw new CreationException(CreationException::REASON_OBJECT);
		}

		if ($type === Room::TYPE_PUBLIC && $password === '' && $this->config->isPasswordEnforced()) {
			throw new PasswordException(PasswordException::REASON_VALUE, $this->l10n->t('Password needs to be set'));
		}

		if (!in_array($readOnly, [Room::READ_WRITE, Room::READ_ONLY], true)) {
			throw new CreationException(CreationException::REASON_READ_ONLY);
		}

		if (!in_array($listable, [Room::LISTABLE_NONE, Room::LISTABLE_USERS, Room::LISTABLE_ALL], true)) {
			throw new CreationException(CreationException::REASON_LISTABLE);
		}

		if ($messageExpiration < 0) {
			throw new CreationException(CreationException::REASON_MESSAGE_EXPIRATION);
		}

		if (!in_array($lobbyState, [Webinary::LOBBY_NONE, Webinary::LOBBY_NON_MODERATORS], true)) {
			throw new CreationException(CreationException::REASON_LOBBY);
		}

		$lobbyTimerDateTime = null;
		if ($lobbyState !== Webinary::LOBBY_NONE && $lobbyTimer !== null) {
			if ($lobbyTimer < 0) {
				throw new CreationException(CreationException::REASON_LOBBY_TIMER);
			}

			$lobbyTimerDateTime = $this->timeFactory->getDateTime('@' . $lobbyTimer);
			$lobbyTimerDateTime->setTimezone(new \DateTimeZone('UTC'));
		}

		if (!in_array($sipEnabled, [Webinary::SIP_DISABLED, Webinary::SIP_ENABLED, Webinary::SIP_ENABLED_NO_PIN], true)) {
			throw new CreationException(CreationException::REASON_SIP_ENABLED);
		}

		if ($permissions < Attendee::PERMISSIONS_DEFAULT || $permissions > Attendee::PERMISSIONS_MAX_CUSTOM) {
			throw new CreationException(CreationException::REASON_PERMISSIONS);
		} elseif ($permissions !== Attendee::PERMISSIONS_DEFAULT) {
			$permissions |= Attendee::PERMISSIONS_CUSTOM;
		}

		if (!in_array($recordingConsent, [RecordingService::CONSENT_REQUIRED_NO, RecordingService::CONSENT_REQUIRED_YES], true)) {
			throw new CreationException(CreationException::REASON_RECORDING_CONSENT);
		}

		if (!in_array($mentionPermissions, [Room::MENTION_PERMISSIONS_EVERYONE, Room::MENTION_PERMISSIONS_MODERATORS], true)) {
			throw new CreationException(CreationException::REASON_MENTION_PERMISSIONS);
		}
		if (mb_strlen($description) > Room::DESCRIPTION_MAXIMUM_LENGTH) {
			throw new CreationException(CreationException::REASON_DESCRIPTION);
		}

		if ($emoji !== null) {
			if ($this->emojiService->getFirstCombinedEmoji($emoji) !== $emoji) {
				throw new CreationException(CreationException::REASON_AVATAR);
			}
			if ($avatarColor !== null && !preg_match('/^[a-fA-F0-9]{6}$/', $avatarColor)) {
				throw new CreationException(CreationException::REASON_AVATAR);
			}
		}

		if ($type !== Room::TYPE_PUBLIC || $password === '') {
			$passwordHash = '';
		} else {
			$event = new ValidatePasswordPolicyEvent($password);
			try {
				$this->dispatcher->dispatchTyped($event);
			} catch (HintException $e) {
				throw new PasswordException(PasswordException::REASON_VALUE, $e->getHint());
			}
			$passwordHash = $this->hasher->hash($password);
		}

		$room = $this->manager->createRoom(
			$type,
			$name,
			$objectType,
			$objectId,
			$passwordHash,
			$readOnly,
			$listable,
			$messageExpiration,
			$lobbyState,
			$lobbyTimerDateTime,
			$sipEnabled,
			$permissions,
			$recordingConsent,
			$mentionPermissions,
			$description,
		);

		if ($emoji !== null) {
			$avatarService = Server::get(AvatarService::class);
			$avatarService->setAvatarFromEmoji($room, $emoji, $avatarColor);
		}

		if ($owner instanceof IUser) {
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $owner->getUID(),
				'displayName' => $owner->getDisplayName(),
				'participantType' => Participant::OWNER,
			]], null);
		}
		return $room;

	}

	public function prepareConversationName(string $objectName): string {
		return rtrim(mb_substr(ltrim($objectName), 0, 64));
	}

	/**
	 * @deprecated
	 */
	public function setPermissions(Room $room, string $level, string $method, int $permissions, bool $resetCustomPermissions): bool {
		if ($level === 'default' && $method === 'set') {
			try {
				$this->setDefaultPermissions($room, $permissions);
				return true;
			} catch (InvalidArgumentException) {
				return false;

			}
		}
		return false;
	}

	/**
	 * @throws DefaultPermissionsException
	 */
	public function setDefaultPermissions(Room $room, int $permissions): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE
			|| $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER
			|| $room->getType() === Room::TYPE_NOTE_TO_SELF) {
			throw new DefaultPermissionsException(DefaultPermissionsException::REASON_TYPE);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Do not allow manual changing the permissions in breakout rooms
			throw new DefaultPermissionsException(DefaultPermissionsException::REASON_BREAKOUT_ROOM);
		}

		if ($permissions < 0 || $permissions > 255) {
			// Do not allow manual changing the permissions in breakout rooms
			throw new DefaultPermissionsException(DefaultPermissionsException::REASON_VALUE);
		}

		$oldPermissions = $room->getDefaultPermissions();
		$newPermissions = $permissions;
		if ($newPermissions !== Attendee::PERMISSIONS_DEFAULT) {
			// Make sure the custom flag is set when not setting to default permissions
			$newPermissions |= Attendee::PERMISSIONS_CUSTOM;
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);

		// Reset custom user permissions to default
		$this->participantService->updateAllPermissions($room, Attendee::PERMISSIONS_MODIFY_SET, Attendee::PERMISSIONS_DEFAULT);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('default_permissions', $update->createNamedParameter($newPermissions, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setDefaultPermissions($newPermissions);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @throws SipConfigurationException
	 */
	public function setSIPEnabled(Room $room, int $newSipEnabled): void {
		$oldSipEnabled = $room->getSIPEnabled();

		if ($newSipEnabled === $oldSipEnabled) {
			return;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new SipConfigurationException(SipConfigurationException::REASON_BREAKOUT_ROOM);
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new SipConfigurationException(SipConfigurationException::REASON_TYPE);
		}

		if (!in_array($newSipEnabled, [Webinary::SIP_ENABLED_NO_PIN, Webinary::SIP_ENABLED, Webinary::SIP_DISABLED], true)) {
			throw new SipConfigurationException(SipConfigurationException::REASON_VALUE);
		}

		if (preg_match(Room::SIP_INCOMPATIBLE_REGEX, $room->getToken())) {
			throw new SipConfigurationException(SipConfigurationException::REASON_TOKEN);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_SIP_ENABLED, $newSipEnabled, $oldSipEnabled);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('sip_enabled', $update->createNamedParameter($newSipEnabled, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setSIPEnabled($newSipEnabled);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_SIP_ENABLED, $newSipEnabled, $oldSipEnabled);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @psalm-param RecordingService::CONSENT_REQUIRED_* $recordingConsent
	 * @throws RecordingConsentException When the room has an active call or the value is invalid
	 */
	public function setRecordingConsent(Room $room, int $recordingConsent, bool $allowUpdatingBreakoutRoomsAndFederatedRooms = false): void {
		$oldRecordingConsent = $room->getRecordingConsent();

		if ($recordingConsent === $oldRecordingConsent) {
			return;
		}

		if (!in_array($recordingConsent, [RecordingService::CONSENT_REQUIRED_NO, RecordingService::CONSENT_REQUIRED_YES], true)) {
			throw new RecordingConsentException(RecordingConsentException::REASON_VALUE);
		}

		if (!$allowUpdatingBreakoutRoomsAndFederatedRooms) {
			if ($recordingConsent !== RecordingService::CONSENT_REQUIRED_NO && $room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
				throw new RecordingConsentException(RecordingConsentException::REASON_CALL);
			}

			if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
				throw new RecordingConsentException(RecordingConsentException::REASON_BREAKOUT_ROOM);
			}

			if ($room->getBreakoutRoomStatus() !== BreakoutRoom::STATUS_STOPPED) {
				throw new RecordingConsentException(RecordingConsentException::REASON_BREAKOUT_ROOM);
			}
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT, $recordingConsent, $oldRecordingConsent);
		$this->dispatcher->dispatchTyped($event);

		$now = $this->timeFactory->getDateTime();

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('recording_consent', $update->createNamedParameter($recordingConsent, IQueryBuilder::PARAM_INT))
			->set('last_activity', $update->createNamedParameter($now, IQueryBuilder::PARAM_DATE))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setRecordingConsent($recordingConsent);
		$room->setLastActivity($now);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT, $recordingConsent, $oldRecordingConsent);
		$this->dispatcher->dispatchTyped($event);

		// Update the recording consent for all rooms
		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken());
			foreach ($breakoutRooms as $breakoutRoom) {
				$this->setRecordingConsent($breakoutRoom, $recordingConsent, true);
			}
		}
	}

	/**
	 * @throws NameException
	 */
	public function setName(Room $room, string $newName, ?string $oldName = null, bool $validateType = false): void {
		if ($validateType && ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)) {
			throw new NameException(NameException::REASON_TYPE);
		}

		$newName = trim($newName);
		$oldName = $oldName ?? $room->getName();
		if ($newName === $oldName) {
			return;
		}

		if ($newName === '' || mb_strlen($newName) > 255) {
			throw new NameException(NameException::REASON_VALUE);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_NAME, $newName, $oldName);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('name', $update->createNamedParameter($newName))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setName($newName);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_NAME, $newName, $oldName);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @param int $newState Currently it is only allowed to change between
	 *                      `Webinary::LOBBY_NON_MODERATORS` and `Webinary::LOBBY_NONE`
	 *                      Also it's not allowed in one-to-one conversations,
	 *                      file conversations and password request conversations.
	 * @param \DateTime|null $dateTime
	 * @param bool $timerReached
	 * @param bool $dispatchEvents (Only skip if the room is created in the same PHP request)
	 * @throws LobbyException
	 */
	public function setLobby(Room $room, int $newState, ?\DateTime $dateTime, bool $timerReached = false, bool $dispatchEvents = true): void {
		$oldState = $room->getLobbyState(false);

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new LobbyException(LobbyException::REASON_TYPE);
		}

		if ($room->getObjectType() !== '' && !in_array($room->getObjectType(), [
			BreakoutRoom::PARENT_OBJECT_TYPE,
			Room::OBJECT_TYPE_EMAIL,
			Room::OBJECT_TYPE_EVENT,
			Room::OBJECT_TYPE_EXTENDED_CONVERSATION,
			Room::OBJECT_TYPE_INSTANT_MEETING,
		], true)) {
			throw new LobbyException(LobbyException::REASON_OBJECT);
		}

		if (!in_array($newState, [Webinary::LOBBY_NON_MODERATORS, Webinary::LOBBY_NONE], true)) {
			throw new LobbyException(LobbyException::REASON_VALUE);
		}

		if ($dispatchEvents) {
			$event = new BeforeLobbyModifiedEvent($room, $newState, $oldState, $dateTime, $timerReached);
			$this->dispatcher->dispatchTyped($event);
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('lobby_state', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->set('lobby_timer', $update->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLobbyState($newState);
		$room->setLobbyTimer($dateTime);

		if ($dispatchEvents) {
			$event = new LobbyModifiedEvent($room, $newState, $oldState, $dateTime, $timerReached);
			$this->dispatcher->dispatchTyped($event);
		}
	}

	/**
	 * @throws AvatarException
	 */
	public function setAvatar(Room $room, string $avatar): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new AvatarException(AvatarException::REASON_TYPE);
		}

		$oldAvatar = $room->getAvatar();
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_AVATAR, $avatar, $oldAvatar);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('avatar', $update->createNamedParameter($avatar))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setAvatar($avatar);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_AVATAR, $avatar, $oldAvatar);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @psalm-param Room::RECORDING_* $status
	 * @param Participant|null $participant the Participant that changed the
	 *                                      state, null for the current user
	 * @throws CallRecordingException
	 */
	public function setCallRecording(Room $room, int $status = Room::RECORDING_NONE, ?Participant $participant = null): void {
		$syncFederatedRoom = $room->getRemoteServer() && $room->getRemoteToken();
		if (!$syncFederatedRoom && !$this->config->isRecordingEnabled() && $status !== Room::RECORDING_NONE) {
			throw new CallRecordingException(CallRecordingException::REASON_CONFIG);
		}

		$availableRecordingStatus = [Room::RECORDING_NONE, Room::RECORDING_VIDEO, Room::RECORDING_AUDIO, Room::RECORDING_VIDEO_STARTING, Room::RECORDING_AUDIO_STARTING, Room::RECORDING_FAILED];
		if (!in_array($status, $availableRecordingStatus, true)) {
			throw new CallRecordingException(CallRecordingException::REASON_STATUS);
		}

		$oldStatus = $room->getCallRecording();
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $status, $oldStatus, $participant);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('call_recording', $update->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setCallRecording($status);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_CALL_RECORDING, $status, $oldStatus, $participant);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @param int $newType Currently it is only allowed to change between `Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @param bool $allowSwitchingOneToOne Allows additionally to change the type from `Room::TYPE_ONE_TO_ONE` to `Room::TYPE_ONE_TO_ONE_FORMER`
	 * @throws TypeException
	 */
	public function setType(Room $room, int $newType, bool $allowSwitchingOneToOne = false): void {
		$oldType = $room->getType();
		if ($oldType === $newType) {
			return;
		}

		if (!$allowSwitchingOneToOne && $oldType === Room::TYPE_ONE_TO_ONE) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if ($oldType === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if ($oldType === Room::TYPE_NOTE_TO_SELF) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if (!in_array($newType, [Room::TYPE_GROUP, Room::TYPE_PUBLIC, Room::TYPE_ONE_TO_ONE_FORMER], true)) {
			throw new TypeException(TypeException::REASON_VALUE);
		}

		if ($newType === Room::TYPE_ONE_TO_ONE_FORMER && $oldType !== Room::TYPE_ONE_TO_ONE) {
			throw new TypeException(TypeException::REASON_VALUE);
		}

		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new TypeException(TypeException::REASON_BREAKOUT_ROOM);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new TypeException(TypeException::REASON_BREAKOUT_ROOM);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_TYPE, $newType, $oldType);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('type', $update->createNamedParameter($newType, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setType($newType);

		if ($oldType === Room::TYPE_PUBLIC) {
			// Kick all guests and users that were not invited
			$delete = $this->db->getQueryBuilder();
			$delete->delete('talk_attendees')
				->where($delete->expr()->eq('room_id', $delete->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
				->andWhere($delete->expr()->in('participant_type', $delete->createNamedParameter([Participant::GUEST, Participant::GUEST_MODERATOR, Participant::USER_SELF_JOINED], IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
		}

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_TYPE, $newType, $oldType);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @throws PasswordException|TypeException
	 */
	public function makePublicWithPassword(Room $room, string $password): void {
		if ($room->getType() === Room::TYPE_PUBLIC) {
			return;
		}

		if ($room->getType() !== Room::TYPE_GROUP) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if ($password === '') {
			throw new PasswordException(PasswordException::REASON_VALUE, $this->l10n->t('Password needs to be set'));
		}

		$event = new ValidatePasswordPolicyEvent($password);
		try {
			$this->dispatcher->dispatchTyped($event);
		} catch (HintException $e) {
			throw new PasswordException(PasswordException::REASON_VALUE, $e->getHint());
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_TYPE, Room::TYPE_PUBLIC, $room->getType());
		$this->dispatcher->dispatchTyped($event);
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_PASSWORD, $password);
		$this->dispatcher->dispatchTyped($event);

		$passwordHash = $this->hasher->hash($password);
		$this->manager->setPublic($room->getId(), $passwordHash);
		$room->setType(Room::TYPE_PUBLIC);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_TYPE, Room::TYPE_PUBLIC, $room->getType());
		$this->dispatcher->dispatchTyped($event);
		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_PASSWORD, $password);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @param int $newState Currently it is only allowed to change between
	 *                      `Room::READ_ONLY` and `Room::READ_WRITE`
	 *                      Also it's only allowed on rooms of type
	 *                      `Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @throws ReadOnlyException
	 */
	public function setReadOnly(Room $room, int $newState): void {
		$oldState = $room->getReadOnly();
		if ($newState === $oldState) {
			return;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC, Room::TYPE_CHANGELOG], true)) {
			if ($newState !== Room::READ_ONLY || $room->getType() !== Room::TYPE_ONE_TO_ONE_FORMER) {
				// Allowed for the automated conversation of one-to-one chats to read only former
				throw new ReadOnlyException(ReadOnlyException::REASON_TYPE);
			}
		}

		if (!in_array($newState, [Room::READ_ONLY, Room::READ_WRITE], true)) {
			throw new ReadOnlyException(ReadOnlyException::REASON_VALUE);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_READ_ONLY, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('read_only', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setReadOnly($newState);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_READ_ONLY, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @param int $newState New listable scope from self::LISTABLE_*
	 *                      Also it's only allowed on rooms of type
	 *                      `Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @psalm-param Room::LISTABLE_* $newState
	 * @throws ListableException
	 */
	public function setListable(Room $room, int $newState): void {
		$oldState = $room->getListable();
		if ($newState === $oldState) {
			return;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new ListableException(ListableException::REASON_TYPE);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new ListableException(ListableException::REASON_BREAKOUT_ROOM);
		}

		if (!in_array($newState, [
			Room::LISTABLE_NONE,
			Room::LISTABLE_USERS,
			Room::LISTABLE_ALL,
		], true)) {
			throw new ListableException(ListableException::REASON_VALUE);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_LISTABLE, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('listable', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setListable($newState);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_LISTABLE, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param Room $room
	 * @param int $newState New mention permissions from Room::MENTION_PERMISSIONS_*
	 * @psalm-param Room::MENTION_PERMISSIONS_* $newState
	 * @throws MentionPermissionsException
	 */
	public function setMentionPermissions(Room $room, int $newState): void {
		$oldState = $room->getMentionPermissions();
		if ($newState === $oldState) {
			return;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			throw new MentionPermissionsException(MentionPermissionsException::REASON_TYPE);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new MentionPermissionsException(MentionPermissionsException::REASON_BREAKOUT_ROOM);
		}

		if (!in_array($newState, [Room::MENTION_PERMISSIONS_EVERYONE, Room::MENTION_PERMISSIONS_MODERATORS], true)) {
			throw new MentionPermissionsException(MentionPermissionsException::REASON_VALUE);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('mention_permissions', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setMentionPermissions($newState);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * Set the ID of the language to use for live transcriptions in the given
	 * room.
	 *
	 * This method is not meant to be directly used; use
	 * "LiveTranscriptionService::setLanguage" instead, which only sets the
	 * language in the room if the external app is available and the language is
	 * valid.
	 *
	 * @param Room $room
	 * @param string $newState ID of the language to set
	 */
	public function setLiveTranscriptionLanguageId(Room $room, string $newState): void {
		$oldState = $room->getLiveTranscriptionLanguageId();
		if ($newState === $oldState) {
			return;
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_LIVE_TRANSCRIPTION_LANGUAGE_ID, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('transcription_language', $update->createNamedParameter($newState, IQueryBuilder::PARAM_STR))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLiveTranscriptionLanguageId($newState);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_LIVE_TRANSCRIPTION_LANGUAGE_ID, $newState, $oldState);
		$this->dispatcher->dispatchTyped($event);
	}

	public function setAssignedSignalingServer(Room $room, ?int $signalingServer): bool {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('assigned_hpb', $update->createNamedParameter($signalingServer))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));

		if ($signalingServer !== null) {
			$update->andWhere($update->expr()->isNull('assigned_hpb'));
		}

		$updated = (bool)$update->executeStatement();
		if ($updated) {
			$room->setAssignedSignalingServer($signalingServer);
		}

		return $updated;
	}

	/**
	 * @throws DescriptionException
	 */
	public function setDescription(Room $room, string $description): void {
		$description = trim($description);

		$oldDescription = $room->getDescription();
		if ($description === $oldDescription) {
			return;
		}

		if (mb_strlen($description) > Room::DESCRIPTION_MAXIMUM_LENGTH) {
			throw new DescriptionException(DescriptionException::REASON_VALUE);
		}

		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new DescriptionException(DescriptionException::REASON_TYPE);
		}

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_DESCRIPTION, $description, $oldDescription);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('description', $update->createNamedParameter($description))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setDescription($description);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_DESCRIPTION, $description, $oldDescription);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param string $password Currently it is only allowed to have a password for Room::TYPE_PUBLIC
	 * @throws PasswordException
	 */
	public function setPassword(Room $room, string $password): void {
		if ($room->getType() !== Room::TYPE_PUBLIC) {
			throw new PasswordException(PasswordException::REASON_TYPE);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new PasswordException(PasswordException::REASON_BREAKOUT_ROOM);
		}

		if ($password !== '') {
			$event = new ValidatePasswordPolicyEvent($password);
			try {
				$this->dispatcher->dispatchTyped($event);
			} catch (HintException $e) {
				throw new PasswordException(PasswordException::REASON_VALUE, $e->getHint());
			}
		}

		$hash = $password !== '' ? $this->hasher->hash($password) : '';

		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_PASSWORD, $password);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('password', $update->createNamedParameter($hash))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setPassword($hash);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_PASSWORD, $password);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @return array{result: ?bool, url: string}
	 */
	public function verifyPassword(Room $room, string $password): array {
		$event = new RoomPasswordVerifyEvent($room, $password);
		$this->dispatcher->dispatchTyped($event);

		if ($event->isPasswordValid() !== null) {
			return [
				'result' => $event->isPasswordValid(),
				'url' => $event->getRedirectUrl(),
			];
		}

		return [
			'result' => !$room->hasPassword() || $this->hasher->verify($password, $room->getPassword()),
			'url' => '',
		];
	}

	/**
	 * @throws MessageExpirationException When the room is a breakout room or the room is a former one-to-one conversation
	 */
	public function setMessageExpiration(Room $room, int $seconds): void {
		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new MessageExpirationException(MessageExpirationException::REASON_BREAKOUT_ROOM);
		}

		if ($room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new MessageExpirationException(MessageExpirationException::REASON_TYPE);
		}

		if ($seconds < 0) {
			throw new MessageExpirationException(MessageExpirationException::REASON_VALUE);
		}

		$oldExpiration = $room->getMessageExpiration();
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION, $seconds, $oldExpiration);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('message_expiration', $update->createNamedParameter($seconds, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setMessageExpiration($seconds);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION, $seconds, $oldExpiration);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @psalm-param BreakoutRoom::MODE_* $mode
	 * @throws BreakoutRoomModeException
	 */
	public function setBreakoutRoomMode(Room $room, int $mode): void {
		if (!in_array($mode, [
			BreakoutRoom::MODE_NOT_CONFIGURED,
			BreakoutRoom::MODE_AUTOMATIC,
			BreakoutRoom::MODE_MANUAL,
			BreakoutRoom::MODE_FREE
		], true)) {
			throw new BreakoutRoomModeException(BreakoutRoomModeException::REASON_VALUE);
		}

		$oldMode = $room->getBreakoutRoomMode();
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_MODE, $mode, $oldMode);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('breakout_room_mode', $update->createNamedParameter($mode, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setBreakoutRoomMode($mode);

		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_MODE, $mode, $oldMode);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @psalm-param BreakoutRoom::STATUS_* $status
	 * @throws BreakoutRoomStatusException
	 */
	public function setBreakoutRoomStatus(Room $room, int $status): void {
		if (!in_array($status, [
			BreakoutRoom::STATUS_STOPPED,
			BreakoutRoom::STATUS_STARTED,
			BreakoutRoom::STATUS_ASSISTANCE_RESET,
			BreakoutRoom::STATUS_ASSISTANCE_REQUESTED,
		], true)) {
			throw new BreakoutRoomStatusException(BreakoutRoomStatusException::REASON_VALUE);
		}

		$oldStatus = $room->getBreakoutRoomStatus();
		$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_STATUS, $status, $oldStatus);
		$this->dispatcher->dispatchTyped($event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('breakout_room_status', $update->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setBreakoutRoomStatus($status);

		$oldStatus = $room->getBreakoutRoomStatus();
		$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_BREAKOUT_ROOM_STATUS, $status, $oldStatus);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @internal Warning! Use with care, this is only used to make sure we win the race condition for posting the final messages
	 * when "End call for everyone" is used where we print the chat messages before testing the race condition,
	 * so that no other participant leaving would trigger a call summary
	 */
	public function resetActiveSinceInDatabaseOnly(Room $room): bool {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('active_since', $update->createNamedParameter(null, IQueryBuilder::PARAM_DATE))
			->set('call_flag', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('call_permissions', $update->createNamedParameter(Attendee::PERMISSIONS_DEFAULT, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->isNotNull('active_since'));

		return (bool)$update->executeStatement();
	}

	/**
	 * @internal Warning! Must only be used after {@see preResetActiveSinceInDatabaseOnly()}
	 * was called and returned `true`
	 */
	public function resetActiveSinceInModelOnly(Room $room): void {
		$room->resetActiveSince();
	}

	public function resetActiveSince(Room $room, ?Participant $participant): void {
		$oldActiveSince = $room->getActiveSince();
		$oldCallFlag = $room->getCallFlag();

		if ($oldActiveSince === null && $oldCallFlag === Participant::FLAG_DISCONNECTED) {
			return;
		}

		$event = new BeforeCallEndedEvent($room, $participant, $oldActiveSince);
		$this->dispatcher->dispatchTyped($event);

		$result = $this->resetActiveSinceInDatabaseOnly($room);
		$this->resetActiveSinceInModelOnly($room);

		if (!$result) {
			// Lost the race, someone else updated the database
			return;
		}

		$event = new CallEndedEvent($room, $participant, $oldActiveSince);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param list<string> $silentFor
	 */
	public function setActiveSince(Room $room, ?Participant $participant, \DateTime $since, int $callFlag, bool $silent, array $silentFor = []): bool {
		$oldCallFlag = $room->getCallFlag();
		$callFlag |= $oldCallFlag; // Merge the callFlags, so events and response are with the best values

		if ($room->getActiveSince() instanceof \DateTime && $oldCallFlag === $callFlag) {
			// Call flags of the conversation are unchanged and it's already marked active
			return false;
		}

		$details = [];
		if ($room->getActiveSince() instanceof \DateTime) {
			// Call is already active, just someone upgrading the call flags
			$event = new BeforeRoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_IN_CALL, $callFlag, $oldCallFlag, $participant);
			$this->dispatcher->dispatchTyped($event);
		} else {
			if ($silent) {
				$details[AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT] = true;
			} elseif (!empty($silentFor)) {
				$details[AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT_FOR] = $silentFor;
			}
			$event = new BeforeCallStartedEvent($room, $since, $callFlag, $details, $participant);
			$this->dispatcher->dispatchTyped($event);
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set(
				'call_flag',
				$update->expr()->bitwiseOr('call_flag', $callFlag)
			)
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		if ($room->getActiveSince() instanceof \DateTime) {
			// Call is already active, just someone upgrading the call flags
			$room->setActiveSince($room->getActiveSince(), $callFlag);

			$event = new RoomModifiedEvent($room, ARoomModifiedEvent::PROPERTY_IN_CALL, $callFlag, $oldCallFlag);
			$this->dispatcher->dispatchTyped($event);

			return false;
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('active_since', $update->createNamedParameter($since, IQueryBuilder::PARAM_DATE))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->isNull('active_since'));
		$result = (bool)$update->executeStatement();

		$room->setActiveSince($since, $callFlag);

		if (!$result) {
			// Lost the race, someone else updated the database
			return false;
		}

		$event = new CallStartedEvent($room, $since, $callFlag, $details, $participant);
		$this->dispatcher->dispatchTyped($event);

		return true;
	}

	public function setLastMessage(Room $room, IComment $message): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_message', $update->createNamedParameter((int)$message->getId()))
			->set('last_activity', $update->createNamedParameter($message->getCreationDateTime(), 'datetime'))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLastMessage($message);
		$room->setLastActivity($message->getCreationDateTime());
	}

	public function setLastMessageInfo(Room $room, int $messageId, \DateTime $dateTime): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_message', $update->createNamedParameter($messageId))
			->set('last_activity', $update->createNamedParameter($dateTime, 'datetime'))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLastMessageId($messageId);
		$room->setLastActivity($dateTime);
	}

	/**
	 * @psalm-param int-mask-of<Room::HAS_FEDERATION_*> $hasFederation
	 */
	public function setHasFederation(Room $room, int $hasFederation): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('has_federation', $update->createNamedParameter($hasFederation, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setFederatedParticipants($hasFederation);
	}

	public function setHasAttachments(Room $room): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('has_attachments', $update->createNamedParameter(Attachment::ATTACHMENTS_ATLEAST_ONE, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();
	}

	public function setLastActivity(Room $room, \DateTime $now): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_activity', $update->createNamedParameter($now, 'datetime'))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLastActivity($now);
	}

	/**
	 * @psalm-param TalkRoom $host
	 */
	public function syncPropertiesFromHostRoom(Room $local, array $host): void {
		$event = new BeforeRoomSyncedEvent($local);
		$this->dispatcher->dispatchTyped($event);

		/** @var array<array-key, ARoomModifiedEvent::PROPERTY_*> $changed */
		$changed = [];
		if (isset($host['type']) && $host['type'] !== $local->getType()) {
			try {
				$this->setType($local, $host['type']);
				$changed[] = ARoomModifiedEvent::PROPERTY_TYPE;
			} catch (TypeException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync n type of ' . $local->getId() . ' to ' . $host['type'], ['exception' => $e]);
			}
		}
		if (isset($host['name']) && $host['name'] !== $local->getName()) {
			try {
				$this->setName($local, $host['name'], $local->getName());
				$changed[] = ARoomModifiedEvent::PROPERTY_NAME;
			} catch (NameException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync name of ' . $local->getId() . ' to ' . $host['name'], ['exception' => $e]);
			}
		}
		if (isset($host['description']) && $host['description'] !== $local->getDescription()) {
			try {
				$this->setDescription($local, $host['description']);
				$changed[] = ARoomModifiedEvent::PROPERTY_DESCRIPTION;
			} catch (DescriptionException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync description of ' . $local->getId() . ' to ' . $host['description'], ['exception' => $e]);
			}
		}
		if (isset($host['callRecording']) && $host['callRecording'] !== $local->getCallRecording()) {
			try {
				$this->setCallRecording($local, $host['callRecording']);
				$changed[] = ARoomModifiedEvent::PROPERTY_CALL_RECORDING;
			} catch (CallRecordingException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync callRecording of ' . $local->getId() . ' to ' . $host['callRecording'], ['exception' => $e]);
			}
		}
		if (isset($host['defaultPermissions']) && $host['defaultPermissions'] !== $local->getDefaultPermissions()) {
			try {
				$this->setDefaultPermissions($local, $host['defaultPermissions']);
				$changed[] = ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS;
			} catch (DefaultPermissionsException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync defaultPermissions of ' . $local->getId() . ' to ' . $host['defaultPermissions'], ['exception' => $e]);
			}
		}
		if (isset($host['avatarVersion']) && $host['avatarVersion'] !== $local->getAvatar()) {
			$hostAvatar = $host['avatarVersion'];
			if ($hostAvatar) {
				// Add a fake suffix as we explode by the dot in the AvatarService, but the version doesn't have one.
				$hostAvatar .= '.fed';
			}
			try {
				$this->setAvatar($local, $hostAvatar);
				$changed[] = ARoomModifiedEvent::PROPERTY_AVATAR;
			} catch (AvatarException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync avatarVersion of ' . $local->getId() . ' to ' . $host['avatarVersion'], ['exception' => $e]);
			}
		}
		if (isset($host['lastActivity']) && $host['lastActivity'] !== 0 && $host['lastActivity'] !== ((int)$local->getLastActivity()?->getTimestamp())) {
			$lastActivity = $this->timeFactory->getDateTime('@' . $host['lastActivity']);
			$this->setLastActivity($local, $lastActivity);
			$changed[] = ARoomSyncedEvent::PROPERTY_LAST_ACTIVITY;
		}
		if (isset($host['lobbyState'], $host['lobbyTimer']) && ($host['lobbyState'] !== $local->getLobbyState(false) || $host['lobbyTimer'] !== ((int)$local->getLobbyTimer(false)?->getTimestamp()))) {
			$hostTimer = $host['lobbyTimer'] === 0 ? null : $this->timeFactory->getDateTime('@' . $host['lobbyTimer']);
			try {
				$this->setLobby($local, $host['lobbyState'], $hostTimer);
				$changed[] = ARoomModifiedEvent::PROPERTY_LOBBY;
			} catch (LobbyException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync lobby of ' . $local->getId() . ' to ' . $host['lobbyState'] . ' with timer to ' . $host['lobbyTimer'], ['exception' => $e]);
			}
		}
		if (isset($host['callStartTime'], $host['callFlag'])) {
			$localCallStartTime = (int)$local->getActiveSince()?->getTimestamp();
			if ($host['callStartTime'] === 0 && ($host['callStartTime'] !== $localCallStartTime || $host['callFlag'] !== $local->getCallFlag())) {
				$this->resetActiveSince($local, null);
				$changed[] = ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE;
				$changed[] = ARoomModifiedEvent::PROPERTY_IN_CALL;
			} elseif ($host['callStartTime'] !== 0 && ($host['callStartTime'] !== $localCallStartTime || $host['callFlag'] !== $local->getCallFlag())) {
				$startDateTime = $this->timeFactory->getDateTime('@' . $host['callStartTime']);
				$this->setActiveSince($local, null, $startDateTime, $host['callFlag'], true);
				$changed[] = ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE;
				$changed[] = ARoomModifiedEvent::PROPERTY_IN_CALL;
			}
		}
		if (isset($host['mentionPermissions']) && $host['mentionPermissions'] !== $local->getMentionPermissions()) {
			try {
				$this->setMentionPermissions($local, $host['mentionPermissions']);
				$changed[] = ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS;
			} catch (MentionPermissionsException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync mentionPermissions of ' . $local->getId() . ' to ' . $host['mentionPermissions'], ['exception' => $e]);
			}
		}
		if (isset($host['messageExpiration']) && $host['messageExpiration'] !== $local->getMessageExpiration()) {
			try {
				$this->setMessageExpiration($local, $host['messageExpiration']);
				$changed[] = ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION;
			} catch (MessageExpirationException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync messageExpiration of ' . $local->getId() . ' to ' . $host['messageExpiration'], ['exception' => $e]);
			}
		}
		if (isset($host['readOnly']) && $host['readOnly'] !== $local->getReadOnly()) {
			try {
				$this->setReadOnly($local, $host['readOnly']);
				$changed[] = ARoomModifiedEvent::PROPERTY_READ_ONLY;
			} catch (ReadOnlyException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync readOnly of ' . $local->getId() . ' to ' . $host['readOnly'], ['exception' => $e]);
			}
		}
		if (isset($host['recordingConsent']) && $host['recordingConsent'] !== $local->getRecordingConsent()) {
			try {
				$this->setRecordingConsent($local, $host['recordingConsent'], true);
				$changed[] = ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT;
			} catch (RecordingConsentException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync recordingConsent of ' . $local->getId() . ' to ' . $host['recordingConsent'], ['exception' => $e]);
			}
		}
		if (isset($host['sipEnabled']) && $host['sipEnabled'] !== $local->getSIPEnabled()) {
			try {
				$this->setSIPEnabled($local, $host['sipEnabled']);
				$changed[] = ARoomModifiedEvent::PROPERTY_SIP_ENABLED;
			} catch (SipConfigurationException $e) {
				$this->logger->error('An error (' . $e->getReason() . ') occurred while trying to sync sipEnabled of ' . $local->getId() . ' to ' . $host['sipEnabled'], ['exception' => $e]);
			}
		}

		// Ignore for now, so the conversation is not found by other users on this federated participants server
		// if (isset($host['listable']) && $host['listable'] !== $local->getListable()) {
		// $success = $this->setListable($local, $host['listable']);
		// if (!$success) {
		// $this->logger->error('An error occurred while trying to sync listable of ' . $local->getId() . ' to ' . $host['listable']);
		// } else {
		// $changed[] = ARoomModifiedEvent::PROPERTY_LISTABLE;
		// }
		// }

		$event = new RoomSyncedEvent($local, $changed);
		$this->dispatcher->dispatchTyped($event);
	}

	public function deleteRoom(Room $room): void {
		$event = new BeforeRoomDeletedEvent($room);
		$this->dispatcher->dispatchTyped($event);

		// Delete all breakout rooms when deleting a parent room
		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			$breakoutRooms = $this->manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken());
			foreach ($breakoutRooms as $breakoutRoom) {
				$this->deleteRoom($breakoutRoom);
			}
		}

		if ($room->isFederatedConversation()) {
			// Delete PCM messages
			$delete = $this->db->getQueryBuilder();
			$delete->delete('talk_proxy_messages')
				->where($delete->expr()->eq('local_token', $delete->createNamedParameter($room->getToken())));
			$delete->executeStatement();
		}

		// Delete attendees
		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_attendees')
			->where($delete->expr()->eq('room_id', $delete->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$delete->executeStatement();

		// Delete room
		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_rooms')
			->where($delete->expr()->eq('id', $delete->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$delete->executeStatement();

		$event = new RoomDeletedEvent($room);
		$this->dispatcher->dispatchTyped($event);
		if (class_exists(CriticalActionPerformedEvent::class)) {
			$this->dispatcher->dispatchTyped(new CriticalActionPerformedEvent(
				'Conversation "%s" deleted',
				['name' => $room->getName()],
			));
		}
	}

	/**
	 * @return list<Room>
	 */
	public function getInactiveRooms(\DateTime $inactiveSince): array {
		return $this->manager->getInactiveRooms($inactiveSince);
	}

	/**
	 * @param Room $room
	 * @param int $oldType
	 * @param int $newType
	 * @param bool $allowSwitchingOneToOne
	 * @return void
	 */
	public function validateRoomTypeSwitch(Room $room, int $oldType, int $newType, bool $allowSwitchingOneToOne): void {
		if (!$allowSwitchingOneToOne && $oldType === Room::TYPE_ONE_TO_ONE) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if ($oldType === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if ($oldType === Room::TYPE_NOTE_TO_SELF) {
			throw new TypeException(TypeException::REASON_TYPE);
		}

		if (!in_array($newType, [Room::TYPE_GROUP, Room::TYPE_PUBLIC, Room::TYPE_ONE_TO_ONE_FORMER], true)) {
			throw new TypeException(TypeException::REASON_VALUE);
		}

		if ($newType === Room::TYPE_ONE_TO_ONE_FORMER && $oldType !== Room::TYPE_ONE_TO_ONE) {
			throw new TypeException(TypeException::REASON_VALUE);
		}

		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			throw new TypeException(TypeException::REASON_BREAKOUT_ROOM);
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new TypeException(TypeException::REASON_BREAKOUT_ROOM);
		}
	}

	public function resetObject(Room $room): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('object_type', $update->createNamedParameter('', IQueryBuilder::PARAM_STR))
			->set('object_id', $update->createNamedParameter('', IQueryBuilder::PARAM_STR))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setObjectId('');
		$room->setObjectType('');
	}

	/**
	 * @psalm-param Room::OBJECT_TYPE_* $objectType
	 */
	public function setObject(Room $room, string $objectType, string $objectId): void {
		if (($objectId !== '' && $objectType === '') || ($objectId === '' && $objectType !== '')) {
			throw new InvalidRoomException('Object ID and Object Type must both be empty or both have values');
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('object_id', $update->createNamedParameter($objectId, IQueryBuilder::PARAM_STR))
			->set('object_type', $update->createNamedParameter($objectType, IQueryBuilder::PARAM_STR))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setObjectId($objectId);
		$room->setObjectType($objectType);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function parseRoomTokenFromUrl(string $url): string {
		// Check if room exists and check if user is part of room
		$array = explode('/', $url);
		$token = end($array);
		// Cut off any excess characters from the room token
		if (str_contains($token, '?')) {
			$token = substr($token, 0, strpos($token, '?'));
		}
		if (str_contains($token, '#')) {
			$token = substr($token, 0, strpos($token, '#'));
		}

		if (!$token) {
			throw new RoomNotFoundException();
		}
		return $token;
	}

	public function hasExistingCalendarEvents(Room $room, string $userId, string $eventUid) : bool {
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId);
		if (!empty($calendars)) {
			$searchProperties = ['LOCATION'];
			foreach ($calendars as $calendar) {
				$searchResult = $calendar->search($room->getToken(), $searchProperties, [], 2);
				foreach ($searchResult as $result) {
					foreach ($result['objects'] as $object) {
						if ($object['UID'][0] !== $eventUid) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}
}
