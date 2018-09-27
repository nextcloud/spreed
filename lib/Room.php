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
use OCP\Comments\IComment;
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
	/** @var \DateTime|null */
	private $lastActivity;
	/** @var IComment|null */
	private $lastMessage;
	/** @var string */
	private $objectType;
	/** @var string */
	private $objectId;

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
	 * @param \DateTime|null $lastActivity
	 * @param IComment|null $lastMessage
	 * @param string $objectType
	 * @param string $objectId
	 */
	public function __construct(Manager $manager, IDBConnection $db, ISecureRandom $secureRandom, EventDispatcherInterface $dispatcher, IHasher $hasher, $id, $type, $token, $name, $password, $activeGuests, \DateTime $activeSince = null, \DateTime $lastActivity = null, IComment $lastMessage = null, $objectType = '', $objectId = '') {
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
		$this->lastActivity = $lastActivity;
		$this->lastMessage = $lastMessage;
		$this->objectType = $objectType;
		$this->objectId = $objectId;
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
	 * @return \DateTime|null
	 */
	public function getLastActivity() {
		return $this->lastActivity;
	}

	/**
	 * @return IComment|null
	 */
	public function getLastMessage() {
		return $this->lastMessage;
	}

	/**
	 * @return string
	 */
	public function getObjectType() {
		return $this->objectType;
	}

	/**
	 * @return string
	 */
	public function getObjectId() {
		return $this->objectId;
	}

	/**
	 * @return bool
	 */
	public function hasPassword() {
		return $this->password !== '';
	}

	public function getPassword(): string {
		return $this->password;
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
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId())));
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
			->where($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId())));
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
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
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
	 * @param \DateTime $now
	 * @return bool
	 */
	public function setLastActivity(\DateTime $now) {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('last_activity', $query->createNamedParameter($now, 'datetime'))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->lastActivity = $now;

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
				->set('active_guests', $query->createFunction($query->getColumnName('active_guests') . ' + 1'))
				->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
			$query->execute();

			$this->activeGuests++;
		}

		if ($this->activeSince instanceof \DateTime) {
			return false;
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('active_since', $query->createNamedParameter($since, 'datetime'))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNull('active_since'));
		$query->execute();

		$this->activeSince = $since;

		return true;
	}

	public function setLastMessage(IComment $message) {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('last_message', $query->createNamedParameter((int) $message->getId()))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	public function resetActiveSince() {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_rooms')
			->set('active_guests', $query->createNamedParameter(0))
			->set('active_since', $query->createNamedParameter(null, 'datetime'))
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
				->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->in('participant_type', $query->createNamedParameter([Participant::GUEST, Participant::USER_SELF_JOINED], IQueryBuilder::PARAM_INT_ARRAY)));
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
					'user_id' => $query->createParameter('user_id'),
					'session_id' => $query->createParameter('session_id'),
					'participant_type' => $query->createParameter('participant_type'),
					'room_id' => $query->createNamedParameter($this->getId()),
					'last_ping' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				]
			);

		foreach ($participants as $participant) {
			$query->setParameter('user_id', $participant['userId'])
				->setParameter('session_id', isset($participant['sessionId']) ? $participant['sessionId'] : '0')
				->setParameter('participant_type', isset($participant['participantType']) ? $participant['participantType'] : Participant::USER, IQueryBuilder::PARAM_INT);

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
			->set('participant_type', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($participant)));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postSetParticipantType', new GenericEvent($this, [
			'user' => $participant,
			'newType' => $participantType,
		]));
	}

	/**
	 * @param Participant $participant
	 * @param int $participantType
	 */
	public function setParticipantTypeBySession(Participant $participant, int $participantType) {
		$this->dispatcher->dispatch(self::class . '::preSetParticipantTypeBySession', new GenericEvent($this, [
			'participant' => $participant,
			'newType' => $participantType,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('participant_type', $query->createNamedParameter($participantType, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('session_id', $query->createNamedParameter($participant->getSessionId())));
		$query->execute();

		$this->dispatcher->dispatch(self::class . '::postSetParticipantTypeBySession', new GenericEvent($this, [
			'participant' => $participant,
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
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($user->getUID())));
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
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('session_id', $query->createNamedParameter($participant->getSessionId())));
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
	public function joinRoom($userId, $password, $passedPasswordProtection = false) {
		$this->dispatcher->dispatch(self::class . '::preJoinRoom', new GenericEvent($this, [
			'userId' => $userId,
			'password' => $password,
			'passedPasswordProtection' => $passedPasswordProtection,
		]));

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('session_id', $query->createParameter('session_id'))
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)));

		$sessionId = $this->secureRandom->generate(255);
		$query->setParameter('session_id', $sessionId);
		$result = $query->execute();

		if ($result === 0) {
			if (!$passedPasswordProtection && !$this->verifyPassword($password)['result']) {
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
			$query->setParameter('session_id', $sessionId);
			$query->execute();
		}

		$this->dispatcher->dispatch(self::class . '::postJoinRoom', new GenericEvent($this, [
			'userId' => $userId,
			'password' => $password,
			'passedPasswordProtection' => $passedPasswordProtection,
		]));

		return $sessionId;
	}

	/**
	 * @param string $userId
	 */
	public function leaveRoom($userId) {
		$this->dispatcher->dispatch(self::class . '::preUserDisconnectRoom', new GenericEvent($this, [
			'userId' => $userId,
		]));

		// Reset session when leaving a normal room
		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('session_id', $query->createNamedParameter('0'))
			->set('in_call', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('participant_type', $query->createNamedParameter(Participant::USER_SELF_JOINED, IQueryBuilder::PARAM_INT)));
		$query->execute();

		// And kill session when leaving a self joined room
		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('participant_type', $query->createNamedParameter(Participant::USER_SELF_JOINED, IQueryBuilder::PARAM_INT)));
		$selfJoined = (bool) $query->execute();

		$this->dispatcher->dispatch(self::class . '::postUserDisconnectRoom', new GenericEvent($this, [
			'userId' => $userId,
			'selfJoin' => $selfJoined,
		]));
	}

	/**
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return string
	 * @throws InvalidPasswordException
	 */
	public function joinRoomGuest($password, $passedPasswordProtection = false) {
		$this->dispatcher->dispatch(self::class . '::preJoinRoomGuest', new GenericEvent($this));

		if (!$passedPasswordProtection && !$this->verifyPassword($password)['result']) {
			throw new InvalidPasswordException();
		}

		$sessionId = $this->secureRandom->generate(255);
		while (!$this->db->insertIfNotExist('*PREFIX*talk_participants', [
			'user_id' => '',
			'room_id' => $this->getId(),
			'last_ping' => 0,
			'session_id' => $sessionId,
			'participant_type' => Participant::GUEST,
		], ['session_id'])) {
			$sessionId = $this->secureRandom->generate(255);
		}

		$this->dispatcher->dispatch(self::class . '::postJoinRoomGuest', new GenericEvent($this));

		return $sessionId;
	}


	public function changeInCall(string $sessionId, int $flags) {
		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$this->dispatcher->dispatch(self::class . '::preSessionJoinCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
				'flags' => $flags,
			]));
		} else {
			$this->dispatcher->dispatch(self::class . '::preSessionLeaveCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		}

		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('in_call', $query->createNamedParameter($flags, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$this->dispatcher->dispatch(self::class . '::postSessionJoinCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
				'flags' => $flags,
			]));
		} else {
			$this->dispatcher->dispatch(self::class . '::postSessionLeaveCall', new GenericEvent($this, [
				'sessionId' => $sessionId,
			]));
		}
	}

	/**
	 * @param string $password
	 * @return array
	 */
	public function verifyPassword($password) {
		$event = new GenericEvent($this, [
			'password' => $password
		]);

		$this->dispatcher->dispatch(self::class . '::verifyPassword', $event);
		if ($event->hasArgument('result')) {
			$result = $event->getArgument('result');
			return [
				'result' => $result['result'] ?? false,
				'url' => $result['url'] ?? ''
			];
		}

		return [
			'result' => !$this->hasPassword() || $this->hasher->verify($password, $this->password),
			'url' => ''
		];
	}

	/**
	 * @param string $sessionId
	 * @return bool
	 */
	protected function isSessionUnique($sessionId) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_sessions')
			->from('talk_participants')
			->where($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)));
		$result = $query->execute();
		$numSessions = (int) $result->fetchColumn();
		$result->closeCursor();

		return $numSessions === 1;
	}

	public function cleanGuestParticipants() {
		$this->dispatcher->dispatch(self::class . '::preCleanGuests', new GenericEvent($this));

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->emptyString('user_id'))
			->andWhere($query->expr()->lte('last_ping', $query->createNamedParameter(time() - 100, IQueryBuilder::PARAM_INT)));
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
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('last_ping', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->execute();

		$users = $guests = [];
		while ($row = $result->fetch()) {
			if ($row['user_id'] !== '' && $row['user_id'] !== null) {
				$users[$row['user_id']] = [
					'inCall' => (int) $row['in_call'],
					'lastPing' => (int) $row['last_ping'],
					'sessionId' => $row['session_id'],
					'participantType' => (int) $row['participant_type'],
				];
			} else {
				$guests[] = [
					'inCall' => (int) $row['in_call'],
					'lastPing' => (int) $row['last_ping'],
					'participantType' => (int) $row['participant_type'],
					'sessionId' => $row['session_id'],
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
	 * @param int $notificationLevel
	 * @return Participant[] Array of participants
	 */
	public function getParticipantsByNotificationLevel(int $notificationLevel): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('notification_level', $query->createNamedParameter($notificationLevel, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();

		$participants = [];
		while ($row = $result->fetch()) {
			$participants[] = $this->manager->createParticipantObject($this, $row);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @return string[]
	 */
	public function getActiveSessions() {
		$query = $this->db->getQueryBuilder();
		$query->select('session_id')
			->from('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('session_id', $query->createNamedParameter('0')));
		$result = $query->execute();

		$sessions = [];
		while ($row = $result->fetch()) {
			$sessions[] = $row['session_id'];
		}
		$result->closeCursor();

		return $sessions;
	}

	/**
	 * Get all user ids which are participants in a room but currently not in the call
	 * @return string[]
	 */
	public function getNotInCallUserIds() {
		$query = $this->db->getQueryBuilder();
		$query->select('user_id')
			->from('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->nonEmptyString('user_id'))
			->andWhere($query->expr()->eq('in_call', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();

		$userIds = [];
		while ($row = $result->fetch()) {
			$userIds[] = $row['user_id'];
		}
		$result->closeCursor();

		return $userIds;
	}

	/**
	 * @return bool
	 */
	public function hasSessionsInCall() {
		$query = $this->db->getQueryBuilder();
		$query->select('session_id')
			->from('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool) $row;
	}

	/**
	 * @param bool $ignoreGuests
	 * @param int $lastPing When the last ping is older than the given timestamp, the user is ignored
	 * @return int
	 */
	public function getNumberOfParticipants($ignoreGuests = true, $lastPing = 0) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_participants')
			->from('talk_participants')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		if ($lastPing > 0) {
			$query->andWhere($query->expr()->gt('last_ping', $query->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT)));
		}

		if ($ignoreGuests) {
			$query->andWhere($query->expr()->notIn('participant_type', $query->createNamedParameter([
				Participant::GUEST,
				Participant::USER_SELF_JOINED,
			], IQueryBuilder::PARAM_INT_ARRAY)));
		}

		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return isset($row['num_participants']) ? (int) $row['num_participants'] : 0;
	}

	public function markUsersAsMentioned(array $userIds, int $messageId) {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('last_mention_message', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('user_id', $query->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$query->execute();
	}

	/**
	 * @param string $userId
	 * @param string $sessionId
	 * @param int $timestamp
	 */
	public function ping($userId, $sessionId, $timestamp) {
		$query = $this->db->getQueryBuilder();
		$query->update('talk_participants')
			->set('last_ping', $query->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('user_id', $query->createNamedParameter((string) $userId)))
			->andWhere($query->expr()->eq('session_id', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));

		$query->execute();
	}
}
