<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

namespace OCA\Talk\Federation;

use Exception;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
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
use OCP\AppFramework\Http;
use OCP\DB\Exception as DBException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\HintException;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\Exceptions\ShareNotFound;
use Psr\Log\LoggerInterface;

class CloudFederationProviderTalk implements ICloudFederationProvider {

	public function __construct(
		private IUserManager $userManager,
		private AddressHandler $addressHandler,
		private FederationManager $federationManager,
		private Config $config,
		private INotificationManager $notificationManager,
		private IURLGenerator $urlGenerator,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private AttendeeMapper $attendeeMapper,
		private InvitationMapper $invitationMapper,
		private Manager $manager,
		private ISession $session,
		private IEventDispatcher $dispatcher,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getShareType(): string {
		return 'talk-room';
	}

	/**
	 * @inheritDoc
	 * @throws HintException
	 * @throws DBException
	 */
	public function shareReceived(ICloudFederationShare $share): string {
		if (!$this->config->isFederationEnabled()) {
			$this->logger->debug('Received a federation invite but federation is disabled');
			throw new ProviderCouldNotAddShareException('Server does not support talk federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		if (!in_array($share->getShareType(), $this->getSupportedShareTypes(), true)) {
			$this->logger->debug('Received a federation invite for invalid share type');
			throw new ProviderCouldNotAddShareException('Support for sharing with non-users not implemented yet', '', Http::STATUS_NOT_IMPLEMENTED);
			// TODO: Implement group shares
		}

		$roomType = $share->getProtocol()['roomType'];
		if (!is_numeric($roomType) || !in_array((int) $roomType, $this->validSharedRoomTypes(), true)) {
			$this->logger->debug('Received a federation invite for invalid room type');
			throw new ProviderCouldNotAddShareException('roomType is not a valid number', '', Http::STATUS_BAD_REQUEST);
		}

		$shareSecret = $share->getShareSecret();
		$shareWith = $share->getShareWith();
		$remoteId = $share->getProviderId();
		$roomToken = $share->getResourceName();
		$roomName = $share->getProtocol()['roomName'];
		$roomType = (int) $roomType;
		$sharedBy = $share->getSharedByDisplayName();
		$sharedByFederatedId = $share->getSharedBy();
		$owner = $share->getOwnerDisplayName();
		$ownerFederatedId = $share->getOwner();
		[, $remote] = $this->addressHandler->splitUserRemote($ownerFederatedId);

		// if no explicit information about the person who created the share was send
		// we assume that the share comes from the owner
		if ($sharedByFederatedId === null) {
			$sharedBy = $owner;
			$sharedByFederatedId = $ownerFederatedId;
		}

		if ($remote && $shareSecret && $shareWith && $roomToken && $remoteId && is_string($roomName) && $roomName && $owner) {
			$shareWith = $this->userManager->get($shareWith);
			if ($shareWith === null) {
				$this->logger->debug('Received a federation invite for user that could not be found');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			}

			$shareId = (string) $this->federationManager->addRemoteRoom($shareWith, $remoteId, $roomType, $roomName, $roomToken, $remote, $shareSecret);

			$this->notifyAboutNewShare($shareWith, $shareId, $sharedByFederatedId, $sharedBy, $roomName, $roomToken, $remote);
			return $shareId;
		}

		$this->logger->debug('Received a federation invite with missing request data');
		throw new ProviderCouldNotAddShareException('required request data not found', '', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @inheritDoc
	 */
	public function notificationReceived($notificationType, $providerId, array $notification): array {
		if (!is_numeric($providerId)) {
			throw new BadRequestException(['providerId']);
		}
		switch ($notificationType) {
			case FederationManager::NOTIFICATION_SHARE_ACCEPTED:
				return $this->shareAccepted((int) $providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_DECLINED:
				return $this->shareDeclined((int) $providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_UNSHARED:
				return $this->shareUnshared((int) $providerId, $notification);
			case FederationManager::NOTIFICATION_ROOM_MODIFIED:
				return $this->roomModified((int) $providerId, $notification);
		}

		throw new BadRequestException([$notificationType]);
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareAccepted(int $id, array $notification): array {
		$attendee = $this->getAttendeeAndValidate($id, $notification['sharedSecret']);

		$this->session->set('talk-overwrite-actor-type', $attendee->getActorType());
		$this->session->set('talk-overwrite-actor-id', $attendee->getActorId());
		$this->session->set('talk-overwrite-actor-displayname', $attendee->getDisplayName());

		$room = $this->manager->getRoomById($attendee->getRoomId());
		$event = new AttendeesAddedEvent($room, [$attendee]);
		$this->dispatcher->dispatchTyped($event);

		$this->session->remove('talk-overwrite-actor-type');
		$this->session->remove('talk-overwrite-actor-id');
		$this->session->remove('talk-overwrite-actor-displayname');

		return [];
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareDeclined(int $id, array $notification): array {
		$attendee = $this->getAttendeeAndValidate($id, $notification['sharedSecret']);

		$this->session->set('talk-overwrite-actor-type', $attendee->getActorType());
		$this->session->set('talk-overwrite-actor-id', $attendee->getActorId());
		$this->session->set('talk-overwrite-actor-displayname', $attendee->getDisplayName());

		$room = $this->manager->getRoomById($attendee->getRoomId());
		$participant = new Participant($room, $attendee, null);
		$this->participantService->removeAttendee($room, $participant, Room::PARTICIPANT_LEFT);

		$this->session->remove('talk-overwrite-actor-type');
		$this->session->remove('talk-overwrite-actor-id');
		$this->session->remove('talk-overwrite-actor-displayname');
		return [];
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareUnshared(int $id, array $notification): array {
		$attendee = $this->getRemoteAttendeeAndValidate($id, $notification['sharedSecret']);

		$room = $this->manager->getRoomById($attendee->getRoomId());

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedRemoteRoom()) {
			throw new ShareNotFound();
		}

		$participant = new Participant($room, $attendee, null);
		$this->participantService->removeAttendee($room, $participant, Room::PARTICIPANT_REMOVED);
		return [];
	}

	/**
	 * @param int $remoteAttendeeId
	 * @param array{sharedSecret: string, remoteToken: string, changedProperty: string, newValue: string|int|bool|null, oldValue: string|int|bool|null} $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws ShareNotFound
	 * @throws \OCA\Talk\Exceptions\RoomNotFoundException
	 */
	private function roomModified(int $remoteAttendeeId, array $notification): array {
		$attendee = $this->getRemoteAttendeeAndValidate($remoteAttendeeId, $notification['sharedSecret']);

		$room = $this->manager->getRoomById($attendee->getRoomId());

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedRemoteRoom()) {
			throw new ShareNotFound();
		}

		if ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_AVATAR) {
			$this->roomService->setAvatar($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_DESCRIPTION) {
			$this->roomService->setDescription($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_NAME) {
			$this->roomService->setName($room, $notification['newValue'], $notification['oldValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_READ_ONLY) {
			$this->roomService->setReadOnly($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_TYPE) {
			$this->roomService->setType($room, $notification['newValue']);
		} else {
			$this->logger->debug('Update of room property "' . $notification['changedProperty'] . '" is not handled and should not be send via federation');
		}

		return [];
	}

	/**
	 * @throws AuthenticationFailedException
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 */
	private function getAttendeeAndValidate(int $id, string $sharedSecret): Attendee {
		if (!$this->config->isFederationEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		try {
			$attendee = $this->attendeeMapper->getById($id);
		} catch (Exception $ex) {
			throw new ShareNotFound();
		}
		if ($attendee->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			throw new ShareNotFound();
		}
		if ($attendee->getAccessToken() !== $sharedSecret) {
			throw new AuthenticationFailedException();
		}
		return $attendee;
	}

	/**
	 * @param int $id
	 * @param string $sharedSecret
	 * @return Attendee|Invitation
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function getRemoteAttendeeAndValidate(int $id, string $sharedSecret): Attendee|Invitation {
		if (!$this->federationManager->isEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		if (!$sharedSecret) {
			throw new AuthenticationFailedException();
		}

		try {
			return $this->attendeeMapper->getByRemoteIdAndToken($id, $sharedSecret);
		} catch (DoesNotExistException) {
			try {
				return $this->invitationMapper->getByRemoteIdAndToken($id, $sharedSecret);
			} catch (DoesNotExistException $e) {
				throw new ShareNotFound();
			}
		} catch (Exception) {
			throw new ShareNotFound();
		}
	}

	private function notifyAboutNewShare(IUser $shareWith, string $shareId, string $sharedByFederatedId, string $sharedByName, string $roomName, string $roomToken, string $serverUrl): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($shareWith->getUID())
			->setDateTime(new \DateTime())
			->setObject('remote_talk_share', $shareId)
			->setSubject('remote_talk_share', [
				'sharedByDisplayName' => $sharedByName,
				'sharedByFederatedId' => $sharedByFederatedId,
				'roomName' => $roomName,
				'serverUrl' => $serverUrl,
				'roomToken' => $roomToken,
			]);

		$this->notificationManager->notify($notification);
	}

	private function validSharedRoomTypes(): array {
		return [
			Room::TYPE_ONE_TO_ONE,
			Room::TYPE_GROUP,
			Room::TYPE_PUBLIC,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedShareTypes(): array {
		return ['user'];
	}
}
