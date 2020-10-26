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

use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\JoinRoomGuestEvent;
use OCA\Talk\Events\JoinRoomUserEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ParticipantEvent;
use OCA\Talk\Events\RemoveParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Exceptions\InvalidPasswordException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

class ParticipantService {
	/** @var AttendeeMapper */
	protected $attendeeMapper;
	/** @var SessionMapper */
	protected $sessionMapper;
	/** @var SessionService */
	protected $sessionService;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IDBConnection */
	protected $connection;
	/** @var IEventDispatcher */
	private $dispatcher;
	/** @var IUserManager */
	private $userManager;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(AttendeeMapper $attendeeMapper,
								SessionMapper $sessionMapper,
								SessionService $sessionService,
								ISecureRandom $secureRandom,
								IDBConnection $connection,
								IEventDispatcher $dispatcher,
								IUserManager $userManager,
								ITimeFactory $timeFactory) {
		$this->attendeeMapper = $attendeeMapper;
		$this->sessionMapper = $sessionMapper;
		$this->sessionService = $sessionService;
		$this->secureRandom = $secureRandom;
		$this->connection = $connection;
		$this->dispatcher = $dispatcher;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
	}

	public function updateParticipantType(Room $room, Participant $participant, int $participantType): void {
		$attendee = $participant->getAttendee();
		$oldType = $attendee->getParticipantType();

		$event = new ModifyParticipantEvent($room, $participant, 'type', $participantType, $oldType);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_PARTICIPANT_TYPE_SET, $event);

		$attendee->setParticipantType($participantType);
		$this->attendeeMapper->update($attendee);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, $event);
	}

	/**
	 * @param Room $room
	 * @param IUser $user
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return Participant
	 * @throws InvalidPasswordException
	 * @throws UnauthorizedException
	 */
	public function joinRoom(Room $room, IUser $user, string $password, bool $passedPasswordProtection = false): Participant {
		$event = new JoinRoomUserEvent($room, $user, $password, $passedPasswordProtection);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_ROOM_CONNECT, $event);

		if ($event->getCancelJoin() === true) {
			$this->removeUser($room, $user, Room::PARTICIPANT_LEFT);
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		try {
			$attendee = $this->attendeeMapper->findByActor($room->getId(), 'users', $user->getUID());
		} catch (DoesNotExistException $e) {
			if (!$event->getPassedPasswordProtection() && !$room->verifyPassword($password)['result']) {
				throw new InvalidPasswordException('Provided password is invalid');
			}

			// User joining a public room, without being invited
			$this->addUsers($room, [[
				'actorType' => 'users',
				'actorId' => $user->getUID(),
				'participantType' => Participant::USER_SELF_JOINED,
			]]);

			$attendee = $this->attendeeMapper->findByActor($room->getId(), 'users', $user->getUID());
		}

		$session = $this->sessionService->createSessionForAttendee($attendee);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_ROOM_CONNECT, $event);

		return new Participant(
			\OC::$server->getDatabaseConnection(), // FIXME
			\OC::$server->getConfig(), // FIXME
			$room, $attendee, $session);
	}

	/**
	 * @param Room $room
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return Participant
	 * @throws InvalidPasswordException
	 * @throws UnauthorizedException
	 */
	public function joinRoomAsNewGuest(Room $room, string $password, bool $passedPasswordProtection = false): Participant {
		$event = new JoinRoomGuestEvent($room, $password, $passedPasswordProtection);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_GUEST_CONNECT, $event);

		if ($event->getCancelJoin()) {
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		if (!$event->getPassedPasswordProtection() && !$room->verifyPassword($password)['result']) {
			throw new InvalidPasswordException();
		}

		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int) $room->getLastMessage()->getId();
		}

		$randomActorId = $this->secureRandom->generate(255);

		$attendee = new Attendee();
		$attendee->setRoomId($room->getId());
		$attendee->setActorType('guests');
		$attendee->setActorId($randomActorId);
		$attendee->setParticipantType(Participant::GUEST);
		$attendee->setLastReadMessage($lastMessage);
		$this->attendeeMapper->insert($attendee);

		$session = $this->sessionService->createSessionForAttendee($attendee);

		// Update the random guest id
		$attendee->setActorId(sha1($session->getSessionId()));
		$this->attendeeMapper->update($attendee);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_GUEST_CONNECT, $event);

		return new Participant(
			\OC::$server->getDatabaseConnection(), // FIXME
			\OC::$server->getConfig(), // FIXME
			$room, $attendee, $session);
	}

	/**
	 * @param Room $room
	 * @param array $participants
	 */
	public function addUsers(Room $room, array $participants): void {
		$event = new AddParticipantsEvent($room, $participants);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_USERS_ADD, $event);

		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int) $room->getLastMessage()->getId();
		}

		foreach ($participants as $participant) {
			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType($participant['actorType']);
			$attendee->setActorId($participant['actorId']);
			$attendee->setParticipantType($participant['participantType'] ?? Participant::USER);
			$attendee->setLastReadMessage($lastMessage);
			$this->attendeeMapper->insert($attendee);
		}

		$this->dispatcher->dispatch(Room::EVENT_AFTER_USERS_ADD, $event);
	}

	public function ensureOneToOneRoomIsFilled(Room $room): void {
		if ($room->getType() !== Room::ONE_TO_ONE_CALL) {
			return;
		}

		$users = json_decode($room->getName(), true);
		$participants = $this->getParticipantUserIds($room);
		$missingUsers = array_diff($users, $participants);

		foreach ($missingUsers as $userId) {
			if ($this->userManager->userExists($userId)) {
				$this->addUsers($room, [[
					'actorType' => 'users',
					'actorId' => $userId,
					'participantType' => Participant::OWNER,
				]]);
			}
		}
	}

	public function leaveRoomAsSession(Room $room, Participant $participant): void {
		if (!$participant->isGuest()) {
			$event = new ParticipantEvent($room, $participant);
			$this->dispatcher->dispatch(Room::EVENT_BEFORE_ROOM_DISCONNECT, $event);
		} else {
			$event = new RemoveParticipantEvent($room, $participant, Room::PARTICIPANT_LEFT);
			$this->dispatcher->dispatch(Room::EVENT_BEFORE_PARTICIPANT_REMOVE, $event);
		}

		$session = $participant->getSession();
		if ($session instanceof Session) {
			$this->sessionMapper->delete($session);
		} else {
			$this->sessionMapper->deleteByAttendeeId($participant->getAttendee()->getId());
		}

		if ($participant->isGuest()
			|| $participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
			$this->attendeeMapper->delete($participant->getAttendee());
		}

		if (!$participant->isGuest()) {
			$this->dispatcher->dispatch(Room::EVENT_AFTER_ROOM_DISCONNECT, $event);
		} else {
			$this->dispatcher->dispatch(Room::EVENT_AFTER_PARTICIPANT_REMOVE, $event);
		}
	}

	public function removeAttendee(Room $room, Participant $participant, string $reason): void {
		$event = new RemoveParticipantEvent($room, $participant, $reason);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_PARTICIPANT_REMOVE, $event);

		$this->sessionMapper->deleteByAttendeeId($participant->getAttendee()->getId());
		$this->attendeeMapper->delete($participant->getAttendee());

		$this->dispatcher->dispatch(Room::EVENT_AFTER_PARTICIPANT_REMOVE, $event);
	}

	public function removeUser(Room $room, IUser $user, string $reason): void {
		try {
			$participant = $room->getParticipant($user->getUID());
		} catch (ParticipantNotFoundException $e) {
			return;
		}

		$event = new RemoveUserEvent($room, $participant, $user, $reason);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_USER_REMOVE, $event);

		$session = $participant->getSession();
		if ($session instanceof Session) {
			$this->sessionMapper->delete($session);
		}

		$attendee = $participant->getAttendee();
		$this->attendeeMapper->delete($attendee);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_USER_REMOVE, $event);
	}

	public function cleanGuestParticipants(Room $room): void {
		$event = new RoomEvent($room);
		$this->dispatcher->dispatch(Room::EVENT_BEFORE_GUESTS_CLEAN, $event);

		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('s.id', 's_id')
			->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('s.attendee_id', 'a.id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter('guests')))
			->andWhere($query->expr()->lte('s.last_ping', $query->createNamedParameter($this->timeFactory->getTime() - 100, IQueryBuilder::PARAM_INT)));

		$sessionTableIds = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$sessionTableIds[] = (int) $row['s_id'];
		}
		$result->closeCursor();

		$this->sessionService->deleteSessionsById($sessionTableIds);

		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('a.id', 'a_id')
			->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq('s.attendee_id', 'a.id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter('guests')))
			->andWhere($query->expr()->isNull('s.id'));

		$attendeeIds = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$attendeeIds[] = (int) $row['a_id'];
		}
		$result->closeCursor();

		$this->attendeeMapper->deleteByIds($attendeeIds);

		$this->dispatcher->dispatch(Room::EVENT_AFTER_GUESTS_CLEAN, $event);
	}

	public function changeInCall(Room $room, Participant $participant, int $flags): void {
		$session = $participant->getSession();
		if (!$session instanceof Session) {
			return;
		}

		$event = new ModifyParticipantEvent($room, $participant, 'inCall', $flags, $session->getInCall());
		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$this->dispatcher->dispatch(Room::EVENT_BEFORE_SESSION_JOIN_CALL, $event);
		} else {
			$this->dispatcher->dispatch(Room::EVENT_BEFORE_SESSION_LEAVE_CALL, $event);
		}

		$session->setInCall($flags);
		$this->sessionMapper->update($session);

		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$attendee = $participant->getAttendee();
			$attendee->setLastJoinedCall($this->timeFactory->getTime());
			$this->attendeeMapper->update($attendee);
		}

		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$this->dispatcher->dispatch(Room::EVENT_AFTER_SESSION_JOIN_CALL, $event);
		} else {
			$this->dispatcher->dispatch(Room::EVENT_AFTER_SESSION_LEAVE_CALL, $event);
		}
	}

	public function markUsersAsMentioned(Room $room, array $userIds, int $messageId): void {
		$query = $this->connection->getQueryBuilder();
		$query->update('talk_attendees')
			->set('last_mention_message', $query->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('actor_type', $query->createNamedParameter('users')))
			->andWhere($query->expr()->in('actor_id', $query->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$query->execute();
	}

	/**
	 * @param Room $room
	 * @return Participant[]
	 */
	public function getParticipantsForRoom(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.*')
			->selectAlias('a.id', 'a_id')
			->addSelect('s.*')
			->selectAlias('s.id', 's_id')
			->from('talk_attendees', 'a')
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));

		$participants = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
			if (isset($row['s_id'])) {
				$session = $this->sessionMapper->createSessionFromRow($row);
			} else {
				$session = null;
			}

			$participants[] = new Participant(
				\OC::$server->getDatabaseConnection(), // FIXME
				\OC::$server->getConfig(), // FIXME
				$room, $attendee, $session);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @param Room $room
	 * @return Participant[]
	 */
	public function getParticipantsWithSession(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.*')
			->selectAlias('a.id', 'a_id')
			->addSelect('s.*')
			->selectAlias('s.id', 's_id')
			->from('talk_attendees', 'a')
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('s.id'));

		$participants = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
			$session = $this->sessionMapper->createSessionFromRow($row);

			$participants[] = new Participant(
				\OC::$server->getDatabaseConnection(), // FIXME
				\OC::$server->getConfig(), // FIXME
				$room, $attendee, $session);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @param Room $room
	 * @return Participant[]
	 */
	public function getParticipantsInCall(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.*')
			->selectAlias('a.id', 'a_id')
			->addSelect('s.*')
			->selectAlias('s.id', 's_id')
			->from('talk_sessions', 's')
			->leftJoin(
				's', 'talk_attendees', 'a',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)));

		$participants = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
			$session = $this->sessionMapper->createSessionFromRow($row);

			$participants[] = new Participant(
				\OC::$server->getDatabaseConnection(), // FIXME
				\OC::$server->getConfig(), // FIXME
				$room, $attendee, $session);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @param Room $room
	 * @param int $notificationLevel
	 * @return Participant[]
	 */
	public function getParticipantsByNotificationLevel(Room $room, int $notificationLevel): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.*')
			->selectAlias('a.id', 'a_id')
			->addSelect('s.*')
			->selectAlias('s.id', 's_id')
			->from('talk_sessions', 's')
			->leftJoin(
				's', 'talk_attendees', 'a',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('notification_level', $query->createNamedParameter($notificationLevel, IQueryBuilder::PARAM_INT)));

		$participants = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
			$session = $this->sessionMapper->createSessionFromRow($row);

			$participants[] = new Participant(
				\OC::$server->getDatabaseConnection(), // FIXME
				\OC::$server->getConfig(), // FIXME
				$room, $attendee, $session);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @param Room $room
	 * @param \DateTime|null $maxLastJoined
	 * @return string[]
	 */
	public function getParticipantUserIds(Room $room, \DateTime $maxLastJoined = null): array {
		$attendees = $this->attendeeMapper->getActorsByType($room->getId(), 'users', $maxLastJoined);

		return array_map(static function (Attendee $attendee) {
			return $attendee->getActorId();
		}, $attendees);
	}

	/**
	 * @param Room $room
	 * @return string[]
	 */
	public function getParticipantUserIdsNotInCall(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.actor_id')
			->from('talk_sessions', 's')
			->leftJoin(
				's', 'talk_attendees', 'a',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter('users')))
			->andWhere($query->expr()->orX(
				$query->expr()->eq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)),
				$query->expr()->isNull('s.in_call')
			));

		$userIds = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$userIds[] = $row['actor_id'];
		}
		$result->closeCursor();

		return $userIds;
	}

	/**
	 * @param Room $room
	 * @return int
	 */
	public function getNumberOfUsers(Room $room): int {
		return $this->attendeeMapper->countActorsByParticipantType($room->getId(), [
			Participant::USER,
			Participant::MODERATOR,
			Participant::OWNER,
		]);
	}

	/**
	 * @param Room $room
	 * @param bool $ignoreGuestModerators
	 * @return int
	 */
	public function getNumberOfModerators(Room $room, bool $ignoreGuestModerators = true): int {
		$participantTypes = [
			Participant::MODERATOR,
			Participant::OWNER,
		];
		if (!$ignoreGuestModerators) {
			$participantTypes[] = Participant::GUEST_MODERATOR;
		}
		return $this->attendeeMapper->countActorsByParticipantType($room->getId(), $participantTypes);
	}

	/**
	 * @param Room $room
	 * @return int
	 */
	public function getNumberOfActors(Room $room): int {
		return $this->attendeeMapper->countActorsByParticipantType($room->getId(), []);
	}

	/**
	 * @param Room $room
	 * @return bool
	 */
	public function hasActiveSessions(Room $room): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('a.room_id')
			->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq(
				'a.id', 's.attendee_id'
			))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('s.id'))
			->setMaxResults(1);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool) $row;
	}

	/**
	 * @param Room $room
	 * @return bool
	 */
	public function hasActiveSessionsInCall(Room $room): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('a.room_id')
			->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq(
				'a.id', 's.attendee_id'
			))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('s.in_call'))
			->andWhere($query->expr()->neq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)))
			->setMaxResults(1);
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool) $row;
	}
}
