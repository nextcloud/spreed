<?php
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

namespace OCA\Spreed;

use OCA\Spreed\Exceptions\InvalidPasswordException;
use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Room {
	const ONE_TO_ONE_CALL = 1;
	const GROUP_CALL = 2;
	const PUBLIC_CALL = 3;

	/** @var IDBConnection */
	private $db;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var IHasher */
	private $hasher;

	/** @var int */
	private $id;
	/** @var int */
	private $type;
	/** @var string */
	private $token;
	/** @var string */
	private $name;
	/** @var string */
	private $password;

	/** @var string */
	protected $currentUser;
	/** @var Participant */
	protected $participant;

	/**
	 * Room constructor.
	 *
	 * @param IDBConnection $db
	 * @param ISecureRandom $secureRandom
	 * @param EventDispatcherInterface $dispatcher
	 * @param IHasher $hasher
	 * @param int $id
	 * @param int $type
	 * @param string $token
	 * @param string $name
	 * @param string $password
	 */
	public function __construct(IDBConnection $db, ISecureRandom $secureRandom, EventDispatcherInterface $dispatcher, IHasher $hasher, $id, $type, $token, $name, $password) {
		$this->db = $db;
		$this->secureRandom = $secureRandom;
		$this->dispatcher = $dispatcher;
		$this->hasher = $hasher;
		$this->id = $id;
		$this->type = $type;
		$this->token = $token;
		$this->name = $name;
		$this->password = $password;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getToken() {
		return $this->token;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return bool
	 */
	public function hasPassword() {
		return $this->password !== '';
	}

	/**
	 * @param string $userId
	 * @param Participant $participant
	 */
	public function setParticipant($userId, Participant $participant) {
		$this->currentUser = $userId;
		$this->participant = $participant;
	}

	/**
	 * @param string $userId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipant($userId) {
		if (!is_string($userId) || $userId === '') {
			throw new ParticipantNotFoundException('Not a user');
		}

		if ($this->currentUser === $userId && $this->participant instanceof Participant) {
			return $this->participant;
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId())));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new ParticipantNotFoundException('User is not a participant');
		}

		if ($this->currentUser === $userId) {
			$this->participant = new Participant($this->db, $this, $row['userId'], (int) $row['participantType'], (int) $row['lastPing'], $row['sessionId']);
			return $this->participant;
		}

		return new Participant($this->db, $this, $row['userId'], (int) $row['participantType'], (int) $row['lastPing'], $row['sessionId']);
	}

	/**
	 * @param string $sessionId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipantBySession($sessionId) {
		if (!is_string($sessionId) || $sessionId === '') {
			throw new ParticipantNotFoundException('Not a user');
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId())));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new ParticipantNotFoundException('User is not a participant');
		}

		return new Participant($this->db, $this, $row['userId'], (int) $row['participantType'], (int) $row['lastPing'], $row['sessionId']);
	}

	public function deleteRoom() {
		$query = $this->db->getQueryBuilder();

		// Delete all participants
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		// Delete room
		$query->delete('spreedme_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	/**
	 * @param string $newName Currently it is only allowed to rename: Room::GROUP_CALL, Room::PUBLIC_CALL
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setName($newName) {
		if ($newName === $this->getName()) {
			return true;
		}

		if ($this->getType() === self::ONE_TO_ONE_CALL) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_rooms')
			->set('name', $query->createNamedParameter($newName))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
		$this->name = $newName;

		return true;
	}

	/**
	 * @param string $password Currently it is only allowed to have a password for Room::PUBLIC_CALL
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setPassword($password) {
		if ($this->getType() !== self::PUBLIC_CALL) {
			return false;
		}

		$hash = $this->hasher->hash($password);

		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_rooms')
			->set('password', $query->createNamedParameter($hash))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
		$this->password = $hash;

		return true;
	}

	/**
	 * @param int $newType Currently it is only allowed to change to: Room::GROUP_CALL, Room::PUBLIC_CALL
	 * @return bool True when the change was valid, false otherwise
	 */
	public function changeType($newType) {
		if ($newType === $this->getType()) {
			return true;
		}

		if (!in_array($newType, [Room::GROUP_CALL, Room::PUBLIC_CALL], true)) {
			return false;
		}

		$oldType = $this->getType();

		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_rooms')
			->set('type', $query->createNamedParameter($newType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->type = (int) $newType;

		if ($oldType === Room::PUBLIC_CALL) {
			// Kick all guests and users that were not invited
			$query = $this->db->getQueryBuilder();
			$query->delete('spreedme_room_participants')
				->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->in('participantType', $query->createNamedParameter([Participant::GUEST, Participant::USER_SELF_JOINED], IQueryBuilder::PARAM_INT_ARRAY)));
			$query->execute();
		}

		return true;
	}

	/**
	 * @param IUser $user
	 */
	public function addUser(IUser $user) {
		$this->addParticipant($user->getUID(), Participant::USER);
	}

	/**
	 * @param string $participant
	 * @param int $participantType
	 * @param string $sessionId
	 */
	public function addParticipant($participant, $participantType, $sessionId = '0') {
		$query = $this->db->getQueryBuilder();
		$query->insert('spreedme_room_participants')
			->values(
				[
					'userId' => $query->createNamedParameter($participant),
					'roomId' => $query->createNamedParameter($this->getId()),
					'lastPing' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
					'sessionId' => $query->createNamedParameter($sessionId),
					'participantType' => $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT),
				]
			);
		$query->execute();
	}

	/**
	 * @param string $participant
	 * @param int $participantType
	 */
	public function setParticipantType($participant, $participantType) {
		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('participantType', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($participant)));
		$query->execute();
	}

	/**
	 * @param IUser $user
	 */
	public function removeUser(IUser $user) {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($user->getUID())));
		$query->execute();
	}

	/**
	 * @param Participant $participant
	 */
	public function removeParticipantBySession(Participant $participant) {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('sessionId', $query->createNamedParameter($participant->getSessionId())));
		$query->execute();
	}

	/**
	 * @param string $userId
	 * @param string $password
	 * @return string
	 * @throws InvalidPasswordException
	 */
	public function enterRoomAsUser($userId, $password) {
		$this->dispatcher->dispatch(self::class . '::preUserEnterRoom', new GenericEvent($this));

		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('sessionId', $query->createParameter('sessionId'))
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($userId)));

		$sessionId = $this->secureRandom->generate(255);
		$query->setParameter('sessionId', $sessionId);
		$result = $query->execute();

		if ($result === 0) {
			if ($this->hasPassword() && !$this->hasher->verify($password, $this->password)) {
				throw new InvalidPasswordException();
			}

			// User joining a public room, without being invited
			$this->addParticipant($userId, Participant::USER_SELF_JOINED, $sessionId);
		}

		while (!$this->isSessionUnique($sessionId)) {
			$sessionId = $this->secureRandom->generate(255);
			$query->setParameter('sessionId', $sessionId);
			$query->execute();
		}

		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('sessionId', $query->createNamedParameter('0'))
			->where($query->expr()->neq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($userId)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postUserEnterRoom', new GenericEvent($this));

		return $sessionId;
	}

	/**
	 * @param string $password
	 * @return string
	 * @throws InvalidPasswordException
	 */
	public function enterRoomAsGuest($password) {
		$this->dispatcher->dispatch(self::class . '::preGuestEnterRoom', new GenericEvent($this));

		if ($this->hasPassword() && !$this->hasher->verify($password, $this->password)) {
			throw new InvalidPasswordException();
		}

		$sessionId = $this->secureRandom->generate(255);
		while (!$this->db->insertIfNotExist('*PREFIX*spreedme_room_participants', [
			'userId' => '',
			'roomId' => $this->getId(),
			'lastPing' => 0,
			'sessionId' => $sessionId,
			'participantType' => Participant::GUEST,
		], ['sessionId'])) {
			$sessionId = $this->secureRandom->generate(255);
		}

		$this->dispatcher->dispatch(self::class . '::postGuestEnterRoom', new GenericEvent($this));

		return $sessionId;
	}

	/**
	 * @param string $sessionId
	 * @return bool
	 */
	protected function isSessionUnique($sessionId) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_sessions')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)));
		$result = $query->execute();
		$numSessions = (int) $result->fetchColumn();
		$result->closeCursor();

		return $numSessions === 1;
	}

	public function cleanGuestParticipants() {
		$query = $this->db->getQueryBuilder();
		$query->delete('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->emptyString('userId'))
			->andWhere($query->expr()->lte('lastPing', $query->createNamedParameter(time() - 30, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return array[] Array of users with [users => [userId => [lastPing, sessionId]], guests => [[lastPing, sessionId]]]
	 */
	public function getParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('lastPing', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();

		$users = $guests = [];
		while ($row = $result->fetch()) {
			if ($row['userId'] !== '' && $row['userId'] !== null) {
				$users[$row['userId']] = [
					'lastPing' => (int) $row['lastPing'],
					'sessionId' => $row['sessionId'],
					'participantType' => (int) $row['participantType'],
				];
			} else {
				$guests[] = [
					'lastPing' => (int) $row['lastPing'],
					'sessionId' => $row['sessionId'],
				];
			}
		}
		$result->closeCursor();

		return [
			'users' => $users,
			'guests' => $guests,
		];
	}

	/**
	 * @return string[]
	 */
	public function getActiveSessions() {
		$query = $this->db->getQueryBuilder();
		$query->select('sessionId')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('sessionId', $query->createNamedParameter('0')));
		$result = $query->execute();

		$sessions = [];
		while ($row = $result->fetch()) {
			$sessions[] = $row['sessionId'];
		}
		$result->closeCursor();

		return $sessions;
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return int
	 */
	public function getNumberOfParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_participants')
			->from('spreedme_room_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('lastPing', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return isset($row['num_participants']) ? (int) $row['num_participants'] : 0;
	}

	/**
	 * @param string $participant
	 * @param string $sessionId
	 * @param int $timestamp
	 */
	public function ping($participant, $sessionId, $timestamp) {
		$query = $this->db->getQueryBuilder();
		$query->update('spreedme_room_participants')
			->set('lastPing', $query->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('userId', $query->createNamedParameter((string) $participant)))
			->andWhere($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		$query->execute();
	}
}
