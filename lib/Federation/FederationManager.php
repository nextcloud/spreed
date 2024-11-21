<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\FederationRestrictionException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IUser;
use OCP\Notification\IManager;
use SensitiveParameter;

/**
 * Class FederationManager
 *
 * @package OCA\Talk\Federation
 *
 * FederationManager handles incoming federated rooms
 */
class FederationManager {
	public const OCM_RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
	public const TALK_ROOM_RESOURCE = 'talk-room';
	public const TALK_PROTOCOL_NAME = 'nctalk';
	public const NOTIFICATION_SHARE_ACCEPTED = 'SHARE_ACCEPTED';
	public const NOTIFICATION_SHARE_DECLINED = 'SHARE_DECLINED';
	public const NOTIFICATION_SHARE_UNSHARED = 'SHARE_UNSHARED';
	public const NOTIFICATION_PARTICIPANT_MODIFIED = 'PARTICIPANT_MODIFIED';
	public const NOTIFICATION_ROOM_MODIFIED = 'ROOM_MODIFIED';
	public const NOTIFICATION_MESSAGE_POSTED = 'MESSAGE_POSTED';
	public const TOKEN_LENGTH = 64;

	public function __construct(
		private Manager $manager,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private InvitationMapper $invitationMapper,
		private AttendeeMapper $attendeeMapper,
		private BackendNotifier $backendNotifier,
		private IManager $notificationManager,
		private ICloudIdManager $cloudIdManager,
		private RestrictionValidator $restrictionValidator,
	) {
	}

	/**
	 * Check if $sharedBy is allowed to invite $shareWith
	 *
	 * @throws FederationRestrictionException
	 */
	public function isAllowedToInvite(
		IUser $user,
		ICloudId $cloudIdToInvite,
	): void {
		$this->restrictionValidator->isAllowedToInvite($user, $cloudIdToInvite);
	}

	public function addRemoteRoom(
		IUser $user,
		int $remoteAttendeeId,
		int $roomType,
		string $roomName,
		int $roomDefaultPermissions,
		string $remoteToken,
		string $remoteServerUrl,
		#[SensitiveParameter]
		string $sharedSecret,
		string $inviterCloudId,
		string $inviterDisplayName,
		string $localCloudId,
	): Invitation {
		$couldHaveInviteWithOtherCasing = false;
		try {
			$room = $this->manager->getRoomByToken($remoteToken, null, $remoteServerUrl);
			$couldHaveInviteWithOtherCasing = true;
		} catch (RoomNotFoundException) {
			$room = $this->manager->createRemoteRoom($roomType, $roomName, $remoteToken, $remoteServerUrl);
		}

		// Only update the room permissions if there are no participants in the
		// remote room. Otherwise, the room permissions would be up to date
		// already due to the notifications about room permission changes.
		if (!$this->participantService->getNumberOfActors($room)) {
			$this->roomService->setDefaultPermissions($room, $roomDefaultPermissions);
		}

		if ($couldHaveInviteWithOtherCasing) {
			try {
				$invitation = $this->invitationMapper->getInvitationForUserByLocalRoom($room, $user->getUID(), true);
				$invitation->setAccessToken($sharedSecret);
				$invitation->setRemoteAttendeeId($remoteAttendeeId);
				$invitation->setInviterCloudId($inviterCloudId);
				$invitation->setInviterDisplayName($inviterDisplayName);

				if ($invitation->getState() === Invitation::STATE_ACCEPTED) {
					try {
						$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $user->getUID());
						$attendee = $participant->getAttendee();
						$attendee->setAccessToken($sharedSecret);
						$attendee->setRemoteId((string)$remoteAttendeeId);
						$this->attendeeMapper->update($attendee);
					} catch (ParticipantNotFoundException) {
						$invitation->setState(Invitation::STATE_PENDING);
					}
				}
				$this->invitationMapper->update($invitation);

				return $invitation;
			} catch (DoesNotExistException) {
				// Not invited with any casing already, so all good.
			}
		}

		$invitation = new Invitation();
		$invitation->setUserId($user->getUID());
		$invitation->setState(Invitation::STATE_PENDING);
		$invitation->setLocalRoomId($room->getId());
		$invitation->setLocalCloudId($localCloudId);
		$invitation->setAccessToken($sharedSecret);
		$invitation->setRemoteServerUrl($remoteServerUrl);
		$invitation->setRemoteToken($remoteToken);
		$invitation->setRemoteAttendeeId($remoteAttendeeId);
		$invitation->setInviterCloudId($inviterCloudId);
		$invitation->setInviterDisplayName($inviterDisplayName);
		$this->invitationMapper->insert($invitation);

		return $invitation;
	}

	protected function markNotificationProcessed(string $userId, int $shareId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setObject('remote_talk_share', (string)$shareId);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws CannotReachRemoteException
	 */
	public function acceptRemoteRoomShare(IUser $user, int $shareId): Participant {
		try {
			$invitation = $this->invitationMapper->getInvitationById($shareId);
		} catch (DoesNotExistException $e) {
			throw new \InvalidArgumentException('invitation');
		}
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new UnauthorizedException('user');
		}

		if ($invitation->getState() === Invitation::STATE_ACCEPTED) {
			throw new \InvalidArgumentException('state');
		}


		$cloudId = $this->cloudIdManager->getCloudId($user->getUID(), null);

		// Add user to the room
		$room = $this->manager->getRoomById($invitation->getLocalRoomId());
		if (
			!$this->backendNotifier->sendShareAccepted($invitation->getRemoteServerUrl(), $invitation->getRemoteAttendeeId(), $invitation->getAccessToken(), $user->getDisplayName(), $cloudId->getId())
		) {
			throw new CannotReachRemoteException();
		}

		$participant = [
			[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'accessToken' => $invitation->getAccessToken(),
				'remoteId' => $invitation->getRemoteAttendeeId(),
				'invitedCloudId' => $invitation->getLocalCloudId(),
				'lastReadMessage' => $room->getLastMessageId(),
			]
		];
		$attendees = $this->participantService->addUsers($room, $participant, $user);
		/** @var Attendee $attendee */
		$attendee = array_pop($attendees);

		$invitation->setState(Invitation::STATE_ACCEPTED);
		$invitation->setLocalCloudId($cloudId->getId());
		$this->invitationMapper->update($invitation);

		$this->markNotificationProcessed($user->getUID(), $shareId);

		return new Participant($room, $attendee, null);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getRemoteShareById(int $shareId): Invitation {
		return $this->invitationMapper->getInvitationById($shareId);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws UnauthorizedException
	 */
	public function rejectRemoteRoomShare(IUser $user, int $shareId): void {
		try {
			$invitation = $this->invitationMapper->getInvitationById($shareId);
		} catch (DoesNotExistException $e) {
			throw new \InvalidArgumentException('invitation');
		}

		if ($invitation->getUserId() !== $user->getUID()) {
			throw new UnauthorizedException('user');
		}

		if ($invitation->getState() !== Invitation::STATE_PENDING) {
			throw new \InvalidArgumentException('state');
		}

		$this->rejectInvitation($invitation, $user->getUID());
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws UnauthorizedException
	 */
	public function rejectByRemoveSelf(Room $room, string $userId): void {
		try {
			$invitation = $this->invitationMapper->getInvitationForUserByLocalRoom($room, $userId);
		} catch (DoesNotExistException $e) {
			throw new \InvalidArgumentException('invitation');
		}

		$this->rejectInvitation($invitation, $userId);
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws UnauthorizedException
	 */
	protected function rejectInvitation(Invitation $invitation, string $userId): void {
		$this->invitationMapper->delete($invitation);
		$this->markNotificationProcessed($userId, $invitation->getId());

		$this->backendNotifier->sendShareDeclined($invitation->getRemoteServerUrl(), $invitation->getRemoteAttendeeId(), $invitation->getAccessToken());
	}

	/**
	 * @param IUser $user
	 * @return Invitation[]
	 */
	public function getRemoteRoomShares(IUser $user): array {
		return $this->invitationMapper->getInvitationsForUser($user);
	}

	public function getNumberOfPendingInvitationsForUser(IUser $user): int {
		return $this->invitationMapper->countInvitationsForUser($user, Invitation::STATE_PENDING);
	}

	public function getNumberOfInvitations(Room $room): int {
		return $this->invitationMapper->countInvitationsForLocalRoom($room);
	}
}
