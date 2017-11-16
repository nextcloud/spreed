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
	const UNKNOWN_CALL = -1;
	const ONE_TO_ONE_CALL = 1;
	const GROUP_CALL = 2;
	const PUBLIC_CALL = 3;

	/** @var Manager */
	private $manager;
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
	/** @var int */
	private $activeGuests;
	/** @var \DateTime|null */
	private $activeSince;

	/** @var string */
	protected $currentUser;
	/** @var Participant */
	protected $participant;

	/**
	 * Room constructor.
	 *
	 * @param Manager $manager
	 * @param IDBConnection $db
	 * @param ISecureRandom $secureRandom
	 * @param EventDispatcherInterface $dispatcher
	 * @param IHasher $hasher
	 * @param int $id
	 * @param int $type
	 * @param string $token
	 * @param string $name
	 * @param string $password
	 * @param int $activeGuests
	 * @param \DateTime|null $activeSince
	 */
	public function __construct(Manager $manager, IDBConnection $db, ISecureRandom $secureRandom, EventDispatcherInterface $dispatcher, IHasher $hasher, $id, $type, $token, $name, $password, $activeGuests, \DateTime $activeSince = null) {
		$this->manager = $manager;
		$this->db = $db;
		$this->secureRandom = $secureRandom;
		$this->dispatcher = $dispatcher;
		$this->hasher = $hasher;
		$this->id = $id;
		$this->type = $type;
		$this->token = $token;
		$this->name = $name;
		$this->password = $password;
		$this->activeGuests = $activeGuests;
		$this->activeSince = $activeSince;
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
	 * @return int
	 */
	public function getActiveGuests() {
		return $this->activeGuests;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getActiveSince() {
		return $this->activeSince;
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
			->from('talk_participants')
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId())));
		$result = $query->execute();
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

	/**
	 * @param string $sessionId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipantBySession($sessionId) {
		if (!is_string($sessionId) || $sessionId === '' || $sessionId === '0') {
			throw new ParticipantNotFoundException('Not a user');
		}

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_participants')
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId())));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new ParticipantNotFoundException('User is not a participant');
		}

		return $this->manager->createParticipantObject($this, $row);
	}

	public function deleteRoom() {
		$participants = $this->getParticipants();
		$this->dispatcher->dispatch(self::class . '::preDeleteRoom', new GenericEvent($this, [
			'participants' => $participants,
		]));
		$query = $this->db->getQueryBuilder();

		// Delete all participants
		$query->delete('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		// Delete room
		$query->delete('talk_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postDeleteRoom', new GenericEvent($this, [
			'participants' => $participants,
		]));
	}

	/**
	 * @param string $newName Currently it is only allowed to rename: self::GROUP_CALL, self::PUBLIC_CALL
	 * @return bool True when the change was valid, false otherwise
	 */
	public function setName($newName) {
		$oldName = $this->getName();
		if ($newName === $oldName) {
			return true;
		}

		if ($this->getType() === self::ONE_TO_ONE_CALL) {
			return false;
		}

		$oldName = $this->getName();

		$this->dispatcher->dispatch(self::class . '::preSetName', new GenericEvent($this, [
			'newName' => $newName,
			'oldName' => $oldName,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('name', $query->createNamedParameter($newName))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
		$this->name = $newName;

		$this->dispatcher->dispatch(self::class . '::postSetName', new GenericEvent($this, [
			'newName' => $newName,
			'oldName' => $oldName,
		]));

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

		$hash = $password !== '' ? $this->hasher->hash($password) : '';

		$this->dispatcher->dispatch(self::class . '::preSetPassword', new GenericEvent($this, [
			'password' => $password,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('password', $query->createNamedParameter($hash))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
		$this->password = $hash;

		$this->dispatcher->dispatch(self::class . '::postSetPassword', new GenericEvent($this, [
			'password' => $password,
		]));

		return true;
	}

	/**
	 * @param \DateTime $since
	 * @param bool $isGuest
	 * @return bool
	 */
	public function setActiveSince(\DateTime $since, $isGuest) {

		if ($isGuest && $this->getType() === self::PUBLIC_CALL) {
			$query = $this->db->getQueryBuilder();
			$query->update('talk_rooms')
				->set('activeGuests', $query->createFunction($query->getColumnName('activeGuests') . ' + 1'))
				->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
			$query->execute();

			$this->activeGuests++;
		}

		if ($this->activeSince instanceof \DateTime) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('activeSince', $query->createNamedParameter($since, 'datetime'))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNull('activeSince'));
		$query->execute();

		$this->activeSince = $since;

		return true;
	}

	/**
	 * @return bool
	 */
	public function resetActiveSince() {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('activeGuests', $query->createNamedParameter(0))
			->set('activeSince', $query->createNamedParameter(null, 'datetime'))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

	}

	/**
	 * @param int $newType Currently it is only allowed to change to: self::GROUP_CALL, self::PUBLIC_CALL
	 * @return bool True when the change was valid, false otherwise
	 */
	public function changeType($newType) {
		$newType = (int) $newType;
		if ($newType === $this->getType()) {
			return true;
		}

		if (!in_array($newType, [self::GROUP_CALL, self::PUBLIC_CALL], true)) {
			return false;
		}

		$oldType = $this->getType();

		$this->dispatcher->dispatch(self::class . '::preChangeType', new GenericEvent($this, [
			'newType' => $newType,
			'oldType' => $oldType,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('type', $query->createNamedParameter($newType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->type = $newType;

		if ($oldType === self::PUBLIC_CALL) {
			// Kick all guests and users that were not invited
			$query = $this->db->getQueryBuilder();
			$query->delete('talk_participants')
				->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->in('participantType', $query->createNamedParameter([Participant::GUEST, Participant::USER_SELF_JOINED], IQueryBuilder::PARAM_INT_ARRAY)));
			$query->execute();
		}

		$this->dispatcher->dispatch(self::class . '::postChangeType', new GenericEvent($this, [
			'newType' => $newType,
			'oldType' => $oldType,
		]));

		return true;
	}

	/**
	 * @param array[] ...$participants
	 */
	public function addUsers(array ...$participants) {
		$this->dispatcher->dispatch(self::class . '::preAddUsers', new GenericEvent($this, [
			'users' => $participants,
		]));

		$query = $this->db->getQueryBuilder();
		$query->insert('talk_participants')
			->values(
				[
					'userId' => $query->createParameter('userId'),
					'sessionId' => $query->createParameter('sessionId'),
					'participantType' => $query->createParameter('participantType'),
					'roomId' => $query->createNamedParameter($this->getId()),
					'lastPing' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				]
			);

		foreach ($participants as $participant) {
			$query->setParameter('userId', $participant['userId'])
				->setParameter('sessionId', isset($participant['sessionId']) ? $participant['sessionId'] : '0')
				->setParameter('participantType', isset($participant['participantType']) ? $participant['participantType'] : Participant::USER, IQueryBuilder::PARAM_INT);

			$query->execute();
		}

		$this->dispatcher->dispatch(self::class . '::postAddUsers', new GenericEvent($this, [
			'users' => $participants,
		]));
	}

	/**
	 * @param string $participant
	 * @param int $participantType
	 */
	public function setParticipantType($participant, $participantType) {
		$this->dispatcher->dispatch(self::class . '::preSetParticipantType', new GenericEvent($this, [
			'user' => $participant,
			'newType' => $participantType,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('participantType', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($participant)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postSetParticipantType', new GenericEvent($this, [
			'user' => $participant,
			'newType' => $participantType,
		]));
	}

	/**
	 * @param IUser $user
	 */
	public function removeUser(IUser $user) {
		$this->dispatcher->dispatch(self::class . '::preRemoveUser', new GenericEvent($this, [
			'user' => $user,
		]));

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($user->getUID())));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postRemoveUser', new GenericEvent($this, [
			'user' => $user,
		]));
	}

	/**
	 * @param Participant $participant
	 */
	public function removeParticipantBySession(Participant $participant) {
		$this->dispatcher->dispatch(self::class . '::preRemoveBySession', new GenericEvent($this, [
			'participant' => $participant,
		]));

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('sessionId', $query->createNamedParameter($participant->getSessionId())));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postRemoveBySession', new GenericEvent($this, [
			'participant' => $participant,
		]));
	}

	/**
	 * @param string $userId
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return string
	 * @throws InvalidPasswordException
	 */
	public function enterRoomAsUser($userId, $password, $passedPasswordProtection = false) {
		$this->dispatcher->dispatch(self::class . '::preUserEnterRoom', new GenericEvent($this));

		$this->disconnectUserFromAllRooms($userId);

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('sessionId', $query->createParameter('sessionId'))
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('userId', $query->createNamedParameter($userId)));

		$sessionId = $this->secureRandom->generate(255);
		$query->setParameter('sessionId', $sessionId);
		$result = $query->execute();

		if ($result === 0) {
			if (!$passedPasswordProtection && !$this->verifyPassword($password)) {
				throw new InvalidPasswordException();
			}

			// User joining a public room, without being invited
			$this->addUsers([
				'userId' => $userId,
				'participantType' => Participant::USER_SELF_JOINED,
				'sessionId' => $sessionId,
			]);
		}

		while (!$this->isSessionUnique($sessionId)) {
			$sessionId = $this->secureRandom->generate(255);
			$query->setParameter('sessionId', $sessionId);
			$query->execute();
		}

		$this->dispatcher->dispatch(self::class . '::postUserEnterRoom', new GenericEvent($this));

		return $sessionId;
	}

	/**
	 * @param string $userId
	 */
	public function disconnectUserFromAllRooms($userId) {
		$this->dispatcher->dispatch(self::class . '::preUserDisconnectRoom', new GenericEvent($this));

		// Reset sessions on all normal rooms
		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('sessionId', $query->createNamedParameter('0'))
			->set('inCall', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->neq('participantType', $query->createNamedParameter(Participant::USER_SELF_JOINED, IQueryBuilder::PARAM_INT)));
		$query->execute();

		// And kill session on all self joined rooms
		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('userId', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('participantType', $query->createNamedParameter(Participant::USER_SELF_JOINED, IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postUserDisconnectRoom', new GenericEvent($this));
	}

	/**
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return string
	 * @throws InvalidPasswordException
	 */
	public function enterRoomAsGuest($password, $passedPasswordProtection = false) {
		$this->dispatcher->dispatch(self::class . '::preGuestEnterRoom', new GenericEvent($this));

		if (!$passedPasswordProtection && !$this->verifyPassword($password)) {
			throw new InvalidPasswordException();
		}

		$sessionId = $this->secureRandom->generate(255);
		while (!$this->db->insertIfNotExist('*PREFIX*talk_participants', [
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
	 * @param bool $active
	 */
	public function changeInCall($sessionId, $active) {
		if ($active) {
			$this->dispatcher->dispatch(self::class . '::preSessionJoinCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		} else {
			$this->dispatcher->dispatch(self::class . '::preSessionLeaveCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('inCall', $query->createNamedParameter((int) $active, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		if ($active) {
			$this->dispatcher->dispatch(self::class . '::postSessionJoinCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		} else {
			$this->dispatcher->dispatch(self::class . '::postSessionLeaveCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		}
	}

	/**
	 * @param string $password
	 * @return bool
	 */
	public function verifyPassword($password) {
		return !$this->hasPassword() || $this->hasher->verify($password, $this->password);
	}

	/**
	 * @param string $sessionId
	 * @return bool
	 */
	protected function isSessionUnique($sessionId) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_sessions')
			->from('talk_participants')
			->where($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)));
		$result = $query->execute();
		$numSessions = (int) $result->fetchColumn();
		$result->closeCursor();

		return $numSessions === 1;
	}

	public function cleanGuestParticipants() {
		$this->dispatcher->dispatch(self::class . '::preCleanGuests', new GenericEvent($this));

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->emptyString('userId'))
			->andWhere($query->expr()->lte('lastPing', $query->createNamedParameter(time() - 30, IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postCleanGuests', new GenericEvent($this));
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return array[] Array of users with [users => [userId => [lastPing, sessionId]], guests => [[lastPing, sessionId]]]
	 */
	public function getParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('lastPing', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();

		$users = $guests = [];
		while ($row = $result->fetch()) {
			if ($row['userId'] !== '' && $row['userId'] !== null) {
				$users[$row['userId']] = [
					'inCall' => (bool) $row['inCall'],
					'lastPing' => (int) $row['lastPing'],
					'sessionId' => $row['sessionId'],
					'participantType' => (int) $row['participantType'],
				];
			} else {
				$guests[] = [
					'inCall' => (bool) $row['inCall'],
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
			->from('talk_participants')
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
	 * Get all user ids which are participants in a room but currently not active
	 * @return string[]
	 */
	public function getInactiveUserIds() {
		$query = $this->db->getQueryBuilder();
		$query->select('userId')
			->from('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('sessionId', $query->createNamedParameter('0')))
			->andWhere($query->expr()->nonEmptyString('userId'));
		$result = $query->execute();

		$userIds = [];
		while ($row = $result->fetch()) {
			$userIds[] = $row['userId'];
		}
		$result->closeCursor();

		return $userIds;
	}

	/**
	 * @return bool
	 */
	public function hasSessionsInCall() {
		$query = $this->db->getQueryBuilder();
		$query->select('sessionId')
			->from('talk_participants')
			->where($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('inCall', $query->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool) $row;
	}

	/**
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return int
	 */
	public function getNumberOfParticipants($lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_participants')
			->from('talk_participants')
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
	 * @param string $userId
	 * @param string $sessionId
	 * @param int $timestamp
	 */
	public function ping($userId, $sessionId, $timestamp) {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('lastPing', $query->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('userId', $query->createNamedParameter((string) $userId)))
			->andWhere($query->expr()->eq('sessionId', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('roomId', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		$query->execute();
	}
}
