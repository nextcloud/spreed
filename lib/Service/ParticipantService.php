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
use OCA\Talk\Exceptions\InvalidPasswordException;
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
			// FIXME $this->removeUser($user, self::PARTICIPANT_LEFT);
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
	 * @param \DateTime|null $maxLastJoined
	 * @return string[]
	 */
	public function getParticipantUserIds(Room $room, \DateTime $maxLastJoined = null): array {
		$attendees = $this->attendeeMapper->getActorsByType($room->getId(), 'users', $maxLastJoined);

		return array_map(function(Attendee $attendee) {
			return $attendee->getActorId();
		}, $attendees);
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
}
