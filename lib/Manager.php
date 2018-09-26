<?php
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

namespace OCA\Spreed;


use OCA\Spreed\Chat\CommentsManager;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Manager {

	/** @var IDBConnection */
	private $db;
	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var CommentsManager */
	private $commentsManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var IHasher */
	private $hasher;

	/**
	 * Manager constructor.
	 *
	 * @param IDBConnection $db
	 * @param IConfig $config
	 * @param ISecureRandom $secureRandom
	 * @param CommentsManager $commentsManager
	 * @param EventDispatcherInterface $dispatcher
	 * @param IHasher $hasher
	 */
	public function __construct(IDBConnection $db, IConfig $config, ISecureRandom $secureRandom, CommentsManager $commentsManager, EventDispatcherInterface $dispatcher, IHasher $hasher) {
		$this->db = $db;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->commentsManager = $commentsManager;
		$this->dispatcher = $dispatcher;
		$this->hasher = $hasher;
	}

	public function forAllRooms(callable $callback) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms');

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$room = $this->createRoomObject($row);
			$callback($room);
		}
		$result->closeCursor();
	}

	/**
	 * @param array $row
	 * @return Room
	 */
	public function createRoomObject(array $row) {
		$activeSince = null;
		if (!empty($row['active_since'])) {
			$activeSince = new \DateTime($row['active_since']);
		}

		$lastActivity = null;
		if (!empty($row['last_activity'])) {
			$lastActivity = new \DateTime($row['last_activity']);
		}

		$lastMessage = null;
		if (!empty($row['comment_id'])) {
			$lastMessage = $this->commentsManager->getCommentFromData(array_merge($row, [
				'id' => $row['comment_id'],
				'object_type' => $row['comment_object_type'],
				'object_id' => $row['comment_object_id'],
				'parent_id' => '',
				'topmost_parent_id' => '',
				'latest_child_timestamp' => null,
				'children_count' => 0,
			]));
		}

		return new Room($this, $this->db, $this->secureRandom, $this->dispatcher, $this->hasher, (int) $row['id'], (int) $row['type'], $row['token'], $row['name'], $row['password'], (int) $row['active_guests'], $activeSince, $lastActivity, $lastMessage, (string) $row['object_type'], (string) $row['object_id']);
	}

	/**
	 * @param Room $room
	 * @param array $row
	 * @return Participant
	 */
	public function createParticipantObject(Room $room, array $row) {

		return new Participant($this->db, $room, (string) $row['user_id'], (int) $row['participant_type'], (int) $row['last_ping'], (string) $row['session_id'], (int) $row['in_call'], (int) $row['notification_level'], (bool) $row['favorite'], (int) $row['last_read_message'], (int) $row['last_mention_message']);
	}

	/**
	 * @param string $participant
	 * @param bool $includeLastMessage
	 * @return Room[]
	 */
	public function getRoomsForParticipant($participant, $includeLastMessage = false) {
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
	public function getRoomForParticipant($roomId, $participant) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms', 'r')
			->where($query->expr()->eq('id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

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
	public function getRoomForParticipantByToken($token, $participant, $includeLastMessage = false) {
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
	public function getRoomById($roomId) {
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

		return $this->createRoomObject($row);
	}

	/**
	 * @param string $token
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomByToken($token) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return $this->createRoomObject($row);
	}

	/**
	 * @param string|null $userId
	 * @param string $sessionId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	public function getRoomForSession($userId, $sessionId) {
		if ((string) $sessionId === '' || $sessionId === '0') {
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
	public function getOne2OneRoom($participant1, $participant2) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_rooms', 'r1')
			->leftJoin('r1', 'talk_participants', 'p1', $query->expr()->andX(
				$query->expr()->eq('p1.user_id', $query->createNamedParameter($participant1)),
				$query->expr()->eq('p1.room_id', 'r1.id')
			))
			->leftJoin('r1', 'talk_participants', 'p2', $query->expr()->andX(
				$query->expr()->eq('p2.user_id', $query->createNamedParameter($participant2)),
				$query->expr()->eq('p2.room_id', 'r1.id')
			))
			->where($query->expr()->eq('r1.type', $query->createNamedParameter(Room::ONE_TO_ONE_CALL, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('p1.user_id'))
			->andWhere($query->expr()->isNotNull('p2.user_id'));

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new RoomNotFoundException();
		}

		return $this->createRoomObject($row);
	}

	/**
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 */
	public function createOne2OneRoom($objectType = '', $objectId = '') {
		return $this->createRoom(Room::ONE_TO_ONE_CALL, '', $objectType, $objectId);
	}

	/**
	 * @param string $name
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 */
	public function createGroupRoom($name = '', $objectType = '', $objectId = '') {
		return $this->createRoom(Room::GROUP_CALL, $name, $objectType, $objectId);
	}

	/**
	 * @param string $name
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 */
	public function createPublicRoom($name = '', $objectType = '', $objectId = '') {
		return $this->createRoom(Room::PUBLIC_CALL, $name, $objectType, $objectId);
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @param string $objectType
	 * @param string $objectId
	 * @return Room
	 */
	private function createRoom($type, $name = '', $objectType = '', $objectId = '') {
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

		$this->dispatcher->dispatch(Room::class . '::createRoom', new GenericEvent($room));

		return $room;
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getCurrentSessionId($userId) {
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
	public function getSessionIdsForUser($userId) {
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

	/**
	 * @return string
	 */
	protected function getNewToken() {
		$chars = str_replace(['l', '0', '1'], '', ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
		$entropy = (int) $this->config->getAppValue('spreed', 'token_entropy', 8);
		$entropy = min(8, $entropy); // For update cases

		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createParameter('token')));

		$i = 0;
		while ($i < 1000) {
			try {
				$token = $this->generateNewToken($query, $entropy, $chars);
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
		return $this->generateNewToken($query, $entropy, $chars);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param int $entropy
	 * @param string $chars
	 * @return string
	 * @throws \OutOfBoundsException
	 */
	protected function generateNewToken(IQueryBuilder $query, $entropy, $chars) {
		$event = new GenericEvent(null, [
			'entropy' => $entropy,
			'chars' => $chars,
		]);
		$this->dispatcher->dispatch(self::class . '::generateNewToken', $event);
		try {
			$token = $event->getArgument('token');
			if (empty($token)) {
				// Will generate default token below.
				throw new \InvalidArgumentException('token may not be empty');
			}
		} catch (\InvalidArgumentException $e) {
			$token = $this->secureRandom->generate($entropy, $chars);
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

	protected function loadLastMessageInfo(IQueryBuilder $query) {
		$query->leftJoin('r','comments', 'c', $query->expr()->eq('r.last_message', 'c.id'));
		$query->selectAlias('c.id', 'comment_id');
		$query->addSelect('c.actor_id', 'c.actor_type', 'c.message', 'c.creation_timestamp', 'c.verb');
		$query->selectAlias('c.object_type', 'comment_object_type');
		$query->selectAlias('c.object_id', 'comment_object_id');
	}
}
