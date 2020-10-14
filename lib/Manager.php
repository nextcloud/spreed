<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk;

use OCA\Talk\Chat\Changelog;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Events\CreateRoomTokenEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;

class Manager {
	public const EVENT_TOKEN_GENERATE = self::class . '::generateNewToken';

	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IUserManager */
	private $userManager;
	/** @var CommentsManager */
	private $commentsManager;
	/** @var TalkSession */
	private $talkSession;
	/** @var IEventDispatcher */
	private $dispatcher;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IHasher */
	private $hasher;
	/** @var IL10N */
	private $l;

	public function __construct(IDBConnection $db,
								IConfig $config,
								ISecureRandom $secureRandom,
								IUserManager $userManager,
								CommentsManager $commentsManager,
								TalkSession $talkSession,
								IEventDispatcher $dispatcher,
								ITimeFactory $timeFactory,
								IHasher $hasher,
								IL10N $l) {
		$this->db = $db;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->userManager = $userManager;
		$this->commentsManager = $commentsManager;
		$this->talkSession = $talkSession;
		$this->dispatcher = $dispatcher;
		$this->timeFactory = $timeFactory;
		$this->hasher = $hasher;
		$this->l = $l;
	}

	public function forAllRooms(callable $callback): void {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$room = $this->createRoomObject($row);
			$callback($room);
		}
		$result->closeCursor();
	}

	/**
	 * @param array $row
	 * @return Room
	 */
	public function createRoomObject(array $row): Room {
		$activeSince = null;
		if (!empty($row['active_since'])) {
			$activeSince = $this->timeFactory->getDateTime($row['active_since']);
		}

		$lastActivity = null;
		if (!empty($row['last_activity'])) {
			$lastActivity = $this->timeFactory->getDateTime($row['last_activity']);
		}

		$lobbyTimer = null;
		if (!empty($row['lobby_timer'])) {
			$lobbyTimer = $this->timeFactory->getDateTime($row['lobby_timer']);
		}

		$lastMessage = null;
		if (!empty($row['comment_id'])) {
			$lastMessage = $this->createCommentObject($row);
		}

		$assignedSignalingServer = $row['assigned_hpb'];
		if ($assignedSignalingServer !== null) {
			$assignedSignalingServer = (int) $assignedSignalingServer;
		}

		return new Room(
			$this,
			$this->db,
			$this->secureRandom,
			$this->dispatcher,
			$this->timeFactory,
			$this->hasher,
			(int) $row['id'],
			(int) $row['type'],
			(int) $row['read_only'],
			(int) $row['lobby_state'],
			(int) $row['sip_enabled'],
			$assignedSignalingServer,
			(string) $row['token'],
			(string) $row['name'],
			(string) $row['password'],
			(int) $row['active_guests'],
			$activeSince,
			$lastActivity,
			(int) $row['last_message'],
			$lastMessage,
			$lobbyTimer,
			(string) $row['object_type'],
			(string) $row['object_id']
		);
	}

	/**
	 * @param Room $room
	 * @param array $row
	 * @return Participant
	 */
	public function createParticipantObject(Room $room, array $row): Participant {
		$lastJoinedCall = null;
		if (!empty($row['last_joined_call'])) {
			$lastJoinedCall = $this->timeFactory->getDateTime($row['last_joined_call']);
		}

		return new Participant(
			$this->db,
			$this->config,
			$room,
			(string) $row['user_id'],
			(int) $row['participant_type'],
			(int) $row['last_ping'],
			(string) $row['session_id'],
			(int) $row['in_call'],
			(int) $row['notification_level'],
			(bool) $row['favorite'],
			(int) $row['last_read_message'],
			(int) $row['last_mention_message'],
			$lastJoinedCall
		);
	}

	public function createCommentObject(array $row): ?IComment {
		return $this->commentsManager->getCommentFromData([
			'id'				=> $row['comment_id'],
			'parent_id'			=> $row['comment_parent_id'],
			'topmost_parent_id'	=> $row['comment_topmost_parent_id'],
			'children_count'	=> $row['comment_children_count'],
			'message'			=> $row['comment_message'],
			'verb'				=> $row['comment_verb'],
			'actor_type'		=> $row['comment_actor_type'],
			'actor_id'			=> $row['comment_actor_id'],
			'object_type'		=> $row['comment_object_type'],
			'object_id'			=> $row['comment_object_id'],
			// Reference id column might not be there, so we need to fallback to null
			'reference_id'		=> $row['comment_reference_id'] ?? null,
			'creation_timestamp'		=> $row['comment_creation_timestamp'],
			'latest_child_timestamp'	=> $row['comment_latest_child_timestamp'],
		]);
	}

	public function loadLastCommentInfo(int $id): ?IComment {
		try {
			return $this->commentsManager->get((string)$id);
		} catch (NotFoundException $e) {
			return null;
		}
	}

	public function resetAssignedSignalingServers(ICache $cache): void {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->isNotNull('assigned_hpb'));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$room = $this->createRoomObject($row);
			if (!$room->hasActiveSessions()) {
				$room->setAssignedSignalingServer(null);
				$cache->remove($room->getToken());
			}
		}
		$result->closeCursor();
	}

	/**
	 * @param string $searchToken
	 * @param int $limit
	 * @param int $offset
	 * @return Room[]
	 */
	public function searchRoomsByToken(string $searchToken = '', int $limit = null, int $offset = null): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->setMaxResults(1);

		if ($searchToken !== '') {
			$query->where($query->expr()->iLike('token', $query->createNamedParameter(
				'%' . $this->db->escapeLikeParameter($searchToken) . '%'
			)));
		}

		$query->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('token', 'ASC');
		$result = $query->execute();

		$rooms = [];
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$rooms[] = $this->createRoomObject($row);
		}
		$result->closeCursor();

		return $rooms;
	}

	/**
	 * @param string $participant
	 * @param bool $includeLastMessage
	 * @return Room[]
	 */
	public function getRoomsForParticipant(string $participant, bool $includeLastMessage = false): array {
		$query = $this->db->getQueryBuilder();
		$query->select('r.*')->addSelect('p.*')
			->from('talk_rooms', 'r')
			->leftJoin('r', 'talk_participants', 'p', $query->expr()->andX(
				$query->expr()->eq('p.user_id', $query->createNamedParameter($participant)),
				$query->expr()->eq('p.room_id', 'r.id')
			))
			->where($query->expr()->isNotNull('p.user_id'));

		if ($includeLastMessage) {
			$this->loadLastMessageInfo($query);
		}

		$result = $query->execute();
		$rooms = [];
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$room = $this->createRoomObject($row);
			if ($participant !== null && isset($row['user_id'])) {
				$room->setParticipant($row['user_id'], $this->createParticipantObject($room, $row));
			}
			$rooms[] = $room;
		}
		$result->closeCursor();

		return $rooms;
	}

	/**
	 * Does *not* return public rooms for participants that have not been invited
	 *
	 * @param int $roomId
	 * @param string $participant
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForParticipant(int $roomId, ?string $participant): Room {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		if ($participant !== null) {
			// Non guest user
			$query->leftJoin('r', 'talk_participants', 'p', $query->expr()->andX(
					$query->expr()->eq('p.user_id', $query->createNamedParameter($participant)),
					$query->expr()->eq('p.room_id', 'r.id')
				))
				->andWhere($query->expr()->isNotNull('p.user_id'));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		$room = $this->createRoomObject($row);
		if ($participant !== null && isset($row['user_id'])) {
			$room->setParticipant($row['user_id'], $this->createParticipantObject($room, $row));
		}

		if ($participant === null && $room->getType() !== Room::PUBLIC_CALL) {
			throw new RoomNotFoundException();
		}

		return $room;
	}

	/**
	 * Also returns public rooms for participants that have not been invited,
	 * so they can join.
	 *
	 * @param string $token
	 * @param string $participant
	 * @param bool $includeLastMessage
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForParticipantByToken(string $token, ?string $participant, bool $includeLastMessage = false): Room {
		$query = $this->db->getQueryBuilder();
		$query->select('r.*')
			->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.token', $query->createNamedParameter($token)))
			->setMaxResults(1);

		if ($participant !== null) {
			// Non guest user
			$query->addSelect('p.*')
				->leftJoin('r', 'talk_participants', 'p', $query->expr()->andX(
					$query->expr()->eq('p.user_id', $query->createNamedParameter($participant)),
					$query->expr()->eq('p.room_id', 'r.id')
				));
		}

		if ($includeLastMessage) {
			$this->loadLastMessageInfo($query);
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		$room = $this->createRoomObject($row);
		if ($participant !== null && isset($row['user_id'])) {
			$room->setParticipant($row['user_id'], $this->createParticipantObject($room, $row));
		}

		if ($room->getType() === Room::PUBLIC_CALL) {
			return $room;
		}

		if ($participant !== null && $row['user_id'] === $participant) {
			return $room;
		}

		throw new RoomNotFoundException();
	}

	/**
	 * @param int $roomId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomById(int $roomId): Room {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		return $this->createRoomObject($row);
	}

	/**
	 * @param string $token
	 * @param string|null $preloadParticipant Load this participants information if possible
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByToken(string $token, ?string $preloadParticipant = null): Room {
		$preloadParticipant = $preloadParticipant === '' ? null : $preloadParticipant;

		$query = $this->db->getQueryBuilder();
		$query->select('r.*')
			->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.token', $query->createNamedParameter($token)));

		if ($preloadParticipant !== null) {
			$query->addSelect('p.*')
				->leftJoin('r', 'talk_participants', 'p', $query->expr()->andX(
					$query->expr()->eq('p.user_id', $query->createNamedParameter($preloadParticipant)),
					$query->expr()->eq('p.room_id', 'r.id')
				));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		$room = $this->createRoomObject($row);
		if ($preloadParticipant !== null && isset($row['user_id'])) {
			$room->setParticipant($row['user_id'], $this->createParticipantObject($room, $row));
		}

		return $room;
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByObject(string $objectType, string $objectId): Room {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->eq('object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($objectId)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		return $this->createRoomObject($row);
	}

	/**
	 * @param string|null $userId
	 * @param string|null $sessionId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForSession(?string $userId, ?string $sessionId): Room {
		if ($sessionId === '' || $sessionId === '0') {
			throw new RoomNotFoundException();
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_participants', 'p')
			->leftJoin('p', 'talk_rooms', 'r', $query->expr()->eq('p.room_id', 'r.id'))
			->where($query->expr()->eq('p.session_id', $query->createNamedParameter($sessionId)))
			->setMaxResults(1);

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false || !$row['id']) {
			throw new RoomNotFoundException();
		}

		if ((string) $userId !== $row['user_id']) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		$room = $this->createRoomObject($row);
		$participant = $this->createParticipantObject($room, $row);
		$room->setParticipant($row['user_id'], $participant);

		if ($room->getType() === Room::PUBLIC_CALL || !in_array($participant->getParticipantType(), [Participant::GUEST, Participant::USER_SELF_JOINED], true)) {
			return $room;
		}

		throw new RoomNotFoundException();
	}

	/**
	 * @param string $participant1
	 * @param string $participant2
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getOne2OneRoom(string $participant1, string $participant2): Room {
		$users = [$participant1, $participant2];
		sort($users);
		$name = json_encode($users);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->eq('type', $query->createNamedParameter(Room::ONE_TO_ONE_CALL, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		return $this->createRoomObject($row);
	}

	/**
	 * Makes sure the user is part of a changelog room and returns it
	 *
	 * @param string $userId
	 * @return Room
	 */
	public function getChangelogRoom(string $userId): Room {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->eq('type', $query->createNamedParameter(Room::CHANGELOG_CONVERSATION, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($userId)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			$room = $this->createRoom(Room::CHANGELOG_CONVERSATION, $userId);
			$room->addUsers(['userId' => $userId]);
			$room->setReadOnly(Room::READ_ONLY);
			return $room;
		}

		$room = $this->createRoomObject($row);

		try {
			$room->getParticipant($userId);
		} catch (ParticipantNotFoundException $e) {
			$room->addUsers(['userId' => $userId]);
		}

		return $room;
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 */
	public function createRoom(int $type, string $name = '', string $objectType = '', string $objectId = ''): Room {
		$token = $this->getNewToken();

		$query = $this->db->getQueryBuilder();
		$query->insert('talk_rooms')
			->values(
				[
					'name' => $query->createNamedParameter($name),
					'type' => $query->createNamedParameter($type, IQueryBuilder::PARAM_INT),
					'token' => $query->createNamedParameter($token),
				]
			);

		if (!empty($objectType) && !empty($objectId)) {
			$query->setValue('object_type', $query->createNamedParameter($objectType))
				->setValue('object_id', $query->createNamedParameter($objectId));
		}

		$query->execute();
		$roomId = $query->getLastInsertId();

		$room = $this->getRoomById($roomId);

		$event = new RoomEvent($room);
		$this->dispatcher->dispatch(Room::EVENT_AFTER_ROOM_CREATE, $event);

		return $room;
	}

	/**
	 * @param string|null $userId
	 * @return string|null
	 */
	public function getCurrentSessionId(?string $userId): ?string {
		if (empty($userId)) {
			return null;
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_participants')
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->neq('session_id', $query->createNamedParameter('0')))
			->orderBy('last_ping', 'DESC')
			->setMaxResults(1);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return null;
		}

		return $row['session_id'];
	}

	/**
	 * @param string $userId
	 * @return string[]
	 */
	public function getSessionIdsForUser(?string $userId): array {
		if (!is_string($userId) || $userId === '') {
			// No deleting messages for guests
			return [];
		}

		// Delete all messages from or to the current user
		$query = $this->db->getQueryBuilder();
		$query->select('session_id')
			->from('talk_participants')
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		$result = $query->execute();

		$sessionIds = [];
		while ($row = $result->fetch()) {
			if ($row['session_id'] !== '0') {
				$sessionIds[] = $row['session_id'];
			}
		}
		$result->closeCursor();

		return $sessionIds;
	}

	public function resolveRoomDisplayName(Room $room, string $userId): string {
		if ($room->getObjectType() === 'share:password') {
			return $this->l->t('Password request: %s', [$room->getName()]);
		}
		if ($room->getType() === Room::CHANGELOG_CONVERSATION) {
			return $this->l->t('Talk updates ✅');
		}
		if ($userId === '' && $room->getType() !== Room::PUBLIC_CALL) {
			return $this->l->t('Private conversation');
		}


		if ($room->getType() !== Room::ONE_TO_ONE_CALL && $room->getName() === '') {
			$room->setName($this->getRoomNameByParticipants($room));
		}

		// Set the room name to the other participant for one-to-one rooms
		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			if ($userId === '') {
				return $this->l->t('Private conversation');
			}

			$users = json_decode($room->getName(), true);
			$otherParticipant = '';
			$userIsParticipant = false;

			foreach ($users as $participantId) {
				if ($participantId !== $userId) {
					$user = $this->userManager->get($participantId);
					$otherParticipant = $user instanceof IUser ? $user->getDisplayName() : $participantId;
				} else {
					$userIsParticipant = true;
				}
			}

			if (!$userIsParticipant) {
				// Do not leak the name of rooms the user is not a part of
				return $this->l->t('Private conversation');
			}

			if ($otherParticipant === '' && $room->getName() !== '') {
				$user = $this->userManager->get($room->getName());
				$otherParticipant = $user instanceof IUser ? $user->getDisplayName() : $this->l->t('Deleted user (%s)', $room->getName());
			}

			return $otherParticipant;
		}

		try {
			if ($userId === '') {
				$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
				$room->getParticipantBySession($sessionId);
			} else {
				$room->getParticipant($userId);
			}
		} catch (ParticipantNotFoundException $e) {
			// Do not leak the name of rooms the user is not a part of
			return $this->l->t('Private conversation');
		}

		return $room->getName();
	}

	protected function getRoomNameByParticipants(Room $room): string {
		$users = $room->getParticipantUserIds();
		$displayNames = [];

		foreach ($users as $participantId) {
			$user = $this->userManager->get($participantId);
			$displayNames[] = $user instanceof IUser ? $user->getDisplayName() : $participantId;
		}

		$roomName = implode(', ', $displayNames);
		if (mb_strlen($roomName) > 64) {
			$roomName = mb_substr($roomName, 0, 60) . '…';
		}
		return $roomName;
	}

	/**
	 * @return string
	 */
	protected function getNewToken(): string {
		$sipConfig = $this->config->getAppValue('spreed', 'sip_config', ''); // FIXME adjust config name
		$entropy = (int) $this->config->getAppValue('spreed', 'token_entropy', 8);
		$entropy = max(8, $entropy); // For update cases
		$digitsOnly = $sipConfig !== '';
		if ($digitsOnly) {
			// Increase default token length as we only use numbers
			$entropy = max(10, $entropy);
		}

		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createParameter('token')));

		$i = 0;
		while ($i < 1000) {
			try {
				$token = $this->generateNewToken($query, $entropy, $digitsOnly);
				if (\in_array($token, ['settings', 'backend'], true)) {
					throw new \OutOfBoundsException('Reserved word');
				}
				return $token;
			} catch (\OutOfBoundsException $e) {
				$i++;
				if ($entropy >= 30 || $i >= 999) {
					// Max entropy of 30
					$i = 0;
				}
			}
		}

		$entropy++;
		$this->config->setAppValue('spreed', 'token_entropy', $entropy);
		return $this->generateNewToken($query, $entropy, $digitsOnly);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param int $entropy
	 * @param bool $digitsOnly
	 * @return string
	 * @throws \OutOfBoundsException
	 */
	protected function generateNewToken(IQueryBuilder $query, int $entropy, bool $digitsOnly): string {
		if (!$digitsOnly) {
			$chars = str_replace(['l', '0', '1'], '', ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
			$token = $this->secureRandom->generate($entropy, $chars);
		} else {
			$chars = ISecureRandom::CHAR_DIGITS;
			$token = '';
			// Do not allow to start with a '0' as that is a special mode on the phone server
			// Also there are issues with some providers when you enter the same number twice
			// consecutive too fast, so we avoid this as well.
			$lastDigit = '0';
			for ($i = 0; $i < $entropy; $i++) {
				$lastDigit = $this->secureRandom->generate(1,
					str_replace($lastDigit, '', $chars)
				);
				$token .= $lastDigit;
			}
		}

		$query->setParameter('token', $token);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (is_array($row)) {
			// Token already in use
			throw new \OutOfBoundsException();
		}
		return $token;
	}

	public function isValidParticipant(string $userId): bool {
		return $this->userManager->userExists($userId);
	}

	protected function loadLastMessageInfo(IQueryBuilder $query): void {
		$query->leftJoin('r','comments', 'c', $query->expr()->eq('r.last_message', 'c.id'));
		$query->selectAlias('c.id', 'comment_id');
		$query->selectAlias('c.parent_id', 'comment_parent_id');
		$query->selectAlias('c.topmost_parent_id', 'comment_topmost_parent_id');
		$query->selectAlias('c.children_count', 'comment_children_count');
		$query->selectAlias('c.message', 'comment_message');
		$query->selectAlias('c.verb', 'comment_verb');
		$query->selectAlias('c.actor_type', 'comment_actor_type');
		$query->selectAlias('c.actor_id', 'comment_actor_id');
		$query->selectAlias('c.object_type', 'comment_object_type');
		$query->selectAlias('c.object_id', 'comment_object_id');
		if ($this->config->getAppValue('spreed', 'has_reference_id', 'no') === 'yes') {
			// Only try to load the reference_id column when it should be there
			$query->selectAlias('c.reference_id', 'comment_reference_id');
		}
		$query->selectAlias('c.creation_timestamp', 'comment_creation_timestamp');
		$query->selectAlias('c.latest_child_timestamp', 'comment_latest_child_timestamp');
	}
}
