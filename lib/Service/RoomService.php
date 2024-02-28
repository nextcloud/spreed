<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

use InvalidArgumentException;
use OCA\Talk\Config;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\BeforeLobbyModifiedEvent;
use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Events\BeforeRoomModifiedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\RoomPasswordVerifyEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\HintException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\IHasher;
use OCP\Share\IManager as IShareManager;

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
			throw new InvalidArgumentException('invalid_invitee');
		}

		try {
			// If room exists: Reuse that one, otherwise create a new one.
			$room = $this->manager->getOne2OneRoom($actor->getUID(), $targetUser->getUID());
			$this->participantService->ensureOneToOneRoomIsFilled($room);
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
				[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $targetUser->getUID(),
					'displayName' => $targetUser->getDisplayName(),
					'participantType' => Participant::OWNER,
				],
			], $actor);
		}

		return $room;
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @param IUser|null $owner
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 * @throws InvalidArgumentException on too long or empty names
	 * @throws InvalidArgumentException unsupported type
	 * @throws InvalidArgumentException invalid object data
	 */
	public function createConversation(int $type, string $name, ?IUser $owner = null, string $objectType = '', string $objectId = ''): Room {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new InvalidArgumentException('name');
		}

		if (!\in_array($type, [
			Room::TYPE_GROUP,
			Room::TYPE_PUBLIC,
			Room::TYPE_CHANGELOG,
			Room::TYPE_NOTE_TO_SELF,
		], true)) {
			throw new InvalidArgumentException('type');
		}

		$objectType = trim($objectType);
		if (isset($objectType[64])) {
			throw new InvalidArgumentException('object_type');
		}

		$objectId = trim($objectId);
		if (isset($objectId[64])) {
			throw new InvalidArgumentException('object_id');
		}

		if (($objectType !== '' && $objectId === '') ||
			($objectType === '' && $objectId !== '')) {
			throw new InvalidArgumentException('object');
		}

		$room = $this->manager->createRoom($type, $name, $objectType, $objectId);

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

	public function setPermissions(Room $room, string $level, string $method, int $permissions, bool $resetCustomPermissions): bool {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return false;
		}

		if ($room->getType() === Room::TYPE_NOTE_TO_SELF) {
			return false;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			// Do not allow manual changing the permissions in breakout rooms
			return false;
		}

		if ($level === 'default') {
			$property = ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS;
			$oldPermissions = $room->getDefaultPermissions();
		} elseif ($level === 'call') {
			$property = ARoomModifiedEvent::PROPERTY_CALL_PERMISSIONS;
			$oldPermissions = $room->getCallPermissions();
		} else {
			return false;
		}

		$newPermissions = $permissions;
		if ($method === Attendee::PERMISSIONS_MODIFY_SET) {
			if ($newPermissions !== Attendee::PERMISSIONS_DEFAULT) {
				// Make sure the custom flag is set when not setting to default permissions
				$newPermissions |= Attendee::PERMISSIONS_CUSTOM;
			}
			// If we are setting a fixed set of permissions and apply that to users,
			// we can also simplify it and reset to default.
			$resetCustomPermissions = true;
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_ADD) {
			$newPermissions = $oldPermissions | $newPermissions;
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_REMOVE) {
			$newPermissions = $oldPermissions & ~$newPermissions;
		} else {
			return false;
		}

		$event = new BeforeRoomModifiedEvent($room, $property, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);

		if ($resetCustomPermissions) {
			$this->participantService->updateAllPermissions($room, Attendee::PERMISSIONS_MODIFY_SET, Attendee::PERMISSIONS_DEFAULT);
		} else {
			$this->participantService->updateAllPermissions($room, $method, $permissions);
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set($level . '_permissions', $update->createNamedParameter($newPermissions, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		if ($level === 'default') {
			$room->setDefaultPermissions($newPermissions);
		} else {
			$room->setCallPermissions($newPermissions);
		}

		$event = new RoomModifiedEvent($room, $property, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);

		return true;
	}

	public function setSIPEnabled(Room $room, int $newSipEnabled): bool {
		$oldSipEnabled = $room->getSIPEnabled();

		if ($newSipEnabled === $oldSipEnabled) {
			return false;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			return false;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return false;
		}

		if (!in_array($newSipEnabled, [Webinary::SIP_ENABLED_NO_PIN, Webinary::SIP_ENABLED, Webinary::SIP_DISABLED], true)) {
			return false;
		}

		if (preg_match(Room::SIP_INCOMPATIBLE_REGEX, $room->getToken())) {
			return false;
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

		return true;
	}

	/**
	 * @psalm-param RecordingService::CONSENT_REQUIRED_* $recordingConsent
	 * @throws InvalidArgumentException When the room has an active call or the value is invalid
	 */
	public function setRecordingConsent(Room $room, int $recordingConsent, bool $allowUpdatingBreakoutRooms = false): void {
		$oldRecordingConsent = $room->getRecordingConsent();

		if ($recordingConsent === $oldRecordingConsent) {
			return;
		}

		if (!in_array($recordingConsent, [RecordingService::CONSENT_REQUIRED_NO, RecordingService::CONSENT_REQUIRED_YES], true)) {
			throw new InvalidArgumentException('value');
		}

		if ($recordingConsent !== RecordingService::CONSENT_REQUIRED_NO && $room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
			throw new InvalidArgumentException('call');
		}

		if (!$allowUpdatingBreakoutRooms && $room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new InvalidArgumentException('breakout-room');
		}

		if ($room->getBreakoutRoomStatus() !== BreakoutRoom::STATUS_STOPPED) {
			throw new InvalidArgumentException('breakout-room');
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
	 * @param string $newName Currently it is only allowed to rename: self::TYPE_GROUP, self::TYPE_PUBLIC
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setName(Room $room, string $newName, ?string $oldName = null): bool {
		$oldName = $oldName !== null ? $oldName : $room->getName();
		if ($newName === $oldName) {
			return false;
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

		return true;
	}

	/**
	 * @param Room $room
	 * @param int $newState Currently it is only allowed to change between
	 * 						`Webinary::LOBBY_NON_MODERATORS` and `Webinary::LOBBY_NONE`
	 * 						Also it's not allowed in one-to-one conversations,
	 * 						file conversations and password request conversations.
	 * @param \DateTime|null $dateTime
	 * @param bool $timerReached
	 * @param bool $dispatchEvents (Only skip if the room is created in the same PHP request)
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setLobby(Room $room, int $newState, ?\DateTime $dateTime, bool $timerReached = false, bool $dispatchEvents = true): bool {
		$oldState = $room->getLobbyState(false);

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return false;
		}

		if ($room->getObjectType() !== '' && $room->getObjectType() !== BreakoutRoom::PARENT_OBJECT_TYPE) {
			return false;
		}

		if (!in_array($newState, [Webinary::LOBBY_NON_MODERATORS, Webinary::LOBBY_NONE], true)) {
			return false;
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

		return true;
	}

	public function setAvatar(Room $room, string $avatar): bool {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			return false;
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
		return true;
	}

	/**
	 * @param Room $room
	 * @param integer $status 0 none|1 video|2 audio
	 * @param Participant|null $participant the Participant that changed the
	 *        state, null for the current user
	 * @throws InvalidArgumentException When the status is invalid, not Room::RECORDING_*
	 * @throws InvalidArgumentException When trying to start
	 */
	public function setCallRecording(Room $room, int $status = Room::RECORDING_NONE, ?Participant $participant = null): void {
		if (!$this->config->isRecordingEnabled() && $status !== Room::RECORDING_NONE) {
			throw new InvalidArgumentException('config');
		}

		$availableRecordingStatus = [Room::RECORDING_NONE, Room::RECORDING_VIDEO, Room::RECORDING_AUDIO, Room::RECORDING_VIDEO_STARTING, Room::RECORDING_AUDIO_STARTING, Room::RECORDING_FAILED];
		if (!in_array($status, $availableRecordingStatus)) {
			throw new InvalidArgumentException('status');
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
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setType(Room $room, int $newType, bool $allowSwitchingOneToOne = false): bool {
		$oldType = $room->getType();
		if ($oldType === $newType) {
			return true;
		}

		if (!$allowSwitchingOneToOne && $oldType === Room::TYPE_ONE_TO_ONE) {
			return false;
		}

		if ($oldType === Room::TYPE_ONE_TO_ONE_FORMER) {
			return false;
		}

		if ($oldType === Room::TYPE_NOTE_TO_SELF) {
			return false;
		}

		if (!in_array($newType, [Room::TYPE_GROUP, Room::TYPE_PUBLIC, Room::TYPE_ONE_TO_ONE_FORMER], true)) {
			return false;
		}

		if ($newType === Room::TYPE_ONE_TO_ONE_FORMER && $oldType !== Room::TYPE_ONE_TO_ONE) {
			return false;
		}

		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			return false;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			return false;
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

		return true;
	}

	/**
	 * @param Room $room
	 * @param int $newState Currently it is only allowed to change between
	 * 						`Room::READ_ONLY` and `Room::READ_WRITE`
	 * 						Also it's only allowed on rooms of type
	 * 						`Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setReadOnly(Room $room, int $newState): bool {
		$oldState = $room->getReadOnly();
		if ($newState === $oldState) {
			return true;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC, Room::TYPE_CHANGELOG], true)) {
			if ($newState !== Room::READ_ONLY || $room->getType() !== Room::TYPE_ONE_TO_ONE_FORMER) {
				// Allowed for the automated conversation of one-to-one chats to read only former
				return false;
			}
		}

		if (!in_array($newState, [Room::READ_ONLY, Room::READ_WRITE], true)) {
			return false;
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

		return true;
	}

	/**
	 * @param Room $room
	 * @param int $newState New listable scope from self::LISTABLE_*
	 * 						Also it's only allowed on rooms of type
	 * 						`Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setListable(Room $room, int $newState): bool {
		$oldState = $room->getListable();
		if ($newState === $oldState) {
			return true;
		}

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return false;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			return false;
		}

		if (!in_array($newState, [
			Room::LISTABLE_NONE,
			Room::LISTABLE_USERS,
			Room::LISTABLE_ALL,
		], true)) {
			return false;
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

		return true;
	}

	public function setAssignedSignalingServer(Room $room, ?int $signalingServer): bool {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('assigned_hpb', $update->createNamedParameter($signalingServer))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));

		if ($signalingServer !== null) {
			$update->andWhere($update->expr()->isNull('assigned_hpb'));
		}

		$updated = (bool) $update->executeStatement();
		if ($updated) {
			$room->setAssignedSignalingServer($signalingServer);
		}

		return $updated;
	}

	/**
	 * @return bool True when the change was valid, false otherwise
	 * @throws \LengthException when the given description is too long
	 */
	public function setDescription(Room $room, string $description): bool {
		$description = trim($description);

		if (mb_strlen($description) > Room::DESCRIPTION_MAXIMUM_LENGTH) {
			throw new \LengthException('Conversation description is limited to ' . Room::DESCRIPTION_MAXIMUM_LENGTH . ' characters');
		}

		$oldDescription = $room->getDescription();
		if ($description === $oldDescription) {
			return false;
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

		return true;
	}

	/**
	 * @param string $password Currently it is only allowed to have a password for Room::TYPE_PUBLIC
	 * @return bool True when the change was valid, false otherwise
	 * @throws HintException
	 */
	public function setPassword(Room $room, string $password): bool {
		if ($room->getType() !== Room::TYPE_PUBLIC) {
			return false;
		}

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			return false;
		}

		if ($password !== '') {
			$event = new ValidatePasswordPolicyEvent($password);
			$this->dispatcher->dispatchTyped($event);
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

		return true;
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
	 * @throws InvalidArgumentException When the room is a breakout room
	 */
	public function setMessageExpiration(Room $room, int $seconds): void {
		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			throw new InvalidArgumentException('room');
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

	public function setBreakoutRoomMode(Room $room, int $mode): bool {
		if (!in_array($mode, [
			BreakoutRoom::MODE_NOT_CONFIGURED,
			BreakoutRoom::MODE_AUTOMATIC,
			BreakoutRoom::MODE_MANUAL,
			BreakoutRoom::MODE_FREE
		], true)) {
			return false;
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

		return true;
	}

	public function setBreakoutRoomStatus(Room $room, int $status): bool {
		if (!in_array($status, [
			BreakoutRoom::STATUS_STOPPED,
			BreakoutRoom::STATUS_STARTED,
			BreakoutRoom::STATUS_ASSISTANCE_RESET,
			BreakoutRoom::STATUS_ASSISTANCE_REQUESTED,
		], true)) {
			return false;
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

		return true;
	}

	public function resetActiveSince(Room $room): bool {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('active_guests', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('active_since', $update->createNamedParameter(null, IQueryBuilder::PARAM_DATE))
			->set('call_flag', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('call_permissions', $update->createNamedParameter(Attendee::PERMISSIONS_DEFAULT, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->isNotNull('active_since'));

		$room->resetActiveSince();

		return (bool) $update->executeStatement();
	}

	public function setActiveSince(Room $room, \DateTime $since, int $callFlag, bool $isGuest): bool {
		if ($isGuest && $room->getType() === Room::TYPE_PUBLIC) {
			$update = $this->db->getQueryBuilder();
			$update->update('talk_rooms')
				->set('active_guests', $update->createFunction($update->getColumnName('active_guests') . ' + 1'))
				->set(
					'call_flag',
					$update->expr()->bitwiseOr('call_flag', $callFlag)
				)
				->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		} elseif (!$isGuest) {
			$update = $this->db->getQueryBuilder();
			$update->update('talk_rooms')
				->set(
					'call_flag',
					$update->expr()->bitwiseOr('call_flag', $callFlag)
				)
				->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		}

		if ($room->getActiveSince() instanceof \DateTime) {
			$room->setActiveSince($room->getActiveSince(), $callFlag, $isGuest);
			return false;
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('active_since', $update->createNamedParameter($since, IQueryBuilder::PARAM_DATE))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->isNull('active_since'));
		$update->executeStatement();

		$room->setActiveSince($since, $callFlag, $isGuest);

		return true;
	}

	public function setLastMessage(Room $room, IComment $message): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_message', $update->createNamedParameter((int) $message->getId()))
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

	public function setLastActivity(Room $room, \DateTime $now): void {
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_activity', $update->createNamedParameter($now, 'datetime'))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLastActivity($now);
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
}
