<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Talk\CachePrefix;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Controller\CallNotificationController;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\BeforeAttendeeRemovedEvent;
use OCA\Talk\Events\BeforeAttendeesAddedEvent;
use OCA\Talk\Events\BeforeCallEndedForEveryoneEvent;
use OCA\Talk\Events\BeforeFederatedUserJoinedRoomEvent;
use OCA\Talk\Events\BeforeGuestJoinedRoomEvent;
use OCA\Talk\Events\BeforeGuestsCleanedUpEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\BeforeSessionLeftRoomEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\FederatedUserJoinedRoomEvent;
use OCA\Talk\Events\GuestJoinedRoomEvent;
use OCA\Talk\Events\GuestsCleanedUpEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\SessionLeftRoomEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Events\UserJoinedRoomEvent;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\DialOutFailedException;
use OCA\Talk\Exceptions\InvalidPasswordException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\ParticipantProperty\PermissionsException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\InvitationList;
use OCA\Talk\Model\SelectHelper;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\UserStatus\IManager as IUserStatusManager;
use OCP\UserStatus\IUserStatus;
use Psr\Log\LoggerInterface;

class ParticipantService {

	/** @var array<int, array<string, array<string, Participant>>> */
	protected array $actorCache;
	/** @var array<int, array<string, Participant>> */
	protected array $sessionCache;

	public function __construct(
		protected IConfig $serverConfig,
		protected Config $talkConfig,
		protected AttendeeMapper $attendeeMapper,
		protected SessionMapper $sessionMapper,
		protected SessionService $sessionService,
		private ISecureRandom $secureRandom,
		protected IDBConnection $connection,
		private IEventDispatcher $dispatcher,
		private IUserManager $userManager,
		private ICloudIdManager $cloudIdManager,
		private IGroupManager $groupManager,
		private MembershipService $membershipService,
		private BackendNotifier $backendNotifier,
		private ITimeFactory $timeFactory,
		private ICacheFactory $cacheFactory,
		private IUserStatusManager $userStatusManager,
		private LoggerInterface $logger,
	) {
	}

	public function updateParticipantType(Room $room, Participant $participant, int $participantType): void {
		$attendee = $participant->getAttendee();

		if ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
			// Can not promote/demote groups
			return;
		}

		$oldType = $attendee->getParticipantType();
		if ($oldType === $participantType) {
			return;
		}

		$event = new BeforeParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_TYPE, $participantType, $oldType);
		$this->dispatcher->dispatchTyped($event);

		$attendee->setParticipantType($participantType);

		$promotedToModerator = in_array($participantType, [
			Participant::OWNER,
			Participant::MODERATOR,
		], true);
		$demotedFromModerator = in_array($oldType, [
			Participant::OWNER,
			Participant::MODERATOR,
		], true);

		if ($promotedToModerator) {
			// Reset permissions on promotion
			$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
		}

		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);

		// XOR so we don't move the participant in and out when they are changed from moderator to owner or vice versa
		if (($promotedToModerator xor $demotedFromModerator) && $room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			/** @var Manager $manager */
			$manager = Server::get(Manager::class);

			$breakoutRooms = $manager->getMultipleRoomsByObject(BreakoutRoom::PARENT_OBJECT_TYPE, $room->getToken());

			foreach ($breakoutRooms as $breakoutRoom) {
				try {
					$breakoutRoomParticipant = $this->getParticipantByActor(
						$breakoutRoom,
						$attendee->getActorType(),
						$attendee->getActorId()
					);

					if ($demotedFromModerator) {
						// Remove participant from all breakout rooms
						$this->removeAttendee($breakoutRoom, $breakoutRoomParticipant, AAttendeeRemovedEvent::REASON_REMOVED);
					} elseif (!$breakoutRoomParticipant->hasModeratorPermissions()) {
						if ($breakoutRoomParticipant->getAttendee()->getParticipantType() === Participant::USER
							|| $breakoutRoomParticipant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
							$this->updateParticipantType($breakoutRoom, $breakoutRoomParticipant, Participant::MODERATOR);
						}
					}
				} catch (ParticipantNotFoundException $e) {
					if ($promotedToModerator) {
						// Add participant as a moderator when they were not in the room already
						$this->addUsers($breakoutRoom, [
							[
								'actorType' => $attendee->getActorType(),
								'actorId' => $attendee->getActorId(),
								'displayName' => $attendee->getDisplayName(),
								'participantType' => $attendee->getParticipantType(),
							],
						]);
					}
				}
			}
		}

		$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_TYPE, $participantType, $oldType);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @throws PermissionsException
	 */
	public function updatePermissions(Room $room, Participant $participant, string $method, int $newPermissions): void {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			throw new PermissionsException(PermissionsException::REASON_ROOM_TYPE);
		}

		if ($participant->hasModeratorPermissions()) {
			throw new PermissionsException(PermissionsException::REASON_MODERATOR);
		}

		$attendee = $participant->getAttendee();

		if ($attendee->getActorType() === Attendee::ACTOR_GROUPS || $attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
			// Can not set publishing permissions for those actor types
			throw new PermissionsException(PermissionsException::REASON_TYPE);
		}

		if ($newPermissions < Attendee::PERMISSIONS_DEFAULT || $newPermissions > Attendee::PERMISSIONS_MAX_CUSTOM) {
			throw new PermissionsException(PermissionsException::REASON_VALUE);
		}

		$oldPermissions = $participant->getPermissions();
		if ($method === Attendee::PERMISSIONS_MODIFY_SET) {
			if ($newPermissions !== Attendee::PERMISSIONS_DEFAULT) {
				// Make sure the custom flag is set when not setting to default permissions
				$newPermissions |= Attendee::PERMISSIONS_CUSTOM;
			}
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_ADD) {
			$newPermissions = $oldPermissions | $newPermissions;
		} elseif ($method === Attendee::PERMISSIONS_MODIFY_REMOVE) {
			$newPermissions = $oldPermissions & ~$newPermissions;
		} else {
			throw new PermissionsException(PermissionsException::REASON_METHOD);
		}

		$event = new BeforeParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_PERMISSIONS, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);

		$attendee->setPermissions($newPermissions);
		if ($attendee->getParticipantType() === Participant::USER_SELF_JOINED) {
			$attendee->setParticipantType(Participant::USER);
		}
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);

		$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_PERMISSIONS, $newPermissions, $oldPermissions);
		$this->dispatcher->dispatchTyped($event);
	}

	public function updateAllPermissions(Room $room, string $method, int $newState): void {
		$this->attendeeMapper->modifyPermissions($room->getId(), $method, $newState);
	}

	public function updateLastReadMessage(Participant $participant, int $lastReadMessage): void {
		$attendee = $participant->getAttendee();
		$attendee->setLastReadMessage($lastReadMessage);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	public function updateUnreadInfoForProxyParticipant(Participant $participant, int $unreadMessageCount, bool $hasMention, bool $hadDirectMention, int $lastReadMessageId): void {
		$attendee = $participant->getAttendee();
		$attendee->setUnreadMessages($unreadMessageCount);
		$attendee->setLastReadMessage($lastReadMessageId);
		$attendee->setLastMentionMessage($hasMention ? 1 : 0);
		$attendee->setLastMentionDirect($hadDirectMention ? 1 : 0);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	public function updateFavoriteStatus(Participant $participant, bool $isFavorite): void {
		$attendee = $participant->getAttendee();
		$attendee->setFavorite($isFavorite);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 * @param int $level
	 * @throws \InvalidArgumentException When the notification level is invalid
	 */
	public function updateNotificationLevel(Participant $participant, int $level): void {
		if (!\in_array($level, [
			Participant::NOTIFY_ALWAYS,
			Participant::NOTIFY_MENTION,
			Participant::NOTIFY_NEVER
		], true)) {
			throw new \InvalidArgumentException('level');
		}

		$attendee = $participant->getAttendee();
		$attendee->setNotificationLevel($level);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 * @param int $level
	 * @throws \InvalidArgumentException
	 */
	public function updateNotificationCalls(Participant $participant, int $level): void {
		if (!\in_array($level, [
			Participant::NOTIFY_CALLS_OFF,
			Participant::NOTIFY_CALLS_ON,
		], true)) {
			throw new \InvalidArgumentException('level');
		}

		$attendee = $participant->getAttendee();
		$attendee->setNotificationCalls($level);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function archiveConversation(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setArchived(true);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function unarchiveConversation(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setArchived(false);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function markConversationAsImportant(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setImportant(true);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function markConversationAsUnimportant(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setImportant(false);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function markConversationAsSensitive(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setSensitive(true);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param Participant $participant
	 */
	public function markConversationAsInsensitive(Participant $participant): void {
		$attendee = $participant->getAttendee();
		$attendee->setSensitive(false);
		$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @param RoomService $roomService
	 * @param Room $room
	 * @param IUser $user
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @return Participant
	 * @throws InvalidPasswordException
	 * @throws UnauthorizedException
	 */
	public function joinRoom(RoomService $roomService, Room $room, IUser $user, string $password, bool $passedPasswordProtection = false): Participant {
		$event = new BeforeUserJoinedRoomEvent($room, $user, $password, $passedPasswordProtection);
		$this->dispatcher->dispatchTyped($event);

		if ($event->getCancelJoin() === true) {
			$this->removeUser($room, $user, AAttendeeRemovedEvent::REASON_LEFT);
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		try {
			$attendee = $this->attendeeMapper->findByActor($room->getId(), Attendee::ACTOR_USERS, $user->getUID());
		} catch (DoesNotExistException $e) {
			// queried here to avoid loop deps
			$manager = Server::get(Manager::class);
			$isListableByUser = $manager->isRoomListableByUser($room, $user->getUID());

			if (!$isListableByUser && !$event->getPassedPasswordProtection() && !$roomService->verifyPassword($room, $password)['result']) {
				throw new InvalidPasswordException('Provided password is invalid');
			}

			// User joining a group or public call through listing
			if (($room->getType() === Room::TYPE_GROUP || $room->getType() === Room::TYPE_PUBLIC) && $isListableByUser) {
				$this->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
					// need to use "USER" here, because "USER_SELF_JOINED" only works for public calls
					'participantType' => Participant::USER,
				]], $user);
			} elseif ($room->getType() === Room::TYPE_PUBLIC) {
				// User joining a public room, without being invited
				$this->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
					'participantType' => Participant::USER_SELF_JOINED,
				]], $user);
			} else {
				// shouldn't happen unless some code called joinRoom without previous checks
				throw new UnauthorizedException('Participant is not allowed to join');
			}

			$attendee = $this->attendeeMapper->findByActor($room->getId(), Attendee::ACTOR_USERS, $user->getUID());
		}

		$session = $this->sessionService->createSessionForAttendee($attendee);
		$participant = new Participant($room, $attendee, $session);

		$event = new UserJoinedRoomEvent($room, $user, $participant);
		$this->dispatcher->dispatchTyped($event);

		return $participant;
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function joinRoomAsFederatedUser(Room $room, string $actorType, string $actorId, string $sessionId): Participant {
		$event = new BeforeFederatedUserJoinedRoomEvent($room, $actorId);
		$this->dispatcher->dispatchTyped($event);

		if ($event->isJoinCanceled()) {
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		try {
			$participant = $this->getParticipantByActor($room, $actorType, $actorId);
			$attendee = $participant->getAttendee();
		} catch (ParticipantNotFoundException $e) {
			// shouldn't happen unless some code called joinRoom without previous checks
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		$session = $this->sessionService->createSessionForAttendee($attendee, $sessionId);

		$event = new FederatedUserJoinedRoomEvent($room, $actorId);
		$this->dispatcher->dispatchTyped($event);

		return new Participant($room, $attendee, $session);
	}

	/**
	 * @param RoomService $roomService
	 * @param Room $room
	 * @param string $password
	 * @param bool $passedPasswordProtection
	 * @param ?Participant $previousParticipant
	 * @return Participant
	 * @throws InvalidPasswordException
	 * @throws UnauthorizedException
	 */
	public function joinRoomAsNewGuest(RoomService $roomService, Room $room, string $password, bool $passedPasswordProtection = false, ?Participant $previousParticipant = null, ?string $displayName = null): Participant {
		$event = new BeforeGuestJoinedRoomEvent($room, $password, $passedPasswordProtection);
		$this->dispatcher->dispatchTyped($event);

		if ($event->getCancelJoin()) {
			throw new UnauthorizedException('Participant is not allowed to join');
		}

		if (!$event->getPassedPasswordProtection() && !$roomService->verifyPassword($room, $password)['result']) {
			throw new InvalidPasswordException();
		}

		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int)$room->getLastMessage()->getId();
		}

		if ($previousParticipant instanceof Participant) {
			$attendee = $previousParticipant->getAttendee();
		} else {
			$randomActorId = $this->secureRandom->generate(255);

			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType(Attendee::ACTOR_GUESTS);
			$attendee->setActorId($randomActorId);
			$attendee->setParticipantType(Participant::GUEST);
			$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
			$attendee->setLastReadMessage($lastMessage);

			if ($displayName !== null && $displayName !== '') {
				$attendee->setDisplayName($displayName);
			}

			$this->attendeeMapper->insert($attendee);

			$attendeeEvent = new AttendeesAddedEvent($room, [$attendee]);
			$this->dispatcher->dispatchTyped($attendeeEvent);
		}

		$session = $this->sessionService->createSessionForAttendee($attendee);

		if (!$previousParticipant instanceof Participant) {
			// Update the random guest id
			$attendee->setActorId(sha1($session->getSessionId()));
			$this->attendeeMapper->update($attendee);
		}

		$participant = new Participant($room, $attendee, $session);

		$event = new GuestJoinedRoomEvent($room, $participant);
		$this->dispatcher->dispatchTyped($event);

		return $participant;
	}

	public function addInvitationList(Room $room, InvitationList $invitationList, ?IUser $addedBy = null): void {
		$participantsToAdd = [];
		foreach ($invitationList->getUsers() as $user) {
			$participantsToAdd[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
		}

		foreach ($invitationList->getFederatedUsers() as $cloudId) {
			$participantsToAdd[] = [
				'actorType' => Attendee::ACTOR_FEDERATED_USERS,
				'actorId' => $cloudId->getId(),
				'displayName' => $cloudId->getDisplayId(),
			];
		}

		foreach ($invitationList->getPhoneNumbers() as $phoneNumber) {
			$participantsToAdd[] = [
				'actorType' => Attendee::ACTOR_PHONES,
				'actorId' => sha1($phoneNumber . '#' . $this->timeFactory->getTime()),
				'displayName' => substr($phoneNumber, 0, -4) . '…', // FIXME Allow the UI to hand in a name (when selected from contacts?)
				'phoneNumber' => $phoneNumber,
			];
		}

		$existingParticipants = [];
		if (!empty($participantsToAdd)) {
			$attendees = $this->addUsers($room, $participantsToAdd, $addedBy);
			$existingParticipants = array_map(static fn (Attendee $attendee): Participant => new Participant($room, $attendee, null), $attendees);
		}

		$emails = $invitationList->getEmails();
		if (!empty($emails)) {
			$guestManager = Server::get(GuestManager::class);
			foreach ($emails as $email) {
				$actorId = hash('sha256', $email);
				try {
					$this->getParticipantByActor($room, Attendee::ACTOR_EMAILS, $actorId);
				} catch (ParticipantNotFoundException) {
					$participant = $this->inviteEmailAddress($room, $actorId, $email);
					try {
						$guestManager->sendEmailInvitation($room, $participant);
					} catch (\InvalidArgumentException) {
					}
				}
			}
		}

		foreach ($invitationList->getGroup() as $group) {
			$this->addGroup($room, $group, $existingParticipants);
		}

		foreach ($invitationList->getTeams() as $team) {
			$this->addCircle($room, $team, $existingParticipants);
		}
	}

	/**
	 * @param Room $room
	 * @param array $participants
	 * @param IUser|null $addedBy User that is attempting to add these users (must be set for federated users to be added)
	 * @return list<Attendee>
	 * @throws CannotReachRemoteException thrown when sending the federation request didn't work
	 * @throws \Exception thrown if $addedBy is not set when adding a federated user
	 */
	public function addUsers(Room $room, array $participants, ?IUser $addedBy = null, bool $bansAlreadyChecked = false): array {
		if (empty($participants)) {
			return [];
		}

		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int)$room->getLastMessage()->getId();
		}

		$bannedUserIds = [];
		if (!$bansAlreadyChecked) {
			$banService = Server::get(BanService::class);
			$bannedUserIds = $banService->getBannedUserIdsForRoom($room->getId());
		}
		$attendees = [];
		foreach ($participants as $participant) {
			$readPrivacy = Participant::PRIVACY_PUBLIC;
			if ($participant['actorType'] === Attendee::ACTOR_USERS) {
				if (isset($bannedUserIds[$participant['actorId']])) {
					$this->logger->warning('User ' . $participant['actorId'] . ' is banned from conversation ' . $room->getToken() . ' and was skipped while adding users');
					continue;
				}

				$readPrivacy = $this->talkConfig->getUserReadPrivacy($participant['actorId']);
			} elseif ($participant['actorType'] === Attendee::ACTOR_FEDERATED_USERS) {
				if ($addedBy === null) {
					throw new \Exception('$addedBy must be set to add a federated user');
				}
				$participant['accessToken'] = $this->secureRandom->generate(
					FederationManager::TOKEN_LENGTH,
					ISecureRandom::CHAR_HUMAN_READABLE
				);

				// Disable read marker for federated users
				$readPrivacy = Participant::PRIVACY_PRIVATE;
			}

			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType($participant['actorType']);
			$attendee->setActorId($participant['actorId']);
			if (isset($participant['displayName'])) {
				$attendee->setDisplayName($participant['displayName']);
			}
			if (isset($participant['accessToken'])) {
				$attendee->setAccessToken($participant['accessToken']);
			}
			if (isset($participant['remoteId'])) {
				$attendee->setRemoteId($participant['remoteId']);
			}
			if (isset($participant['phoneNumber'])) {
				$attendee->setPhoneNumber($participant['phoneNumber']);
			}
			if (isset($participant['invitedCloudId'])) {
				$attendee->setInvitedCloudId($participant['invitedCloudId']);
			}
			$attendee->setParticipantType($participant['participantType'] ?? Participant::USER);
			$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
			$attendee->setLastReadMessage($participant['lastReadMessage'] ?? $lastMessage);
			$attendee->setReadPrivacy($readPrivacy);
			$attendees[] = $attendee;
		}

		$event = new BeforeAttendeesAddedEvent($room, $attendees);
		$this->dispatcher->dispatchTyped($event);

		$setFederationFlagAlready = $room->hasFederatedParticipants() & Room::HAS_FEDERATION_TALKv1;
		foreach ($attendees as $attendee) {
			try {
				$this->attendeeMapper->insert($attendee);

				if ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
					$response = $this->backendNotifier->sendRemoteShare((string)$attendee->getId(), $attendee->getAccessToken(), $attendee->getActorId(), $addedBy, 'user', $room, $this->getHighestPermissionAttendee($room));
					if (!$response) {
						$this->attendeeMapper->delete($attendee);
						throw new CannotReachRemoteException();
					}

					// Update the display name and the cloud ID based on the server's response
					if (isset($response['displayName']) && $response['displayName'] !== '') {
						$attendee->setDisplayName($response['displayName']);
					}
					if (isset($response['cloudId']) && $response['cloudId'] !== '') {
						$attendee->setActorId($response['cloudId']);
					}

					$this->attendeeMapper->update($attendee);

					if (!$setFederationFlagAlready) {
						$flag = $room->hasFederatedParticipants() | Room::HAS_FEDERATION_TALKv1;

						/** @var RoomService $roomService */
						$roomService = Server::get(RoomService::class);
						$roomService->setHasFederation($room, $flag);

						$setFederationFlagAlready = true;
					}
				}
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}

		$event = new AttendeesAddedEvent($room, $attendees, true);
		$this->dispatcher->dispatchTyped($event);

		$lastMessage = $event->getLastMessage();
		if ($lastMessage instanceof IComment) {
			$this->updateRoomLastMessage($room, $lastMessage);
		}

		return $attendees;
	}

	protected function updateRoomLastMessage(Room $room, IComment $message): void {
		/** @var RoomService $roomService */
		$roomService = Server::get(RoomService::class);
		$roomService->setLastMessage($room, $message);

		$lastMessageCache = $this->cacheFactory->createDistributed(CachePrefix::CHAT_LAST_MESSAGE_ID);
		$lastMessageCache->remove($room->getToken());
		$unreadCountCache = $this->cacheFactory->createDistributed(CachePrefix::CHAT_UNREAD_COUNT);
		$unreadCountCache->clear($room->getId() . '-');

		$event = new SystemMessagesMultipleSentEvent($room, $message);
		$this->dispatcher->dispatchTyped($event);
	}

	public function getHighestPermissionAttendee(Room $room): ?Attendee {
		try {
			$roomOwners = $this->attendeeMapper->getActorsByParticipantTypes($room->getId(), [Participant::OWNER]);
			if (!empty($roomOwners)) {
				foreach ($roomOwners as $owner) {
					if ($owner->getActorType() === Attendee::ACTOR_USERS) {
						return $owner;
					}
				}
			}

			$roomModerators = $this->attendeeMapper->getActorsByParticipantTypes($room->getId(), [Participant::MODERATOR]);
			if (!empty($roomModerators)) {
				foreach ($roomModerators as $moderator) {
					if ($moderator->getActorType() === Attendee::ACTOR_USERS) {
						return $moderator;
					}
				}
			}
		} catch (Exception $e) {
			$this->logger->error('Error while trying to get owner or moderator in room ' . $room->getToken(), ['exception' => $e]);
		}
		return null;
	}

	/**
	 * @param Room $room
	 * @param IGroup $group
	 * @param Participant[] &$existingParticipants
	 */
	public function addGroup(Room $room, IGroup $group, array &$existingParticipants = []): void {
		$usersInGroup = $group->getUsers();

		if (empty($existingParticipants)) {
			$existingParticipants = $this->getParticipantsForRoom($room);
		}

		$banService = Server::get(BanService::class);
		$bannedUserIds = $banService->getBannedUserIdsForRoom($room->getId());
		$participantsByUserId = [];
		foreach ($existingParticipants as $participant) {
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$participantsByUserId[$participant->getAttendee()->getActorId()] = $participant;
			}
		}

		$newParticipants = [];
		foreach ($usersInGroup as $user) {
			$existingParticipant = $participantsByUserId[$user->getUID()] ?? null;
			if ($existingParticipant instanceof Participant) {
				if ($existingParticipant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
					$this->updateParticipantType($room, $existingParticipant, Participant::USER);
				}

				// Participant is already in the conversation, so skip them.
				continue;
			}

			if (isset($bannedUserIds[$user->getUID()])) {
				$this->logger->warning('User ' . $user->getUID() . ' is banned from conversation ' . $room->getToken() . ' and was skipped while adding group ' . $group->getDisplayName());
				continue;
			}

			$newParticipants[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
		}

		try {
			$this->attendeeMapper->findByActor($room->getId(), Attendee::ACTOR_GROUPS, $group->getGID());
		} catch (DoesNotExistException $e) {
			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType(Attendee::ACTOR_GROUPS);
			$attendee->setActorId($group->getGID());
			$attendee->setDisplayName($group->getDisplayName());
			$attendee->setParticipantType(Participant::USER);
			$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
			$attendee->setReadPrivacy(Participant::PRIVACY_PRIVATE);
			$this->attendeeMapper->insert($attendee);

			$attendeeEvent = new AttendeesAddedEvent($room, [$attendee]);
			$this->dispatcher->dispatchTyped($attendeeEvent);
		}

		$attendees = $this->addUsers($room, $newParticipants, bansAlreadyChecked: true);
		if (!empty($attendees)) {
			$existingParticipants = array_merge(array_map(static fn (Attendee $attendee): Participant => new Participant($room, $attendee, null), $attendees), $existingParticipants);
		}
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 * @return Circle
	 * @throws ParticipantNotFoundException
	 */
	public function getCircle(string $circleId, string $userId): Circle {
		try {
			$circlesManager = Server::get(CirclesManager::class);
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$federatedUser->getLink($circleId);
		} catch (\Exception $e) {
			throw new ParticipantNotFoundException('Circle not found or not a member');
		}

		$circlesManager->startSession($federatedUser);
		try {
			return $circlesManager->getCircle($circleId);
		} catch (\Exception $e) {
		} finally {
			$circlesManager->stopSession();
		}

		throw new ParticipantNotFoundException('Circle not found or not a member');
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 * @return Member[]
	 * @throws ParticipantNotFoundException
	 */
	public function getCircleMembers(string $circleId): array {
		try {
			$circlesManager = Server::get(CirclesManager::class);
		} catch (\Exception) {
			throw new ParticipantNotFoundException('Circle not found');
		}

		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($circleId);
		} catch (\Exception) {
			throw new ParticipantNotFoundException('Circle not found');
		} finally {
			$circlesManager->stopSession();
		}

		$members = $circle->getInheritedMembers();
		return array_filter($members, static function (Member $member) {
			return $member->getUserType() === Member::TYPE_USER;
		});
	}

	/**
	 * @param Room $room
	 * @param Circle $circle
	 * @param Participant[] &$existingParticipants
	 */
	public function addCircle(Room $room, Circle $circle, array &$existingParticipants = []): void {
		$membersInCircle = $circle->getInheritedMembers();

		if (empty($existingParticipants)) {
			$existingParticipants = $this->getParticipantsForRoom($room);
		}

		$banService = Server::get(BanService::class);
		$bannedUserIds = $banService->getBannedUserIdsForRoom($room->getId());
		$participantsByUserId = [];
		foreach ($existingParticipants as $participant) {
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$participantsByUserId[$participant->getAttendee()->getActorId()] = $participant;
			}
		}

		$newParticipants = [];
		foreach ($membersInCircle as $member) {
			/** @var Member $member */
			if ($member->getUserType() !== Member::TYPE_USER || $member->getUserId() === '') {
				// Not a user?
				continue;
			}

			if ($member->getStatus() !== Member::STATUS_INVITED && $member->getStatus() !== Member::STATUS_MEMBER) {
				// Only allow invited and regular members
				continue;
			}

			$user = $this->userManager->get($member->getUserId());
			if (!$user instanceof IUser) {
				continue;
			}

			$existingParticipant = $participantsByUserId[$user->getUID()] ?? null;
			if ($existingParticipant instanceof Participant) {
				if ($existingParticipant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
					$this->updateParticipantType($room, $existingParticipant, Participant::USER);
				}

				// Participant is already in the conversation, so skip them.
				continue;
			}

			if (isset($bannedUserIds[$user->getUID()])) {
				$this->logger->warning('User ' . $user->getUID() . ' is banned from conversation ' . $room->getToken() . ' and was skipped while adding circle ' . $circle->getDisplayName());
				continue;
			}

			$newParticipants[] = [
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
		}

		try {
			$this->attendeeMapper->findByActor($room->getId(), Attendee::ACTOR_CIRCLES, $circle->getSingleId());
		} catch (DoesNotExistException $e) {
			$attendee = new Attendee();
			$attendee->setRoomId($room->getId());
			$attendee->setActorType(Attendee::ACTOR_CIRCLES);
			$attendee->setActorId($circle->getSingleId());
			$attendee->setDisplayName($circle->getDisplayName());
			$attendee->setParticipantType(Participant::USER);
			$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
			$attendee->setReadPrivacy(Participant::PRIVACY_PRIVATE);
			$this->attendeeMapper->insert($attendee);

			$attendeeEvent = new AttendeesAddedEvent($room, [$attendee]);
			$this->dispatcher->dispatchTyped($attendeeEvent);
		}

		$attendees = $this->addUsers($room, $newParticipants, bansAlreadyChecked: true);
		if (!empty($attendees)) {
			$existingParticipants = array_merge(array_map(static fn (Attendee $attendee): Participant => new Participant($room, $attendee, null), $attendees), $existingParticipants);
		}
	}

	public function inviteEmailAddress(Room $room, string $actorId, string $email, ?string $name = null): Participant {
		$lastMessage = 0;
		if ($room->getLastMessage() instanceof IComment) {
			$lastMessage = (int)$room->getLastMessage()->getId();
		}

		$attendee = new Attendee();
		$attendee->setRoomId($room->getId());
		$attendee->setActorType(Attendee::ACTOR_EMAILS);
		$attendee->setActorId($actorId);
		$attendee->setInvitedCloudId($email);
		$attendee->setAccessToken($this->secureRandom->generate(
			FederationManager::TOKEN_LENGTH,
			ISecureRandom::CHAR_HUMAN_READABLE
		));

		if ($name !== null) {
			$attendee->setDisplayName($name);
		}

		if ($room->getSIPEnabled() !== Webinary::SIP_DISABLED
			&& $this->talkConfig->isSIPConfigured()) {
			$attendee->setPin($this->generatePin());
		}

		$attendee->setParticipantType(Participant::GUEST);
		$attendee->setLastReadMessage($lastMessage);
		$this->attendeeMapper->insert($attendee);
		// FIXME handle duplicate invites gracefully

		$attendeeEvent = new AttendeesAddedEvent($room, [$attendee]);
		$this->dispatcher->dispatchTyped($attendeeEvent);

		return new Participant($room, $attendee, null);
	}

	public function generatePinForParticipant(Room $room, Participant $participant): void {
		$attendee = $participant->getAttendee();
		if ($room->getSIPEnabled() !== Webinary::SIP_DISABLED
			&& $this->talkConfig->isSIPConfigured()
			&& ($attendee->getActorType() === Attendee::ACTOR_USERS || $attendee->getActorType() === Attendee::ACTOR_EMAILS)
			&& !$attendee->getPin()) {
			$attendee->setPin($this->generatePin());
			$attendee->setLastAttendeeActivity($this->timeFactory->getTime());
			$this->attendeeMapper->update($attendee);
		}
	}

	public function ensureOneToOneRoomIsFilled(Room $room, ?string $enforceUserId = null): void {
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			return;
		}

		$users = json_decode($room->getName(), true);
		$participants = $this->getParticipantUserIds($room);
		if ($enforceUserId !== null) {
			$missingUsers = !in_array($enforceUserId, $participants) ? [$enforceUserId] : [];
		} else {
			$missingUsers = array_diff($users, $participants);
		}

		foreach ($missingUsers as $userId) {
			$userDisplayName = $this->userManager->getDisplayName($userId);
			if ($userDisplayName !== null) {
				$this->addUsers($room, [[
					'actorType' => Attendee::ACTOR_USERS,
					'actorId' => $userId,
					'displayName' => $userDisplayName,
					'participantType' => Participant::OWNER,
				]]);
			}
		}
	}

	public function leaveRoomAsSession(Room $room, Participant $participant, bool $duplicatedParticipant = false): void {
		$event = new BeforeSessionLeftRoomEvent($room, $participant, $duplicatedParticipant);
		$this->dispatcher->dispatchTyped($event);

		$session = $participant->getSession();
		if ($session instanceof Session) {
			$isInCall = $session->getInCall() !== Participant::FLAG_DISCONNECTED;
			if ($isInCall) {
				$this->changeInCall($room, $participant, Participant::FLAG_DISCONNECTED);
			}

			$this->sessionMapper->delete($session);
		} else {
			$this->sessionMapper->deleteByAttendeeId($participant->getAttendee()->getId());
		}

		$event = new SessionLeftRoomEvent($room, $participant, $duplicatedParticipant);
		$this->dispatcher->dispatchTyped($event);

		if ($participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED
			&& empty($this->sessionMapper->findByAttendeeId($participant->getAttendee()->getId()))) {
			$user = $this->userManager->get($participant->getAttendee()->getActorId());

			$this->removeUser($room, $user, AAttendeeRemovedEvent::REASON_LEFT);
		} else {
			$this->resetCallStateWhenNeeded($room);
		}
	}

	/**
	 * @psalm-param AAttendeeRemovedEvent::REASON_* $reason
	 */
	public function removeAttendee(Room $room, Participant $participant, string $reason, bool $attendeeEventIsTriggeredAlready = false): void {
		if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_FEDERATED_USERS && $reason !== AAttendeeRemovedEvent::REASON_LEFT) {
			$attendee = $participant->getAttendee();
			$cloudId = $this->cloudIdManager->resolveCloudId($attendee->getActorId());

			$this->backendNotifier->sendRemoteUnShare(
				$cloudId->getRemote(),
				$attendee->getId(),
				$attendee->getAccessToken(),
			);
		}

		$sessions = $this->sessionService->getAllSessionsForAttendee($participant->getAttendee());

		if ($room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			/** @var BreakoutRoomService $breakoutRoomService */
			$breakoutRoomService = Server::get(BreakoutRoomService::class);
			$breakoutRoomService->removeAttendeeFromBreakoutRoom(
				$room,
				$participant->getAttendee()->getActorType(),
				$participant->getAttendee()->getActorId(),
				false
			);
		}

		$event = new BeforeAttendeeRemovedEvent($room, $participant->getAttendee(), $reason, $sessions);
		$this->dispatcher->dispatchTyped($event);

		$this->sessionMapper->deleteByAttendeeId($participant->getAttendee()->getId());
		$this->attendeeMapper->delete($participant->getAttendee());

		$event = new AttendeeRemovedEvent($room, $participant->getAttendee(), $reason, $sessions);
		$this->dispatcher->dispatchTyped($event);

		if (!$attendeeEventIsTriggeredAlready) {
			$attendeeEvent = new AttendeesRemovedEvent($room, [$participant->getAttendee()]);
			$this->dispatcher->dispatchTyped($attendeeEvent);
		}

		if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_GROUPS) {
			$this->removeGroupMembers($room, $participant, $reason);
		} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_CIRCLES) {
			$this->removeCircleMembers($room, $participant, $reason);
		}

		$this->resetCallStateWhenNeeded($room);
	}

	/**
	 * @return Attendee[]
	 */
	public function getActorsByType(Room $room, string $actorType): array {
		return $this->attendeeMapper->getActorsByType($room->getId(), $actorType);
	}

	public function removeGroupMembers(Room $room, Participant $removedGroupParticipant, string $reason): void {
		$removedGroup = $this->groupManager->get($removedGroupParticipant->getAttendee()->getActorId());
		if (!$removedGroup instanceof IGroup) {
			return;
		}

		$users = $this->membershipService->getUsersWithoutOtherMemberships($room, $removedGroup->getUsers());
		$attendees = [];
		foreach ($users as $user) {
			try {
				$participant = $this->getParticipant($room, $user->getUID());
				$participantType = $participant->getAttendee()->getParticipantType();

				if ($participantType === Participant::USER) {
					// Only remove normal users, not moderators/admins
					$this->removeAttendee($room, $participant, $reason, true);
					$attendees[] = $participant->getAttendee();
				}
			} catch (ParticipantNotFoundException $e) {
			}
		}

		$attendeeEvent = new AttendeesRemovedEvent($room, $attendees);
		$this->dispatcher->dispatchTyped($attendeeEvent);
	}

	public function removeCircleMembers(Room $room, Participant $removedCircleParticipant, string $reason): void {
		try {
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSuperSession();
			$circle = $circlesManager->getCircle($removedCircleParticipant->getAttendee()->getActorId());
			$circlesManager->stopSession();
		} catch (\Exception $e) {
			// Circles not enabled
			return;
		}

		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($removedCircleParticipant->getAttendee()->getActorId());
			$circlesManager->stopSession();
		} catch (\Exception $e) {
			$circlesManager->stopSession();
			return;
		}

		$membersInCircle = $circle->getInheritedMembers();
		$users = [];
		foreach ($membersInCircle as $member) {
			/** @var Member $member */
			if ($member->getUserType() !== Member::TYPE_USER || $member->getUserId() === '') {
				// Not a user?
				continue;
			}

			if ($member->getStatus() !== Member::STATUS_INVITED && $member->getStatus() !== Member::STATUS_MEMBER) {
				// Only allow invited and regular members
				continue;
			}

			$users[] = $this->userManager->get($member->getUserId());
		}

		$users = array_filter($users);

		if (empty($users)) {
			return;
		}

		$users = $this->membershipService->getUsersWithoutOtherMemberships($room, $users);
		$attendees = [];
		foreach ($users as $user) {
			try {
				$participant = $this->getParticipant($room, $user->getUID());
				$participantType = $participant->getAttendee()->getParticipantType();

				if ($participantType === Participant::USER) {
					// Only remove normal users, not moderators/admins
					$this->removeAttendee($room, $participant, $reason, true);
					$attendees[] = $participant->getAttendee();
				}
			} catch (ParticipantNotFoundException $e) {
			}
		}

		$attendeeEvent = new AttendeesRemovedEvent($room, $attendees);
		$this->dispatcher->dispatchTyped($attendeeEvent);
	}

	/**
	 * @psalm-param AAttendeeRemovedEvent::REASON_* $reason
	 */
	public function removeUser(Room $room, IUser $user, string $reason): void {
		try {
			$participant = $this->getParticipant($room, $user->getUID(), false);
		} catch (ParticipantNotFoundException $e) {
			return;
		}

		$attendee = $participant->getAttendee();
		$sessions = $this->sessionService->getAllSessionsForAttendee($attendee);

		if ($reason !== AAttendeeRemovedEvent::REASON_REMOVED_ALL && $room->getBreakoutRoomMode() !== BreakoutRoom::MODE_NOT_CONFIGURED) {
			/** @var BreakoutRoomService $breakoutRoomService */
			$breakoutRoomService = Server::get(BreakoutRoomService::class);
			$breakoutRoomService->removeAttendeeFromBreakoutRoom(
				$room,
				$attendee->getActorType(),
				$attendee->getActorId(),
				false
			);
		} elseif ($reason === AAttendeeRemovedEvent::REASON_REMOVED_ALL) {
			$reason = AAttendeeRemovedEvent::REASON_REMOVED;
		}

		$attendeeEvent = new BeforeAttendeeRemovedEvent($room, $attendee, $reason, $sessions);
		$this->dispatcher->dispatchTyped($attendeeEvent);

		foreach ($sessions as $session) {
			$this->sessionMapper->delete($session);
		}

		$this->attendeeMapper->delete($attendee);

		$attendeeEvent = new AttendeeRemovedEvent($room, $attendee, $reason, $sessions);
		$this->dispatcher->dispatchTyped($attendeeEvent);

		$attendeeEvent = new AttendeesRemovedEvent($room, [$attendee]);
		$this->dispatcher->dispatchTyped($attendeeEvent);

		$this->resetCallStateWhenNeeded($room);
	}

	public function cleanGuestParticipants(Room $room): void {
		$event = new BeforeGuestsCleanedUpEvent($room);
		$this->dispatcher->dispatchTyped($event);

		$query = $this->connection->getQueryBuilder();
		$query->selectAlias('s.id', 's_id')
			->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('s.attendee_id', 'a.id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_GUESTS)))
			->andWhere($query->expr()->lte('s.last_ping', $query->createNamedParameter($this->timeFactory->getTime() - Session::SESSION_TIMEOUT_KILL, IQueryBuilder::PARAM_INT)));

		$sessionTableIds = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$sessionTableIds[] = (int)$row['s_id'];
		}
		$result->closeCursor();

		$this->sessionService->deleteSessionsById($sessionTableIds);

		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq('s.attendee_id', 'a.id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_GUESTS)))
			->andWhere($query->expr()->isNull('s.id'));

		$attendeeIds = [];
		$attendees = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			if ($row['display_name'] !== '' && $row['display_name'] !== null) {
				// Keep guests with a non-empty display name, so we can still
				// render the guest display name on chat messages.
				continue;
			}

			if ((int)$row['participant_type'] !== Participant::GUEST
				|| ((int)$row['permissions'] !== Attendee::PERMISSIONS_DEFAULT
					&& (int)$row['permissions'] !== Attendee::PERMISSIONS_CUSTOM)) {
				// Keep guests with non-default permissions in case they just reconnect
				continue;
			}

			$attendeeIds[] = (int)$row['a_id'];
			$attendees[] = $this->attendeeMapper->createAttendeeFromRow($row);
		}
		$result->closeCursor();

		if (empty($attendeeIds)) {
			return;
		}

		$this->attendeeMapper->deleteByIds($attendeeIds);

		$attendeeEvent = new AttendeesRemovedEvent($room, $attendees);
		$this->dispatcher->dispatchTyped($attendeeEvent);

		$event = new GuestsCleanedUpEvent($room);
		$this->dispatcher->dispatchTyped($event);

		$this->resetCallStateWhenNeeded($room);
	}

	public function endCallForEveryone(Room $room, ?Participant $moderator): void {
		$oldActiveSince = $room->getActiveSince();
		$event = new BeforeCallEndedForEveryoneEvent($room, $moderator, $oldActiveSince);
		$this->dispatcher->dispatchTyped($event);

		$participants = $this->getParticipantsInCall($room);
		$changedSessionIds = [];
		$changedUserIds = [];

		// kick all participants out of the call
		foreach ($participants as $participant) {
			$changedSessionIds[] = $participant->getSession()->getSessionId();
			if ($participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
				$changedUserIds[] = $participant->getAttendee()->getActorId();
			}
			$this->changeInCall($room, $participant, Participant::FLAG_DISCONNECTED, true);
		}

		$this->sessionMapper->resetInCallByIds($changedSessionIds);

		$event = new CallEndedForEveryoneEvent($room, $moderator, $oldActiveSince, $changedSessionIds, $changedUserIds);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @psalm-param int-mask-of<Participant::FLAG_*> $flags
	 * @throws \InvalidArgumentException
	 */
	public function changeInCall(Room $room, Participant $participant, int $flags, bool $endCallForEveryone = false, bool $silent = false, int $lastJoinedCall = 0): void {
		if ($room->getType() === Room::TYPE_CHANGELOG
			|| $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER
			|| $room->getType() === Room::TYPE_NOTE_TO_SELF) {
			throw new \InvalidArgumentException('type');
		}

		$session = $participant->getSession();
		if (!$session instanceof Session) {
			throw new \InvalidArgumentException('session');
		}

		$permissions = $participant->getPermissions();
		if (!($permissions & Attendee::PERMISSIONS_PUBLISH_AUDIO)) {
			$flags &= ~Participant::FLAG_WITH_AUDIO;
		}
		if (!($permissions & Attendee::PERMISSIONS_PUBLISH_VIDEO)) {
			$flags &= ~Participant::FLAG_WITH_VIDEO;
		}

		$oldFlags = $session->getInCall();
		$details = [];

		if ($flags !== Participant::FLAG_DISCONNECTED && $silent) {
			$details = [AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT => $silent];
		} elseif ($flags === Participant::FLAG_DISCONNECTED && $endCallForEveryone) {
			$details = [AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE => $endCallForEveryone];
		}

		$event = new BeforeParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_IN_CALL, $flags, $oldFlags, $details);
		$this->dispatcher->dispatchTyped($event);

		$session->setInCall($flags);
		if (!$endCallForEveryone) {
			$this->sessionMapper->update($session);
		}

		$attendee = $participant->getAttendee();
		if ($flags !== Participant::FLAG_DISCONNECTED) {
			$attendee->setLastJoinedCall($lastJoinedCall ?: $this->timeFactory->getTime());
			$this->attendeeMapper->update($attendee);
		} elseif ($attendee->getActorType() === Attendee::ACTOR_PHONES) {
			$attendee->setCallId('');
			$this->attendeeMapper->update($attendee);
		}

		$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_IN_CALL, $flags, $oldFlags, $details);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws DoesNotExistException
	 */
	public function sendCallNotificationForAttendee(Room $room, Participant $currentParticipant, int $targetAttendeeId): void {
		$attendee = $this->attendeeMapper->getById($targetAttendeeId);
		if ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			$target = new Participant($room, $attendee, null);
			$event = new ParticipantModifiedEvent($room, $target, AParticipantModifiedEvent::PROPERTY_RESEND_CALL, 1);
			$this->dispatcher->dispatchTyped($event);
			return;
		}
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS) {
			throw new \InvalidArgumentException('actor-type');
		}

		if ($attendee->getRoomId() !== $room->getId()) {
			throw new DoesNotExistException('Room mismatch');
		}

		$userStatus = $this->userStatusManager->getUserStatuses([$attendee->getActorId()]);
		if (isset($userStatus[$attendee->getActorId()]) && $userStatus[$attendee->getActorId()]->getStatus() === IUserStatus::DND) {
			throw new \InvalidArgumentException('status');
		}

		$sessions = $this->sessionMapper->findByAttendeeId($targetAttendeeId);
		foreach ($sessions as $session) {
			if ($session->getInCall() !== Participant::FLAG_DISCONNECTED) {
				return;
			}
		}

		$target = new Participant($room, $attendee, null);
		$event = new CallNotificationSendEvent($room, $currentParticipant, $target);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws DialOutFailedException
	 * @throws ParticipantNotFoundException
	 */
	public function startDialOutRequest(SIPDialOutService $dialOutService, Room $room, int $targetAttendeeId, string|bool $callerNumber): void {
		try {
			$attendee = $this->attendeeMapper->getById($targetAttendeeId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception) {
			throw new ParticipantNotFoundException();
		}

		if ($attendee->getRoomId() !== $room->getId()) {
			throw new ParticipantNotFoundException();
		}

		if ($attendee->getActorType() !== Attendee::ACTOR_PHONES) {
			throw new ParticipantNotFoundException();
		}

		$dialOutResponse = $dialOutService->sendDialOutRequestToBackend($room, $attendee, $callerNumber);

		if (!$dialOutResponse) {
			throw new \InvalidArgumentException('backend');
		}

		if ($dialOutResponse->dialOut->error?->code) {
			throw new DialOutFailedException(
				$dialOutResponse->dialOut->error->code,
				$dialOutResponse->dialOut->error->message,
			);
		}

		$attendee->setCallId($dialOutResponse->dialOut->callId);
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws ParticipantNotFoundException
	 */
	public function resetDialOutRequest(Room $room, int $targetAttendeeId, string $callId): void {
		try {
			$attendee = $this->attendeeMapper->getById($targetAttendeeId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception) {
			throw new ParticipantNotFoundException();
		}

		if ($attendee->getRoomId() !== $room->getId()) {
			throw new ParticipantNotFoundException();
		}

		if ($attendee->getActorType() !== Attendee::ACTOR_PHONES) {
			throw new ParticipantNotFoundException();
		}

		if ($callId === $attendee->getCallId()) {
			$attendee->setCallId(null);
			$this->attendeeMapper->update($attendee);
		} else {
			throw new \InvalidArgumentException('callId');
		}

	}

	public function updateCallFlags(Room $room, Participant $participant, int $flags): void {
		$session = $participant->getSession();
		if (!$session instanceof Session) {
			return;
		}

		if (!($session->getInCall() & Participant::FLAG_IN_CALL)) {
			throw new \Exception('Participant not in call');
		}

		if (!($flags & Participant::FLAG_IN_CALL)) {
			throw new \InvalidArgumentException('Invalid flags');
		}

		$permissions = $participant->getPermissions();
		if (!($permissions & Attendee::PERMISSIONS_PUBLISH_AUDIO)) {
			$flags &= ~Participant::FLAG_WITH_AUDIO;
		}
		if (!($permissions & Attendee::PERMISSIONS_PUBLISH_VIDEO)) {
			$flags &= ~Participant::FLAG_WITH_VIDEO;
		}

		$oldFlags = $session->getInCall();
		$event = new BeforeParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_IN_CALL, $flags, $oldFlags);
		$this->dispatcher->dispatchTyped($event);

		$session->setInCall($flags);
		$this->sessionMapper->update($session);

		// FIXME Missing potential update of call flags on room level

		$event = new ParticipantModifiedEvent($room, $participant, AParticipantModifiedEvent::PROPERTY_IN_CALL, $flags, $oldFlags);
		$this->dispatcher->dispatchTyped($event);
	}

	/**
	 * @param string[] $actorIds
	 * @param string[] $actorsDirectlyMentioned
	 */
	public function markUsersAsMentioned(Room $room, string $actorType, array $actorIds, int $messageId, array $actorsDirectlyMentioned): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('last_mention_message', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('room_id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->in('actor_id', $update->createNamedParameter($actorIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($update->expr()->lt('last_mention_message', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));
		$update->executeStatement();

		if (!empty($actorsDirectlyMentioned)) {
			$update = $this->connection->getQueryBuilder();
			$update->update('talk_attendees')
				->set('last_mention_direct', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('room_id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
				->andWhere($update->expr()->in('actor_id', $update->createNamedParameter($actorsDirectlyMentioned, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($update->expr()->lt('last_mention_direct', $update->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		}
	}

	public function resetChatDetails(Room $room): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('last_read_message', $update->createNamedParameter(ChatManager::UNREAD_FIRST_MESSAGE, IQueryBuilder::PARAM_INT))
			->set('last_mention_message', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('last_mention_direct', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('last_attendee_activity', $update->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('room_id', $update->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$update->executeStatement();
	}

	public function updateReadPrivacyForActor(string $actorType, string $actorId, int $readPrivacy): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('read_privacy', $update->createNamedParameter($readPrivacy, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();
	}

	public function updateDisplayNameForActor(string $actorType, string $actorId, string $displayName): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			->set('display_name', $update->createNamedParameter($displayName))
			->where($update->expr()->eq('actor_type', $update->createNamedParameter($actorType)))
			->andWhere($update->expr()->eq('actor_id', $update->createNamedParameter($actorId)));
		$update->executeStatement();
	}

	public function getLastCommonReadChatMessage(Room $room): int {
		$query = $this->connection->getQueryBuilder();
		$query->selectAlias($query->func()->min('last_read_message'), 'last_common_read_message')
			->from('talk_attendees')
			->where($query->expr()->eq('actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('read_privacy', $query->createNamedParameter(Participant::PRIVACY_PUBLIC, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['last_common_read_message'] ?? 0);
	}

	/**
	 * @param int[] $roomIds
	 * @return array A map of roomId => "last common read message id"
	 * @psalm-return  array<int, int>
	 */
	public function getLastCommonReadChatMessageForMultipleRooms(array $roomIds): array {
		$commonReads = array_fill_keys($roomIds, 0);

		$query = $this->connection->getQueryBuilder();
		$query->select('room_id')
			->selectAlias($query->func()->min('last_read_message'), 'last_common_read_message')
			->from('talk_attendees')
			->where($query->expr()->eq('actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->in('room_id', $query->createParameter('roomIds')))
			->andWhere($query->expr()->eq('read_privacy', $query->createNamedParameter(Participant::PRIVACY_PUBLIC, IQueryBuilder::PARAM_INT)))
			->groupBy('room_id');

		$chunks = array_chunk($roomIds, 1000);
		foreach ($chunks as $chunk) {
			$query->setParameter('roomIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $query->executeQuery();
			while ($row = $result->fetch()) {
				$commonReads[(int)$row['room_id']] = (int)$row['last_common_read_message'];
			}
			$result->closeCursor();
		}

		return $commonReads;
	}

	/**
	 * @param Room $room
	 * @return Participant[]
	 */
	public function getParticipantsForRoom(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq('a.id', 's.attendee_id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->orX(
				$query->expr()->neq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_GUESTS)),
				$query->expr()->isNotNull('s.id'),
			));

		return $this->getParticipantsFromQuery($query, $room);
	}

	/**
	 * Get all sessions and attendees without a session for the room
	 *
	 * This will return multiple items for the same attendee if the attendee
	 * has multiple sessions in the room.
	 *
	 * @param Room $room
	 * @return Participant[]
	 */
	public function getSessionsAndParticipantsForRoom(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_attendees', 'a')
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));

		return $this->getParticipantsFromQuery($query, $room);
	}

	/**
	 * Get all sessions and attendees without a session for the room
	 *
	 * This will return multiple items for the same attendee if the attendee
	 * has multiple sessions in the room.
	 *
	 * @param Room[] $rooms
	 * @return Participant[]
	 */
	public function getSessionsAndParticipantsForRooms(array $rooms): array {
		$roomIds = array_map(static fn (Room $room) => $room->getId(), $rooms);
		$map = array_combine($roomIds, $rooms);

		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_attendees', 'a')
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->in('a.room_id', $query->createNamedParameter($roomIds, IQueryBuilder::PARAM_INT_ARRAY)));

		return $this->getParticipantsForRoomsFromQuery($query, $map);
	}

	/**
	 * @param Room $room
	 * @param int $maxAge
	 * @return Participant[]
	 */
	public function getParticipantsForAllSessions(Room $room, int $maxAge = 0): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_sessions', 's')
			->leftJoin(
				's', 'talk_attendees', 'a',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->isNotNull('a.id'));

		if ($maxAge > 0) {
			$query->andWhere($query->expr()->gt('s.last_ping', $query->createNamedParameter($maxAge, IQueryBuilder::PARAM_INT)));
		}

		return $this->getParticipantsFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param int $maxAge
	 * @return Participant[]
	 */
	public function getParticipantsInCall(Room $room, int $maxAge = 0): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_sessions', 's')
			->leftJoin(
				's', 'talk_attendees', 'a',
				$query->expr()->eq('s.attendee_id', 'a.id')
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)));

		if ($maxAge > 0) {
			$query->andWhere($query->expr()->gte('s.last_ping', $query->createNamedParameter($maxAge, IQueryBuilder::PARAM_INT)));
		}

		return $this->getParticipantsFromQuery($query, $room);
	}

	/**
	 * Do not try to modernize this into using the Room, Participant or other objects.
	 * This function is called by {@see CallNotificationController::state}
	 * and mobile as well as desktop clients are basically ddos-ing it, to check
	 * if the call notification / call screen should be removed.
	 * @return CallNotificationController::CASE_*
	 */
	public function checkIfUserIsMissingCall(string $token, string $userId): int {
		$query = $this->connection->getQueryBuilder();
		$query->select('r.active_since', 'a.last_joined_call', 's.in_call')
			->from('talk_rooms', 'r')
			->innerJoin(
				'r', 'talk_attendees', 'a',
				$query->expr()->eq('r.id', 'a.room_id')
			)
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->andX(
					$query->expr()->eq('s.attendee_id', 'a.id'),
					$query->expr()->neq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT),
				)
			)
			->where($query->expr()->eq('r.token', $query->createNamedParameter($token)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return CallNotificationController::CASE_ROOM_NOT_FOUND;
		}

		if ($row['active_since'] === null) {
			return CallNotificationController::CASE_MISSED_CALL;
		}

		try {
			$activeSince = new \DateTime($row['active_since']);
		} catch (\Throwable) {
			return CallNotificationController::CASE_MISSED_CALL;
		}

		if ($row['in_call'] !== null) {
			return CallNotificationController::CASE_PARTICIPANT_JOINED;
		}

		if ($activeSince->getTimestamp() >= $row['last_joined_call']) {
			return CallNotificationController::CASE_STILL_CURRENT;
		}

		// The participant had joined the call, but left again.
		// In this case we should not ring any more, but clients stop
		// pinging the endpoint 45s after receiving the push anyway.
		// However, it is also possible that the participant was ringed
		// again by a moderator after they had joined the call before.
		// So if a client pings the endpoint after 45s initial ringing
		// + 15 seconds for worst case push notification delay, we will
		// again tell them to show the call notification.
		if (($activeSince->getTimestamp() + 45 + 15) < $this->timeFactory->getTime()) {
			return CallNotificationController::CASE_STILL_CURRENT;
		}
		return CallNotificationController::CASE_PARTICIPANT_JOINED;
	}

	/**
	 * @return Participant[]
	 */
	public function getParticipantsJoinedCurrentCall(Room $room, int $maxAge): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gte('a.last_joined_call', $query->createNamedParameter($maxAge, IQueryBuilder::PARAM_INT)))
			->orderBy('a.id', 'ASC');

		return $this->getParticipantsFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param int $notificationLevel
	 * @return Participant[]
	 */
	public function getParticipantsByNotificationLevel(Room $room, int $notificationLevel): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_attendees', 'a')
			// Currently we only care if the user has an active session at all, so we can select any
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->andX(
					$query->expr()->eq('s.attendee_id', 'a.id'),
					$query->expr()->eq('s.state', $query->createNamedParameter(Session::STATE_ACTIVE, IQueryBuilder::PARAM_INT))
				)
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.notification_level', $query->createNamedParameter($notificationLevel, IQueryBuilder::PARAM_INT)));

		$participants = $this->getParticipantsFromQuery($query, $room);

		$uniqueAttendees = [];
		foreach ($participants as $participant) {
			$uniqueAttendees[$participant->getAttendee()->getId()] = $participant;
		}

		return array_values($uniqueAttendees);
	}

	/**
	 * @param Room $room
	 * @param list<int> $attendeeIds
	 * @return Participant[]
	 */
	public function getParticipantsByAttendeeId(Room $room, array $attendeeIds): array {
		$query = $this->connection->getQueryBuilder();

		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_attendees', 'a')
			// Currently we only care if the user has an active session at all, so we can select any
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->andX(
					$query->expr()->eq('s.attendee_id', 'a.id'),
					$query->expr()->eq('s.state', $query->createNamedParameter(Session::STATE_ACTIVE, IQueryBuilder::PARAM_INT))
				)
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('a.id', $query->createNamedParameter($attendeeIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$participants = $this->getParticipantsFromQuery($query, $room);

		$uniqueAttendees = [];
		foreach ($participants as $participant) {
			$uniqueAttendees[$participant->getAttendee()->getId()] = $participant;
		}

		return array_values($uniqueAttendees);
	}

	/**
	 * @return Participant[]
	 */
	public function getParticipantsByActorType(Room $room, string $actorType): array {
		$attendees = $this->getActorsByType($room, $actorType);
		return array_map(static fn (Attendee $attendee) => new Participant($room, $attendee, null), $attendees);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param Room $room
	 * @return Participant[]
	 */
	protected function getParticipantsFromQuery(IQueryBuilder $query, Room $room): array {
		return $this->getParticipantsForRoomsFromQuery($query, [$room->getId() => $room]);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param Room[] $rooms Room ID => Room object
	 * @psalm-param array<int, Room> $rooms
	 * @return Participant[]
	 */
	protected function getParticipantsForRoomsFromQuery(IQueryBuilder $query, array $rooms): array {
		$participants = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$room = $rooms[(int)$row['room_id']] ?? null;
			if ($room === null) {
				continue;
			}

			$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
			if (isset($row['s_id'])) {
				$session = $this->sessionMapper->createSessionFromRow($row);
			} else {
				$session = null;
			}

			$participants[] = new Participant($room, $attendee, $session);
		}
		$result->closeCursor();

		return $participants;
	}

	/**
	 * @throws ParticipantNotFoundException
	 */
	protected function getParticipantFromQuery(IQueryBuilder $query, Room $room): Participant {
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			throw new ParticipantNotFoundException('User is not a participant');
		}

		$attendee = $this->attendeeMapper->createAttendeeFromRow($row);
		if (isset($row['s_id'])) {
			$session = $this->sessionMapper->createSessionFromRow($row);
		} else {
			$session = null;
		}

		return new Participant($room, $attendee, $session);
	}

	/**
	 * @return string[]
	 */
	public function getParticipantUserIds(Room $room, ?\DateTime $maxLastJoined = null): array {
		return $this->getParticipantActorIdsByActorType($room, [Attendee::ACTOR_USERS], $maxLastJoined);
	}

	/**
	 * @return string[]
	 */
	public function getParticipantUserIdsAndFederatedUserCloudIds(Room $room, ?\DateTime $maxLastJoined = null): array {
		return $this->getParticipantActorIdsByActorType($room, [Attendee::ACTOR_USERS, Attendee::ACTOR_FEDERATED_USERS], $maxLastJoined);
	}

	/**
	 * @return string[]
	 */
	public function getParticipantActorIdsByActorType(Room $room, array $actorTypes, ?\DateTime $maxLastJoined = null): array {
		$maxLastJoinedTimestamp = null;
		if ($maxLastJoined !== null) {
			$maxLastJoinedTimestamp = $maxLastJoined->getTimestamp();
		}
		$attendees = $this->attendeeMapper->getActorsByTypes($room->getId(), $actorTypes, $maxLastJoinedTimestamp);

		return array_map(static function (Attendee $attendee) {
			return $attendee->getActorId();
		}, $attendees);
	}

	public function getActorsCountByType(Room $room, string $actorType, int $maxLastJoined): int {
		return $this->attendeeMapper->getActorsCountByType($room->getId(), $actorType, $maxLastJoined);
	}

	/**
	 * @param Room $room
	 * @return array<string, bool> (userId => isImportant)
	 */
	public function getParticipantUsersForCallNotifications(Room $room): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('a.actor_id', 'a.important')
			->from('talk_attendees', 'a')
			->leftJoin(
				'a', 'talk_sessions', 's',
				$query->expr()->andX(
					$query->expr()->eq('s.attendee_id', 'a.id'),
					$query->expr()->neq('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)),
					$query->expr()->gte('s.last_ping', $query->createNamedParameter($this->timeFactory->getTime() - Session::SESSION_TIMEOUT, IQueryBuilder::PARAM_INT)),
				)
			)
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('a.notification_calls', $query->createNamedParameter(Participant::NOTIFY_CALLS_ON)))
			->andWhere($query->expr()->isNull('s.in_call'));

		if ($room->getLobbyState() !== Webinary::LOBBY_NONE) {
			// Filter out non-moderators and users without lobby permissions
			$query->andWhere(
				$query->expr()->orX(
					$query->expr()->in('a.participant_type', $query->createNamedParameter(
						[Participant::MODERATOR, Participant::OWNER],
						IQueryBuilder::PARAM_INT_ARRAY
					)),
					$query->expr()->eq(
						$query->expr()->castColumn(
							$query->expr()->bitwiseAnd(
								'permissions',
								Attendee::PERMISSIONS_LOBBY_IGNORE
							),
							IQueryBuilder::PARAM_INT
						),
						$query->createNamedParameter(Attendee::PERMISSIONS_LOBBY_IGNORE, IQueryBuilder::PARAM_INT)
					)
				)
			);
		}

		$users = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$users[$row['actor_id']] = (bool)$row['important'];
		}
		$result->closeCursor();

		return $users;
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
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool)$row;
	}

	public function cacheParticipant(Room $room, Participant $participant): void {
		$attendee = $participant->getAttendee();

		$this->actorCache[$room->getId()] ??= [];
		$this->actorCache[$room->getId()][$attendee->getActorType()] ??= [];
		$this->actorCache[$room->getId()][$attendee->getActorType()][$attendee->getActorId()] = $participant;
		if ($participant->getSession()) {
			$participantSessionId = $participant->getSession()->getSessionId();
			$this->sessionCache[$room->getId()] ??= [];
			$this->sessionCache[$room->getId()][$participantSessionId] = $participant;
		}
	}

	protected function resetCallStateWhenNeeded(Room $room): void {
		if ($room->getCallFlag() === Participant::FLAG_DISCONNECTED) {
			// No call
			return;
		}

		if ($this->hasActiveSessionsInCall($room)) {
			// Still others there
			return;
		}

		$roomService = Server::get(RoomService::class);
		$roomService->resetActiveSince($room, null);
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
			->andWhere($query->expr()->gte('s.last_ping', $query->createNamedParameter($this->timeFactory->getTime() - Session::SESSION_TIMEOUT, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool)$row;
	}

	protected function generatePin(int $entropy = 7): string {
		$pin = '';
		// Do not allow to start with a '0' as that is a special mode on the phone server
		// Also there are issues with some providers when you enter the same number twice
		// consecutive too fast, so we avoid this as well.
		$lastDigit = '0';
		for ($i = 0; $i < $entropy; $i++) {
			$lastDigit = $this->secureRandom->generate(1,
				str_replace($lastDigit, '', ISecureRandom::CHAR_DIGITS)
			);
			$pin .= $lastDigit;
		}

		return $pin;
	}

	/**
	 * @param Room $room
	 * @param string|null $userId
	 * @param string|null|false $sessionId Set to false if you don't want to load a session (and save resources),
	 *                                     string to try loading a specific session
	 *                                     null to try loading "any"
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipant(Room $room, ?string $userId, $sessionId = null): Participant {
		if (!is_string($userId) || $userId === '') {
			throw new ParticipantNotFoundException('Not a user');
		}

		if (isset($this->actorCache[$room->getId()][Attendee::ACTOR_USERS][$userId])) {
			$participant = $this->actorCache[$room->getId()][Attendee::ACTOR_USERS][$userId];
			if (!$sessionId
				|| ($participant->getSession() instanceof Session
					&& $participant->getSession()->getSessionId() === $sessionId)) {
				return $participant;
			}
		}

		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->where($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);

		if ($sessionId !== false) {
			if ($sessionId !== null) {
				$helper->selectSessionsTable($query);
				$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
					$query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)),
					$query->expr()->eq('a.id', 's.attendee_id')
				));
			} else {
				$helper->selectSessionsTable($query); // FIXME PROBLEM
				$query->leftJoin('a', 'talk_sessions', 's', $query->expr()->eq('a.id', 's.attendee_id'));
			}
		}

		$participant = $this->getParticipantFromQuery($query, $room);

		$this->actorCache[$room->getId()] ??= [];
		$this->actorCache[$room->getId()][Attendee::ACTOR_USERS] ??= [];
		$this->actorCache[$room->getId()][Attendee::ACTOR_USERS][$userId] = $participant;
		if ($participant->getSession()) {
			$participantSessionId = $participant->getSession()->getSessionId();
			$this->sessionCache[$room->getId()] ??= [];
			$this->sessionCache[$room->getId()][$participantSessionId] = $participant;
		}

		return $participant;
	}

	/**
	 * Get a participant with an active session if there is one, otherwise without session
	 *
	 * @param Room $room
	 * @param string $userId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipantWithActiveSession(Room $room, string $userId): Participant {
		if ($userId === '') {
			throw new ParticipantNotFoundException('Not a user');
		}

		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_sessions', 's', $query->expr()->andX(
				$query->expr()->eq('a.id', 's.attendee_id'),
				$query->expr()->eq('s.state', $query->createNamedParameter(Session::STATE_ACTIVE, IQueryBuilder::PARAM_INT)),
				$query->expr()->gte('s.last_ping', $query->createNamedParameter($this->timeFactory->getTime() - Session::SESSION_TIMEOUT, IQueryBuilder::PARAM_INT)),
			))
			->where($query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);


		return $this->getParticipantFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param string|null $sessionId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the user is not a participant
	 */
	public function getParticipantBySession(Room $room, ?string $sessionId): Participant {
		if (!is_string($sessionId) || $sessionId === '' || $sessionId === '0') {
			throw new ParticipantNotFoundException('Not a user');
		}

		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$helper->selectSessionsTable($query);
		$query->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('a.id', 's.attendee_id'))
			->where($query->expr()->eq('s.session_id', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);

		return $this->getParticipantFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param string $pin
	 * @return Participant
	 * @throws ParticipantNotFoundException When the pin is not valid (has no participant assigned)
	 */
	public function getParticipantByPin(Room $room, string $pin): Participant {
		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->where($query->expr()->eq('a.pin', $query->createNamedParameter($pin)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);

		return $this->getParticipantFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param int $attendeeId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the pin is not valid (has no participant assigned)
	 */
	public function getParticipantByAttendeeId(Room $room, int $attendeeId): Participant {
		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->where($query->expr()->eq('a.id', $query->createNamedParameter($attendeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);

		return $this->getParticipantFromQuery($query, $room);
	}

	/**
	 * @param Room $room
	 * @param string $actorType
	 * @param string $actorId
	 * @return Participant
	 * @throws ParticipantNotFoundException When the pin is not valid (has no participant assigned)
	 */
	public function getParticipantByActor(Room $room, string $actorType, string $actorId): Participant {
		if (isset($this->actorCache[$room->getId()][$actorType][$actorId])) {
			return $this->actorCache[$room->getId()][$actorType][$actorId];
		}

		if ($actorType === Attendee::ACTOR_USERS) {
			return $this->getParticipant($room, $actorId, false);
		}

		if ($actorType === Attendee::ACTOR_GUESTS
			&& in_array($actorId, [Attendee::ACTOR_ID_CLI, Attendee::ACTOR_ID_SYSTEM, Attendee::ACTOR_ID_CHANGELOG, Attendee::ACTOR_ID_SAMPLE], true)) {
			$exception = new ParticipantNotFoundException('User is not a participant');
			$this->logger->info('Trying to load hardcoded system guest from attendees table: ' . $actorType . '/' . $actorId);
			throw $exception;
		}

		$query = $this->connection->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectAttendeesTable($query);
		$query->from('talk_attendees', 'a')
			->andWhere($query->expr()->eq('a.actor_type', $query->createNamedParameter($actorType)))
			->andWhere($query->expr()->eq('a.actor_id', $query->createNamedParameter($actorId)))
			->andWhere($query->expr()->eq('a.room_id', $query->createNamedParameter($room->getId())))
			->setMaxResults(1);

		$participant = $this->getParticipantFromQuery($query, $room);
		$this->cacheParticipant($room, $participant);
		return $participant;
	}
}
