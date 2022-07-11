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
use OCA\Talk\BackgroundJob\ExpireChatMessages;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\ModifyLobbyEvent;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Events\VerifyRoomPasswordEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\HintException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\IHasher;
use OCP\Share\IManager as IShareManager;

class RoomService {
	protected Manager $manager;
	protected ChatManager $chatManager;
	protected ParticipantService $participantService;
	protected IDBConnection $db;
	protected ITimeFactory $timeFactory;
	protected IShareManager $shareManager;
	protected IHasher $hasher;
	protected IEventDispatcher $dispatcher;
	protected IJobList $jobList;

	public function __construct(Manager $manager,
								ChatManager $chatManager,
								ParticipantService $participantService,
								IDBConnection $db,
								ITimeFactory $timeFactory,
								IShareManager $shareManager,
								IHasher $hasher,
								IEventDispatcher $dispatcher,
								IJobList $jobList) {
		$this->manager = $manager;
		$this->chatManager = $chatManager;
		$this->participantService = $participantService;
		$this->db = $db;
		$this->timeFactory = $timeFactory;
		$this->shareManager = $shareManager;
		$this->hasher = $hasher;
		$this->dispatcher = $dispatcher;
		$this->jobList = $jobList;
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
		} catch (RoomNotFoundException $e) {
			if (!$this->shareManager->currentUserCanEnumerateTargetUser($actor, $targetUser)) {
				throw new RoomNotFoundException();
			};

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
		if ($name === '' || isset($name[255])) {
			throw new InvalidArgumentException('name');
		}

		if (!\in_array($type, [
			Room::TYPE_GROUP,
			Room::TYPE_PUBLIC,
			Room::TYPE_CHANGELOG,
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
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return false;
		}

		if ($level === 'default') {
			$oldPermissions = $room->getDefaultPermissions();
		} elseif ($level === 'call') {
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

		$event = new ModifyRoomEvent($room, $level . 'Permissions', $newPermissions, $oldPermissions);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_PERMISSIONS_SET, $event);

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

		$this->dispatcher->dispatch(Room::EVENT_AFTER_PERMISSIONS_SET, $event);

		return true;
	}

	public function setSIPEnabled(Room $room, int $newSipEnabled): bool {
		$oldSipEnabled = $room->getSIPEnabled();

		if ($newSipEnabled === $oldSipEnabled) {
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

		$event = new ModifyRoomEvent($room, 'sipEnabled', $newSipEnabled, $oldSipEnabled);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_SIP_ENABLED_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('sip_enabled', $update->createNamedParameter($newSipEnabled, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setSIPEnabled($newSipEnabled);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_SIP_ENABLED_SET, $event);

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
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setLobby(Room $room, int $newState, ?\DateTime $dateTime, bool $timerReached = false): bool {
		$oldState = $room->getLobbyState();

		if (!in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return false;
		}

		if ($room->getObjectType() !== '') {
			return false;
		}

		if (!in_array($newState, [Webinary::LOBBY_NON_MODERATORS, Webinary::LOBBY_NONE], true)) {
			return false;
		}

		$event = new ModifyLobbyEvent($room, 'lobby', $newState, $oldState, $dateTime, $timerReached);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_LOBBY_STATE_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('lobby_state', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->set('lobby_timer', $update->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setLobbyState($newState);
		$room->setLobbyTimer($dateTime);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_LOBBY_STATE_SET, $event);

		return true;
	}

	/**
	 * @param Room $room
	 * @param int $newType Currently it is only allowed to change between `Room::TYPE_GROUP` and `Room::TYPE_PUBLIC`
	 * @param bool $allowSwitchingOneToOne
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setType(Room $room, int $newType, bool $allowSwitchingOneToOne = false): bool {
		if ($newType === $room->getType()) {
			return true;
		}

		if (!$allowSwitchingOneToOne && $room->getType() === Room::TYPE_ONE_TO_ONE) {
			return false;
		}

		if (!in_array($newType, [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			return false;
		}

		$oldType = $room->getType();

		$event = new ModifyRoomEvent($room, 'type', $newType, $oldType);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_TYPE_SET, $event);

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

		$this->dispatcher->dispatch(Room::EVENT_AFTER_TYPE_SET, $event);

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
			return false;
		}

		if (!in_array($newState, [Room::READ_ONLY, Room::READ_WRITE], true)) {
			return false;
		}

		$event = new ModifyRoomEvent($room, 'readOnly', $newState, $oldState);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_READONLY_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('read_only', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setReadOnly($newState);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_READONLY_SET, $event);

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

		if (!in_array($newState, [
			Room::LISTABLE_NONE,
			Room::LISTABLE_USERS,
			Room::LISTABLE_ALL,
		], true)) {
			return false;
		}

		$event = new ModifyRoomEvent($room, 'listable', $newState, $oldState);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_LISTABLE_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('listable', $update->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setListable($newState);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_LISTABLE_SET, $event);

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
	 * @param string $description
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

		$event = new ModifyRoomEvent($room, 'description', $description, $oldDescription);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_DESCRIPTION_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('description', $update->createNamedParameter($description))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setDescription($description);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_DESCRIPTION_SET, $event);

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

		if ($password !== '') {
			$event = new ValidatePasswordPolicyEvent($password);
			$this->dispatcher->dispatchTyped($event);
		}

		$hash = $password !== '' ? $this->hasher->hash($password) : '';

		$event = new ModifyRoomEvent($room, 'password', $password);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_PASSWORD_SET, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('password', $update->createNamedParameter($hash))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setPassword($hash);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_PASSWORD_SET, $event);

		return true;
	}

	public function verifyPassword(Room $room, string $password): array {
		$event = new VerifyRoomPasswordEvent($room, $password);
		$this->dispatcher->dispatch(Room::EVENT_PASSWORD_VERIFY, $event);

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

	public function setMessageExpiration(Room $room, Participant $participant, int $seconds): void {
		$event = new ModifyRoomEvent($room, 'messageExpiration', $seconds, null, $participant);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_SET_MESSAGE_EXPIRATION, $event);

		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			->set('message_expiration', $update->createNamedParameter($seconds, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		$room->setMessageExpiration($seconds);

		$this->toggleJobExpireMesssageIfNecessary($seconds);
		$this->addMessageExpirationSystemMessage($room, $participant, $seconds);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_SET_MESSAGE_EXPIRATION, $event);
	}

	private function toggleJobExpireMesssageIfNecessary(int $seconds): void {
		if ($seconds > 0) {
			if (!$this->jobList->has(ExpireChatMessages::class, null)) {
				$this->jobList->add(ExpireChatMessages::class, null);
			}
			return;
		}
		$this->disableExpireMessageJobIfNecesary();
	}

	private function disableExpireMessageJobIfNecesary(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'total'))
			->from('talk_rooms')
			->where($qb->expr()->gt('message_expiration', $qb->createNamedParameter(0)));
		$result = $qb->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		if (!$count) {
			$this->jobList->remove(ExpireChatMessages::class, null);
		}
	}

	private function addMessageExpirationSystemMessage(Room $room, Participant $participant, int $seconds): void {
		if ($seconds > 0) {
			$message = 'message_expiration_enabled';
		} else {
			$message = 'message_expiration_disabled';
		}
		$this->chatManager->addSystemMessage(
			$room,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			json_encode([
				'message' => $message,
				'parameters' => ['seconds' => $seconds]
			]),
			$this->timeFactory->getDateTime(),
			false
		);
	}
}
