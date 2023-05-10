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

use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\SelectHelper;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Server;

class Manager {
	public const EVENT_TOKEN_GENERATE = self::class . '::generateNewToken';

	protected IDBConnection $db;
	protected IConfig $config;
	protected Config $talkConfig;
	protected IAppManager $appManager;
	protected AttendeeMapper $attendeeMapper;
	protected SessionMapper $sessionMapper;
	protected ParticipantService $participantService;
	protected ISecureRandom $secureRandom;
	protected IUserManager $userManager;
	protected IGroupManager $groupManager;
	protected ICommentsManager $commentsManager;
	protected TalkSession $talkSession;
	protected IEventDispatcher $dispatcher;
	protected ITimeFactory $timeFactory;
	protected IHasher $hasher;
	protected IL10N $l;

	public function __construct(
		IDBConnection $db,
		IConfig $config,
		Config $talkConfig,
		IAppManager $appManager,
		AttendeeMapper $attendeeMapper,
		SessionMapper $sessionMapper,
		ParticipantService $participantService,
		ISecureRandom $secureRandom,
		IUserManager $userManager,
		IGroupManager $groupManager,
		CommentsManager $commentsManager,
		TalkSession $talkSession,
		IEventDispatcher $dispatcher,
		ITimeFactory $timeFactory,
		IHasher $hasher,
		IL10N $l,
	) {
		$this->db = $db;
		$this->config = $config;
		$this->talkConfig = $talkConfig;
		$this->appManager = $appManager;
		$this->attendeeMapper = $attendeeMapper;
		$this->sessionMapper = $sessionMapper;
		$this->participantService = $participantService;
		$this->secureRandom = $secureRandom;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->commentsManager = $commentsManager;
		$this->talkSession = $talkSession;
		$this->dispatcher = $dispatcher;
		$this->timeFactory = $timeFactory;
		$this->hasher = $hasher;
		$this->l = $l;
	}

	public function forAllRooms(callable $callback): void {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r');

		$result = $query->executeQuery();
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
	 * @param array $data
	 * @return Room
	 */
	public function createRoomObjectFromData(array $data): Room {
		return $this->createRoomObject(array_merge([
			'r_id' => 0,
			'type' => 0,
			'read_only' => 0,
			'listable' => 0,
			'message_expiration' => 0,
			'lobby_state' => 0,
			'sip_enabled' => 0,
			'assigned_hpb' => null,
			'token' => '',
			'name' => '',
			'description' => '',
			'password' => '',
			'avatar' => '',
			'remote_server' => '',
			'remote_token' => '',
			'active_guests' => 0,
			'default_permissions' => 0,
			'call_permissions' => 0,
			'call_flag' => 0,
			'active_since' => null,
			'last_activity' => null,
			'last_message' => 0,
			'comment_id' => null,
			'lobby_timer' => null,
			'object_type' => '',
			'object_id' => '',
			'breakout_room_mode' => 0,
			'breakout_room_status' => 0,
			'call_recording' => 0,
		], $data));
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
			$this->dispatcher,
			$this->timeFactory,
			(int) $row['r_id'],
			(int) $row['type'],
			(int) $row['read_only'],
			(int) $row['listable'],
			(int) $row['message_expiration'],
			(int) $row['lobby_state'],
			(int) $row['sip_enabled'],
			$assignedSignalingServer,
			(string) $row['token'],
			(string) $row['name'],
			(string) $row['description'],
			(string) $row['password'],
			(string) $row['avatar'],
			(string) $row['remote_server'],
			(string) $row['remote_token'],
			(int) $row['active_guests'],
			(int) $row['default_permissions'],
			(int) $row['call_permissions'],
			(int) $row['call_flag'],
			$activeSince,
			$lastActivity,
			(int) $row['last_message'],
			$lastMessage,
			$lobbyTimer,
			(string) $row['object_type'],
			(string) $row['object_id'],
			(int) $row['breakout_room_mode'],
			(int) $row['breakout_room_status'],
			(int) $row['call_recording']
		);
	}

	/**
	 * @param Room $room
	 * @param array $row
	 * @return Participant
	 */
	public function createParticipantObject(Room $room, array $row): Participant {
		$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
		$session = null;
		if (!empty($row['s_id'])) {
			$session = $this->sessionMapper->createSessionFromRow($row);
		}

		return new Participant($room, $attendee, $session);
	}

	public function createCommentObject(array $row): ?IComment {
		/** @psalm-suppress UndefinedInterfaceMethod */
		return $this->commentsManager->getCommentFromData([
			'id' => $row['comment_id'],
			'parent_id' => $row['comment_parent_id'],
			'topmost_parent_id' => $row['comment_topmost_parent_id'],
			'children_count' => $row['comment_children_count'],
			'message' => $row['comment_message'],
			'verb' => $row['comment_verb'],
			'actor_type' => $row['comment_actor_type'],
			'actor_id' => $row['comment_actor_id'],
			'object_type' => $row['comment_object_type'],
			'object_id' => $row['comment_object_id'],
			// Reference id column might not be there, so we need to fallback to null
			'reference_id' => $row['comment_reference_id'] ?? null,
			'creation_timestamp' => $row['comment_creation_timestamp'],
			'latest_child_timestamp' => $row['comment_latest_child_timestamp'],
			'reactions' => $row['comment_reactions'],
			'expire_date' => $row['comment_expire_date'],
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
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->isNotNull('r.assigned_hpb'));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$room = $this->createRoomObject($row);
			if (!$this->participantService->hasActiveSessions($room)) {
				Server::get(RoomService::class)->setAssignedSignalingServer($room, null);
				$cache->remove($room->getToken());
			}
		}
		$result->closeCursor();
	}

	/**
	 * @param string $searchToken
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Room[]
	 */
	public function searchRoomsByToken(string $searchToken = '', int $limit = null, int $offset = null): array {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->setMaxResults(1);

		if ($searchToken !== '') {
			$query->where($query->expr()->iLike('r.token', $query->createNamedParameter(
				'%' . $this->db->escapeLikeParameter($searchToken) . '%'
			)));
		}

		$query->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('r.token', 'ASC');
		$result = $query->executeQuery();

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
	 * @param string $userId
	 * @param array $sessionIds A list of talk sessions to consider for loading (otherwise no session is loaded)
	 * @param bool $includeLastMessage
	 * @return Room[]
	 */
	public function getRoomsForUser(string $userId, array $sessionIds = [], bool $includeLastMessage = false): array {
		return $this->getRoomsForActor(Attendee::ACTOR_USERS, $userId, $sessionIds, $includeLastMessage);
	}

	/**
	 * @param string $actorType
	 * @param string $actorId
	 * @param array $sessionIds A list of talk sessions to consider for loading (otherwise no session is loaded)
	 * @param bool $includeLastMessage
	 * @return Room[]
	 */
	public function getRoomsForActor(string $actorType, string $actorId, array $sessionIds = [], bool $includeLastMessage = false): array {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$helper->selectAttendeesTable($query);
		$query->from('talk_rooms', 'r')
			->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($actorId)),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter($actorType)),
				$query->expr()->eq('a.room_id', 'r.id')
			))
			->where($query->expr()->isNotNull('a.id'));

		if ($sessionIds !== []) {
			$helper->selectSessionsTable($query);
			$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
				$query->expr()->eq('a.id', 's.attendee_id'),
				$query->expr()->in('s.session_id', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY))
			));
		}

		if ($includeLastMessage) {
			$this->loadLastMessageInfo($query);
		}

		$result = $query->executeQuery();
		$rooms = [];
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$room = $this->createRoomObject($row);
			if ($actorType === Attendee::ACTOR_USERS && isset($row['actor_id'])) {
				$room->setParticipant($row['actor_id'], $this->createParticipantObject($room, $row));
			}
			$rooms[] = $room;
		}
		$result->closeCursor();

		return $rooms;
	}

	/**
	 * @param string $userId
	 * @return Room[]
	 */
	public function getLeftOneToOneRoomsForUser(string $userId): array {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.type', $query->createNamedParameter(Room::TYPE_ONE_TO_ONE)))
			->andWhere($query->expr()->like('r.name', $query->createNamedParameter('%' . $this->db->escapeLikeParameter(json_encode($userId)) . '%')));

		$result = $query->executeQuery();
		$rooms = [];
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$room = $this->createRoomObject($row);
			$rooms[] = $room;
		}
		$result->closeCursor();

		return $rooms;
	}

	public function removeUserFromAllRooms(IUser $user): void {
		$rooms = $this->getRoomsForUser($user->getUID());
		foreach ($rooms as $room) {
			if ($this->participantService->getNumberOfUsers($room) === 1) {
				Server::get(RoomService::class)->deleteRoom($room);
			} else {
				$this->participantService->removeUser($room, $user, Room::PARTICIPANT_REMOVED_ALL);
			}
		}

		$leftRooms = $this->getLeftOneToOneRoomsForUser($user->getUID());
		foreach ($leftRooms as $room) {
			// We are changing the room type and name so a potential follow-up
			// user with the same user-id can not reopen the one-to-one conversation.
			/** @var RoomService $roomService */
			$roomService = Server::get(RoomService::class);
			$roomService->setType($room, Room::TYPE_ONE_TO_ONE_FORMER, true);
			$roomService->setName($room, $user->getDisplayName(), '');
			$roomService->setReadOnly($room, Room::READ_ONLY);
		}
	}

	/**
	 * @param string $userId
	 * @return string[]
	 */
	public function getRoomTokensForUser(string $userId): array {
		$query = $this->db->getQueryBuilder();
		$query->select('r.token')
			->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_rooms', 'r', $query->expr()->eq('a.room_id', 'r.id'))
			->where($query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)));

		$result = $query->executeQuery();
		$roomTokens = [];
		while ($row = $result->fetch()) {
			if ($row['token'] === null) {
				// FIXME Temporary solution for the Talk6 release
				continue;
			}

			$roomTokens[] = $row['token'];
		}
		$result->closeCursor();

		return $roomTokens;
	}

	/**
	 * Returns rooms that are listable where the current user is not a participant.
	 *
	 * @param string $userId user id
	 * @param string $term search term
	 * @return Room[]
	 */
	public function getListedRoomsForUser(string $userId, string $term = ''): array {
		$allowedRoomTypes = [Room::TYPE_GROUP, Room::TYPE_PUBLIC];
		$allowedListedTypes = [Room::LISTABLE_ALL];
		if (!$this->isGuestUser($userId)) {
			$allowedListedTypes[] = Room::LISTABLE_USERS;
		}
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)),
				$query->expr()->eq('a.room_id', 'r.id')
			))
			->where($query->expr()->isNull('a.id'))
			->andWhere($query->expr()->in('r.type', $query->createNamedParameter($allowedRoomTypes, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($query->expr()->in('r.listable', $query->createNamedParameter($allowedListedTypes, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('r.id', 'ASC');

		if ($term !== '') {
			$query->andWhere(
				$query->expr()->iLike('name', $query->createNamedParameter(
					'%' . $this->db->escapeLikeParameter($term). '%'
				))
			);
		}

		$result = $query->executeQuery();
		$rooms = [];
		while ($row = $result->fetch()) {
			$room = $this->createRoomObject($row);
			$rooms[] = $room;
		}
		$result->closeCursor();

		return $rooms;
	}

	/**
	 * Does *not* return public rooms for participants that have not been invited
	 *
	 * @param int $roomId
	 * @param string|null $userId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForUser(int $roomId, ?string $userId): Room {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		if ($userId !== null) {
			// Non guest user
			$helper->selectAttendeesTable($query);
			$query->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)),
				$query->expr()->eq('a.room_id', 'r.id')
			))
				->andWhere($query->expr()->isNotNull('a.id'));
		}

		$result = $query->executeQuery();
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
		if ($userId !== null && isset($row['actor_id'])) {
			$room->setParticipant($row['actor_id'], $this->createParticipantObject($room, $row));
		}

		if ($userId === null && $room->getType() !== Room::TYPE_PUBLIC) {
			throw new RoomNotFoundException();
		}

		return $room;
	}

	/**
	 * Returns room object for a user by token.
	 *
	 * Also returns:
	 * - public rooms for participants that have not been invited
	 * - listable rooms for participants that have not been invited
	 *
	 * This is useful so they can join.
	 *
	 * @param string $token
	 * @param string|null $userId
	 * @param string|null $sessionId
	 * @param bool $includeLastMessage
	 * @param bool $isSIPBridgeRequest
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForUserByToken(string $token, ?string $userId, ?string $sessionId = null, bool $includeLastMessage = false, bool $isSIPBridgeRequest = false): Room {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.token', $query->createNamedParameter($token)))
			->setMaxResults(1);

		if ($userId !== null) {
			// Non guest user
			$helper->selectAttendeesTable($query);
			$query->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)),
				$query->expr()->eq('a.room_id', 'r.id')
			));
			if ($sessionId !== null) {
				$helper->selectSessionsTable($query);
				$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
					$query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)),
					$query->expr()->eq('a.id', 's.attendee_id')
				));
			}
		}

		if ($includeLastMessage) {
			$this->loadLastMessageInfo($query);
		}

		$result = $query->executeQuery();
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
		if ($userId !== null && isset($row['actor_id'])) {
			$room->setParticipant($row['actor_id'], $this->createParticipantObject($room, $row));
		}

		if ($isSIPBridgeRequest || $room->getType() === Room::TYPE_PUBLIC) {
			return $room;
		}

		if ($userId !== null) {
			// user already joined that room before
			if ($row['actor_id'] === $userId) {
				return $room;
			}

			// never joined before but found in listing
			$listable = (int)$row['listable'];
			if ($this->isRoomListableByUser($room, $userId)) {
				return $room;
			}
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
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
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
	 * @param string $actorType
	 * @param string $actorId
	 * @param string|null $sessionId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByActor(string $token, string $actorType, string $actorId, ?string $sessionId = null, ?string $serverUrl = null): Room {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$helper->selectAttendeesTable($query);
		$query->from('talk_rooms', 'r')
			->leftJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.actor_type', $query->createNamedParameter($actorType)),
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($actorId)),
				$query->expr()->eq('a.room_id', 'r.id')
			));


		if ($serverUrl === null) {
			$query->where($query->expr()->eq('r.token', $query->createNamedParameter($token)));
		} else {
			$query
				->where($query->expr()->eq('r.remote_token', $query->createNamedParameter($token)))
				->andWhere($query->expr()->eq('r.remote_server', $query->createNamedParameter($serverUrl)));
		}

		if ($sessionId !== null) {
			$helper->selectSessionsTable($query);
			$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
				$query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)),
				$query->expr()->eq('a.id', 's.attendee_id')
			));
		}

		$result = $query->executeQuery();
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
		if ($actorType === Attendee::ACTOR_USERS && isset($row['actor_id'])) {
			$room->setParticipant($row['actor_id'], $this->createParticipantObject($room, $row));
		}

		return $room;
	}

	/**
	 * @param string $token
	 * @param string|null $preloadUserId Load this participant's information if possible
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByToken(string $token, ?string $preloadUserId = null, ?string $serverUrl = null): Room {
		$preloadUserId = $preloadUserId === '' ? null : $preloadUserId;
		if ($preloadUserId !== null) {
			return $this->getRoomByActor($token, Attendee::ACTOR_USERS, $preloadUserId, null, $serverUrl);
		}

		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r');

		if ($serverUrl === null) {
			$query->where($query->expr()->eq('r.token', $query->createNamedParameter($token)));
		} else {
			$query
				->where($query->expr()->eq('r.remote_token', $query->createNamedParameter($token)))
				->andWhere($query->expr()->eq('r.remote_server', $query->createNamedParameter($serverUrl)));
		}


		$result = $query->executeQuery();
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
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByObject(string $objectType, string $objectId): Room {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('r.object_id', $query->createNamedParameter($objectId)))
			->orderBy('r.id', 'ASC');

		$result = $query->executeQuery();
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
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room[]
	 */
	public function getMultipleRoomsByObject(string $objectType, string $objectId, bool $orderById = false): array {
		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.object_type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('r.object_id', $query->createNamedParameter($objectId)));

		if ($orderById) {
			$query->orderBy('id', 'ASC');
		}

		$result = $query->executeQuery();
		$rooms = [];
		while ($row = $result->fetch()) {
			$room = $this->createRoomObject($row);
			$rooms[] = $room;
		}
		$result->closeCursor();

		return $rooms;
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
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('a.id', 's.attendee_id'))
			->leftJoin('a', 'talk_rooms', 'r', $query->expr()->eq('a.room_id', 'r.id'))
			->where($query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)))
			->setMaxResults(1);

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false || !$row['r_id']) {
			throw new RoomNotFoundException();
		}

		if ($userId !== null) {
			if ($row['actor_type'] !== Attendee::ACTOR_USERS || $userId !== $row['actor_id']) {
				throw new RoomNotFoundException();
			}
		} else {
			if ($row['actor_type'] !== Attendee::ACTOR_GUESTS) {
				throw new RoomNotFoundException();
			}
		}

		if ($row['token'] === null) {
			// FIXME Temporary solution for the Talk6 release
			throw new RoomNotFoundException();
		}

		$room = $this->createRoomObject($row);
		$participant = $this->createParticipantObject($room, $row);
		$room->setParticipant($row['actor_id'], $participant);

		if ($room->getType() === Room::TYPE_PUBLIC || !in_array($participant->getAttendee()->getParticipantType(), [Participant::GUEST, Participant::GUEST_MODERATOR, Participant::USER_SELF_JOINED], true)) {
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
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.type', $query->createNamedParameter(Room::TYPE_ONE_TO_ONE, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('r.name', $query->createNamedParameter($name)));

		$result = $query->executeQuery();
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
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$query->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.type', $query->createNamedParameter(Room::TYPE_CHANGELOG, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('r.name', $query->createNamedParameter($userId)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			$room = $this->createRoom(Room::TYPE_CHANGELOG, $userId);
			Server::get(RoomService::class)->setReadOnly($room, Room::READ_ONLY);

			$user = $this->userManager->get($userId);
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'displayName' => $user ? $user->getDisplayName() : $userId,
			]]);
			return $room;
		}

		$room = $this->createRoomObject($row);

		try {
			$this->participantService->getParticipant($room, $userId, false);
		} catch (ParticipantNotFoundException $e) {
			$user = $this->userManager->get($userId);
			$this->participantService->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'displayName' => $user ? $user->getDisplayName() : $userId,
			]]);
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

		$insert = $this->db->getQueryBuilder();
		$insert->insert('talk_rooms')
			->values(
				[
					'name' => $insert->createNamedParameter($name),
					'type' => $insert->createNamedParameter($type, IQueryBuilder::PARAM_INT),
					'token' => $insert->createNamedParameter($token),
				]
			);

		if (!empty($objectType) && !empty($objectId)) {
			$insert->setValue('object_type', $insert->createNamedParameter($objectType))
				->setValue('object_id', $insert->createNamedParameter($objectId));
		}

		$insert->executeStatement();
		$roomId = $insert->getLastInsertId();
		$room = $this->createRoomObjectFromData([
			'r_id' => $roomId,
			'name' => $name,
			'type' => $type,
			'token' => $token,
			'object_type' => $objectType,
			'object_id' => $objectId,
		]);

		$event = new RoomEvent($room);
		$this->dispatcher->dispatch(Room::EVENT_AFTER_ROOM_CREATE, $event);

		return $room;
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @return Room
	 * @throws DBException
	 */
	public function createRemoteRoom(int $type, string $name, string $remoteToken, string $remoteServer): Room {
		$token = $this->getNewToken();

		$qb = $this->db->getQueryBuilder();

		$qb->insert('talk_rooms')
			->values([
				'name' => $qb->createNamedParameter($name),
				'type' => $qb->createNamedParameter($type, IQueryBuilder::PARAM_INT),
				'token' => $qb->createNamedParameter($token),
				'remote_token' => $qb->createNamedParameter($remoteToken),
				'remote_server' => $qb->createNamedParameter($remoteServer),
			]);

		$qb->executeStatement();
		$roomId = $qb->getLastInsertId();

		return $this->createRoomObjectFromData([
			'r_id' => $roomId,
			'name' => $name,
			'type' => $type,
			'token' => $token,
			'remote_token' => $remoteToken,
			'remote_server' => $remoteServer,
		]);
	}

	public function resolveRoomDisplayName(Room $room, string $userId, bool $forceName = false): string {
		if ($room->getObjectType() === 'share:password') {
			return $this->l->t('Password request: %s', [$room->getName()]);
		}

		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return $this->l->t('Talk updates ✅');
		}

		if ($forceName) {
			return $room->getName();
		}

		if ($userId === '' && $room->getType() !== Room::TYPE_PUBLIC) {
			return $this->l->t('Private conversation');
		}

		if ($room->getType() !== Room::TYPE_ONE_TO_ONE && $room->getName() === '') {
			/** @var RoomService $roomService */
			$roomService = Server::get(RoomService::class);
			$roomService->setName($room, $this->getRoomNameByParticipants($room), '');
		}

		// Set the room name to the other participant for one-to-one rooms
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			if ($userId === '') {
				return $this->l->t('Private conversation');
			}

			$users = json_decode($room->getName(), true);
			$otherParticipant = '';
			$userIsParticipant = false;

			foreach ($users as $participantId) {
				if ($participantId !== $userId) {
					$otherParticipant = $this->userManager->getDisplayName($participantId) ?? $participantId;
				} else {
					$userIsParticipant = true;
				}
			}

			if (!$userIsParticipant) {
				// Do not leak the name of rooms the user is not a part of
				return $this->l->t('Private conversation');
			}

			if ($otherParticipant === '' && $room->getName() !== '') {
				$userDisplayName = $this->userManager->getDisplayName($room->getName());
				$otherParticipant = $userDisplayName ?? $this->l->t('Deleted user (%s)', $room->getName());
			}

			return $otherParticipant;
		}

		if (!$this->isRoomListableByUser($room, $userId)) {
			try {
				if ($userId === '') {
					$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
					$this->participantService->getParticipantBySession($room, $sessionId);
				} else {
					$this->participantService->getParticipant($room, $userId, false);
				}
			} catch (ParticipantNotFoundException $e) {
				// Do not leak the name of rooms the user is not a part of
				return $this->l->t('Private conversation');
			}
		}

		return $room->getName();
	}

	/**
	 * Returns whether the given room is listable for the given user.
	 *
	 * @param Room $room room
	 * @param string|null $userId user id
	 */
	public function isRoomListableByUser(Room $room, ?string $userId): bool {
		if ($userId === null) {
			// not listable for guest users with no account
			return false;
		}

		if ($room->getListable() === Room::LISTABLE_ALL) {
			return true;
		}

		if ($room->getListable() === Room::LISTABLE_USERS && !$this->isGuestUser($userId)) {
			return true;
		}

		return false;
	}

	protected function getRoomNameByParticipants(Room $room): string {
		$users = $this->participantService->getParticipantUserIds($room);
		$displayNames = [];

		foreach ($users as $participantId) {
			$displayNames[] = $this->userManager->getDisplayName($participantId) ?? $participantId;
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
		$entropy = (int) $this->config->getAppValue('spreed', 'token_entropy', '8');
		$entropy = max(8, $entropy); // For update cases
		$digitsOnly = $this->talkConfig->isSIPConfigured();
		if ($digitsOnly) {
			// Increase default token length as we only use numbers
			$entropy = max(10, $entropy);
		}

		$query = $this->db->getQueryBuilder();
		$query->select('r.id')
			->from('talk_rooms', 'r')
			->where($query->expr()->eq('r.token', $query->createParameter('token')));

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
		$this->config->setAppValue('spreed', 'token_entropy', (string) $entropy);
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
		$result = $query->executeQuery();
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

	/**
	 * Returns whether the given user id is a guest user from
	 * the guest app
	 *
	 * @param string $userId user id to check
	 * @return bool true if the user is a guest, false otherwise
	 */
	public function isGuestUser(string $userId): bool {
		if (!$this->appManager->isEnabledForUser('guests')) {
			return false;
		}
		// TODO: retrieve guest group name from app once exposed
		return $this->groupManager->isInGroup($userId, 'guest_app');
	}

	protected function loadLastMessageInfo(IQueryBuilder $query): void {
		$query->leftJoin('r', 'comments', 'c', $query->expr()->eq('r.last_message', 'c.id'));
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
		$query->selectAlias('c.reactions', 'comment_reactions');
		$query->selectAlias('c.expire_date', 'comment_expire_date');
	}
}
