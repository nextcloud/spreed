<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation;

use Exception;
use NCU\Federation\ISignedCloudFederationProvider;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\CachePrefix;
use OCA\Talk\Config;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Notification\FederationChatNotifier;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\HintException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\Exceptions\ShareNotFound;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class CloudFederationProviderTalk implements ICloudFederationProvider, ISignedCloudFederationProvider {
	protected ?ICache $proxyCacheMessages;

	public function __construct(
		private ICloudIdManager $cloudIdManager,
		private IUserManager $userManager,
		private AddressHandler $addressHandler,
		private FederationManager $federationManager,
		private Config $config,
		private IAppConfig $appConfig,
		private INotificationManager $notificationManager,
		private ParticipantService $participantService,
		private RoomService $roomService,
		private AttendeeMapper $attendeeMapper,
		private InvitationMapper $invitationMapper,
		private Manager $manager,
		private ISession $session,
		private IEventDispatcher $dispatcher,
		private LoggerInterface $logger,
		private ProxyCacheMessageMapper $proxyCacheMessageMapper,
		private ProxyCacheMessageService $pcmService,
		private FederationChatNotifier $federationChatNotifier,
		private UserConverter $userConverter,
		private ITimeFactory $timeFactory,
		ICacheFactory $cacheFactory,
	) {
		$this->proxyCacheMessages = $cacheFactory->isAvailable() ? $cacheFactory->createDistributed(CachePrefix::FEDERATED_PCM) : null;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getShareType(): string {
		return 'talk-room';
	}

	/**
	 * @inheritDoc
	 * @throws HintException
	 * @throws DBException
	 */
	#[\Override]
	public function shareReceived(ICloudFederationShare $share): string {
		if (!$this->config->isFederationEnabled()) {
			$this->logger->debug('Received a federation invite but federation is disabled');
			throw new ProviderCouldNotAddShareException('Server does not support talk federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		if (!$this->appConfig->getAppValueBool('federation_incoming_enabled', true)) {
			$this->logger->warning('Received a federation invite but incoming federation is disabled');
			throw new ProviderCouldNotAddShareException('Server does not support talk federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		if (!in_array($share->getShareType(), $this->getSupportedShareTypes(), true)) {
			$this->logger->debug('Received a federation invite for invalid share type');
			throw new ProviderCouldNotAddShareException('Support for sharing with non-users not implemented yet', '', Http::STATUS_NOT_IMPLEMENTED);
			// TODO: Implement group shares
		}

		$roomType = $share->getProtocol()['roomType'];
		if (!is_numeric($roomType) || !in_array((int)$roomType, $this->validSharedRoomTypes(), true)) {
			$this->logger->debug('Received a federation invite for invalid room type');
			throw new ProviderCouldNotAddShareException('roomType is not a valid number', '', Http::STATUS_BAD_REQUEST);
		}

		$shareSecret = $share->getShareSecret();
		$shareWith = $share->getShareWith();
		$remoteId = $share->getProviderId();
		$roomToken = $share->getResourceName();
		$roomName = $share->getProtocol()['roomName'];
		$roomDefaultPermissions = $share->getProtocol()['roomDefaultPermissions'] ?? Attendee::PERMISSIONS_DEFAULT;
		if (isset($share->getProtocol()['invitedCloudId'])) {
			$localCloudId = $share->getProtocol()['invitedCloudId'];
		} else {
			$this->logger->debug('Received a federation invite without invitedCloudId, falling back to shareWith');
			$cloudId = $this->cloudIdManager->getCloudId($shareWith, null);
			$localCloudId = $cloudId->getUser() . '@' . $cloudId->getRemote();
		}
		$roomType = (int)$roomType;
		$sharedByDisplayName = $share->getSharedByDisplayName();
		$sharedByFederatedId = $share->getSharedBy();
		$ownerDisplayName = $share->getOwnerDisplayName();
		$ownerFederatedId = $share->getOwner();
		[, $remote] = $this->addressHandler->splitUserRemote($ownerFederatedId);

		if (!$this->addressHandler->urlContainProtocol($remote)) {
			// Heal federation from before Nextcloud 29.0.4 which sends requests
			// without the protocol on the remote in case it is https://
			$remote = 'https://' . $remote;
		}

		// if no explicit information about the person who created the share was sent
		// we assume that the share comes from the owner
		if ($sharedByFederatedId === null) {
			$sharedByDisplayName = $ownerDisplayName;
			$sharedByFederatedId = $ownerFederatedId;
		}

		if ($remote && $shareSecret && $shareWith && $roomToken && $remoteId && is_string($roomName) && $roomName && $ownerDisplayName) {
			$shareWithUser = $this->userManager->get($shareWith);
			if ($shareWithUser === null) {
				$this->logger->debug('Received a federation invite for user that could not be found');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			} elseif (!str_starts_with($localCloudId, $shareWithUser->getUID() . '@')) {
				// Fix the user ID as we also return it via the cloud federation api response in Nextcloud 30+
				$cloudId = $this->cloudIdManager->resolveCloudId($localCloudId);
				$localCloudId = $shareWithUser->getUID() . '@' . $cloudId->getRemote();
			}

			if ($this->config->isDisabledForUser($shareWithUser)) {
				$this->logger->debug('Received a federation invite for user that is not allowed to use Talk');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			}

			if (!$this->config->isFederationEnabledForUserId($shareWithUser)) {
				$this->logger->debug('Received a federation invite for user that is not allowed to use Talk Federation');
				throw new ProviderCouldNotAddShareException('User does not exist', '', Http::STATUS_BAD_REQUEST);
			}

			$invite = $this->federationManager->addRemoteRoom($shareWithUser, (int)$remoteId, $roomType, $roomName, $roomDefaultPermissions, $roomToken, $remote, $shareSecret, $sharedByFederatedId, $sharedByDisplayName, $localCloudId);

			$this->notifyAboutNewShare($shareWithUser, (string)$invite->getId(), $sharedByFederatedId, $sharedByDisplayName, $roomName, $roomToken, $remote);
			return (string)$invite->getId();
		}

		$this->logger->debug('Received a federation invite with missing request data');
		throw new ProviderCouldNotAddShareException('required request data not found', '', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function notificationReceived($notificationType, $providerId, array $notification): array {
		if (!is_numeric($providerId)) {
			throw new BadRequestException(['providerId']);
		}
		switch ($notificationType) {
			case FederationManager::NOTIFICATION_SHARE_ACCEPTED:
				return $this->shareAccepted((int)$providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_DECLINED:
				return $this->shareDeclined((int)$providerId, $notification);
			case FederationManager::NOTIFICATION_SHARE_UNSHARED:
				return $this->shareUnshared((int)$providerId, $notification);
			case FederationManager::NOTIFICATION_PARTICIPANT_MODIFIED:
				return $this->participantModified((int)$providerId, $notification);
			case FederationManager::NOTIFICATION_ROOM_MODIFIED:
				return $this->roomModified((int)$providerId, $notification);
			case FederationManager::NOTIFICATION_MESSAGE_POSTED:
				return $this->messagePosted((int)$providerId, $notification);
		}

		throw new BadRequestException([$notificationType]);
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareAccepted(int $id, array $notification): array {
		$attendee = $this->getLocalAttendeeAndValidate($id, $notification['sharedSecret']);

		if (!empty($notification['displayName'])) {
			$attendee->setDisplayName($notification['displayName']);
			$attendee->setState(Invitation::STATE_ACCEPTED);

			if (!empty($notification['cloudId'])) {
				$attendee->setActorId($notification['cloudId']);
			}

			$this->attendeeMapper->update($attendee);
		}

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
		$attendee = $this->getLocalAttendeeAndValidate($id, $notification['sharedSecret']);

		$this->session->set('talk-overwrite-actor-type', $attendee->getActorType());
		$this->session->set('talk-overwrite-actor-id', $attendee->getActorId());
		$this->session->set('talk-overwrite-actor-displayname', $attendee->getDisplayName());

		$room = $this->manager->getRoomById($attendee->getRoomId());
		$participant = new Participant($room, $attendee, null);
		$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_LEFT);

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
	private function shareUnshared(int $remoteAttendeeId, array $notification): array {
		$invite = $this->getByRemoteAttendeeAndValidate($notification['remoteServerUrl'], $remoteAttendeeId, $notification['sharedSecret']);
		try {
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (RoomNotFoundException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedConversation()) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		$this->invitationMapper->delete($invite);

		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_USERS, $invite->getUserId());
			$this->participantService->removeAttendee($room, $participant, AAttendeeRemovedEvent::REASON_REMOVED);
		} catch (ParticipantNotFoundException) {
			// Never accepted the invite
		}

		return [];
	}

	/**
	 * @param int $remoteAttendeeId
	 * @param array{remoteServerUrl: string, sharedSecret: string, remoteToken: string, changedProperty: string, newValue: string|int, oldValue: string|int|null} $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws ShareNotFound
	 */
	private function participantModified(int $remoteAttendeeId, array $notification): array {
		$invite = $this->getByRemoteAttendeeAndValidate($notification['remoteServerUrl'], $remoteAttendeeId, $notification['sharedSecret']);
		try {
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (RoomNotFoundException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedConversation()) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		try {
			$participant = $this->participantService->getParticipant($room, $invite->getUserId());
		} catch (ParticipantNotFoundException $e) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		if ($notification['changedProperty'] === AParticipantModifiedEvent::PROPERTY_PERMISSIONS) {
			$this->participantService->updatePermissions($room, $participant, Attendee::PERMISSIONS_MODIFY_SET, $notification['newValue']);
		} elseif ($notification['changedProperty'] === AParticipantModifiedEvent::PROPERTY_RESEND_CALL) {
			$event = new CallNotificationSendEvent($room, null, $participant);
			$this->dispatcher->dispatchTyped($event);
		} else {
			$this->logger->debug('Update of participant property "' . $notification['changedProperty'] . '" is not handled and should not be send via federation');
		}

		return [];
	}

	/**
	 * @param int $remoteAttendeeId
	 * @param array{remoteServerUrl: string, sharedSecret: string, remoteToken: string, changedProperty: string, newValue: string|int|bool|null, oldValue: string|int|bool|null, callFlag?: int, dateTime?: string, timerReached?: bool, details?: array<AParticipantModifiedEvent::DETAIL_*, bool>} $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws ShareNotFound
	 */
	private function roomModified(int $remoteAttendeeId, array $notification): array {
		$invite = $this->getByRemoteAttendeeAndValidate($notification['remoteServerUrl'], $remoteAttendeeId, $notification['sharedSecret']);
		try {
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (RoomNotFoundException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedConversation()) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		if ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_ACTIVE_SINCE) {
			if ($notification['newValue'] === null) {
				$this->roomService->resetActiveSince($room, null);
			} else {
				$activeSince = $room->getActiveSince();
				if ($activeSince === null || $notification['newValue'] < $activeSince->getTimestamp()) {
					/**
					 * If the host is sending a lower timestamp, we healed an early in_call update,
					 * so we take the older value as the host should know more specifically.
					 */
					$activeSince = $this->timeFactory->getDateTime('@' . $notification['newValue']);
				}
				$this->roomService->setActiveSince(
					$room,
					null,
					$activeSince,
					$notification['callFlag'] | $room->getCallFlag(),
					!empty($notification['details'][AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT]),
				);
			}
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_AVATAR) {
			$this->roomService->setAvatar($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_CALL_RECORDING) {
			/** @psalm-suppress InvalidArgument */
			$this->roomService->setCallRecording($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_DEFAULT_PERMISSIONS) {
			$this->roomService->setDefaultPermissions($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_DESCRIPTION) {
			$this->roomService->setDescription($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_IN_CALL) {
			/**
			 * In case the in_call update arrives before the actual active_since update,
			 * we fake the timestamp so we at least don't fail the request.
			 * When the active_since finally arrives we merge the results.
			 */
			$this->roomService->setActiveSince(
				$room,
				null,
				$room->getActiveSince() ?? $this->timeFactory->getDateTime(),
				$notification['newValue'],
				true,
			);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_LOBBY) {
			$dateTime = !empty($notification['dateTime']) ? \DateTime::createFromFormat('U', $notification['dateTime']) : null;
			$this->roomService->setLobby($room, $notification['newValue'], $dateTime, $notification['timerReached'] ?? false);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_MENTION_PERMISSIONS) {
			/** @psalm-suppress InvalidArgument */
			$this->roomService->setMentionPermissions($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION) {
			$this->roomService->setMessageExpiration($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_NAME) {
			$this->roomService->setName($room, $notification['newValue'], $notification['oldValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_READ_ONLY) {
			$this->roomService->setReadOnly($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_RECORDING_CONSENT) {
			/** @psalm-suppress InvalidArgument */
			$this->roomService->setRecordingConsent($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_SIP_ENABLED) {
			$this->roomService->setSIPEnabled($room, $notification['newValue']);
		} elseif ($notification['changedProperty'] === ARoomModifiedEvent::PROPERTY_TYPE) {
			$this->roomService->setType($room, $notification['newValue']);
		} else {
			$this->logger->debug('Update of room property "' . $notification['changedProperty'] . '" is not handled and should not be send via federation');
		}

		return [];
	}

	/**
	 * @param int $remoteAttendeeId
	 * @param array{remoteServerUrl: string, sharedSecret: string, remoteToken: string, messageData: array{remoteMessageId: int, actorType: string, actorId: string, actorDisplayName: string, messageType: string, systemMessage: string, expirationDatetime: string, message: string, messageParameter: string, creationDatetime: string, metaData: string}, unreadInfo: array{unreadMessages: int, unreadMention: bool, unreadMentionDirect: bool, lastReadMessage: int}} $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws ShareNotFound
	 */
	private function messagePosted(int $remoteAttendeeId, array $notification): array {
		$invite = $this->getByRemoteAttendeeAndValidate($notification['remoteServerUrl'], $remoteAttendeeId, $notification['sharedSecret']);
		try {
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (RoomNotFoundException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		// Sanity check to make sure the room is a remote room
		if (!$room->isFederatedConversation()) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}

		$removeParentMessage = null;
		if ($notification['messageData']['systemMessage'] === 'message_edited'
			|| $notification['messageData']['systemMessage'] === 'message_deleted') {
			$metaData = json_decode($notification['messageData']['metaData'], true);
			if (isset($metaData['replyToMessageId'])) {
				$removeParentMessage = $metaData['replyToMessageId'];
			}
		}

		// We transform the parameters when storing in the PCM, so we only have
		// to do it once for each message.
		// Note: `messageParameters` (array during parsing) vs `messageParameter` (string during sending)
		$notification['messageData']['messageParameters'] = json_decode($notification['messageData']['messageParameter'], true, flags: JSON_THROW_ON_ERROR);
		unset($notification['messageData']['messageParameter']);
		$converted = $this->userConverter->convertMessage($room, $notification['messageData']);
		$converted['messageParameter'] = json_encode($converted['messageParameters'], JSON_THROW_ON_ERROR);
		unset($converted['messageParameters']);

		/** @var array{remoteMessageId: int, actorType: string, actorId: string, actorDisplayName: string, messageType: string, systemMessage: string, expirationDatetime: string, message: string, messageParameter: string, creationDatetime: string, metaData: string} $converted */
		$notification['messageData'] = $converted;

		$message = null;
		if ($removeParentMessage === null) {
			$message = new ProxyCacheMessage();
			$message->setLocalToken($room->getToken());
			$message->setRemoteServerUrl($notification['remoteServerUrl']);
			$message->setRemoteToken($notification['remoteToken']);
			$message->setRemoteMessageId($notification['messageData']['remoteMessageId']);
			$message->setActorType($notification['messageData']['actorType']);
			$message->setActorId($notification['messageData']['actorId']);
			$message->setActorDisplayName($notification['messageData']['actorDisplayName']);
			$message->setMessageType($notification['messageData']['messageType']);
			$message->setSystemMessage($notification['messageData']['systemMessage']);
			if ($notification['messageData']['expirationDatetime']) {
				$message->setExpirationDatetime(new \DateTime($notification['messageData']['expirationDatetime']));
			}
			$message->setMessage($notification['messageData']['message']);
			$message->setMessageParameters($notification['messageData']['messageParameter']);
			$message->setCreationDatetime(new \DateTime($notification['messageData']['creationDatetime']));
			$message->setMetaData($notification['messageData']['metaData']);

			try {
				$this->proxyCacheMessageMapper->insert($message);

				$lastMessageId = $room->getLastMessageId();
				if ($notification['messageData']['remoteMessageId'] > $lastMessageId) {
					$lastMessageId = (int)$notification['messageData']['remoteMessageId'];
				}

				if ($notification['messageData']['systemMessage'] !== 'message_edited'
					&& $notification['messageData']['systemMessage'] !== 'message_deleted') {
					$this->roomService->setLastMessageInfo($room, $lastMessageId, $this->timeFactory->getDateTime());
				}

				if ($this->proxyCacheMessages instanceof ICache) {
					$cacheKey = sha1(json_encode([$notification['remoteServerUrl'], $notification['remoteToken']]));
					$cacheData = $this->proxyCacheMessages->get($cacheKey);
					if ($cacheData === null || $cacheData < $notification['messageData']['remoteMessageId']) {
						$this->proxyCacheMessages->set($cacheKey, $notification['messageData']['remoteMessageId'], 300);
					}
				}
			} catch (DBException $e) {
				// DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION happens when
				// multiple users are in the same conversation. We are therefore
				// informed multiple times about the same remote message.
				if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					$this->logger->error('Error saving proxy cache message failed: ' . $e->getMessage(), ['exception' => $e]);
					throw $e;
				}

				$message = $this->pcmService->findByRemote(
					$notification['remoteServerUrl'],
					$notification['remoteToken'],
					$notification['messageData']['remoteMessageId'],
				);
			}
		}

		try {
			$participant = $this->participantService->getParticipantWithActiveSession($room, $invite->getUserId());
		} catch (ParticipantNotFoundException) {
			// Not accepted the invite yet
			return [];
		}

		if ($removeParentMessage !== null) {
			try {
				$this->pcmService->syncRemoteMessage($room, $participant, $removeParentMessage);
			} catch (\InvalidArgumentException|CannotReachRemoteException) {
				$oldMessage = $this->pcmService->findByRemote(
					$notification['remoteServerUrl'],
					$notification['remoteToken'],
					$removeParentMessage,
				);
				$this->pcmService->delete($oldMessage);
				$this->logger->info('Failed to resync chat message #' . $removeParentMessage . ' after being notified by host ' . $notification['remoteServerUrl']);
			}

			// Update the last activity so the left sidebar refreshes the data as well
			$this->roomService->setLastMessageInfo($room, $room->getLastMessageId(), new \DateTime());
		}

		$this->logger->debug('Setting unread info for local federated user ' . $invite->getUserId() . ' in ' . $room->getToken() . ' to ' . json_encode($notification['unreadInfo']), [
			'app' => 'spreed-federation',
		]);

		$this->participantService->updateUnreadInfoForProxyParticipant(
			$participant,
			$notification['unreadInfo']['unreadMessages'],
			$notification['unreadInfo']['unreadMention'],
			$notification['unreadInfo']['unreadMentionDirect'],
			$notification['unreadInfo']['lastReadMessage'],
		);

		if ($message instanceof ProxyCacheMessage) {
			$this->federationChatNotifier->handleChatMessage($room, $participant, $message, $notification);
		}

		return [];
	}

	/**
	 * @throws AuthenticationFailedException
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 */
	private function getLocalAttendeeAndValidate(
		int $attendeeId,
		#[SensitiveParameter]
		string $sharedSecret,
	): Attendee {
		if (!$this->config->isFederationEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		try {
			$attendee = $this->attendeeMapper->getById($attendeeId);
		} catch (Exception) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}
		if ($attendee->getActorType() !== Attendee::ACTOR_FEDERATED_USERS) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}
		if ($attendee->getAccessToken() !== $sharedSecret) {
			throw new AuthenticationFailedException();
		}
		return $attendee;
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function getByRemoteAttendeeAndValidate(
		string $remoteServerUrl,
		int $remoteAttendeeId,
		#[SensitiveParameter]
		string $sharedSecret,
	): Invitation {
		if (!$this->config->isFederationEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		if (!$sharedSecret) {
			throw new AuthenticationFailedException();
		}

		if (!$this->addressHandler->urlContainProtocol($remoteServerUrl)) {
			// Heal federation from before Nextcloud 29.0.4 which sends requests
			// without the protocol on the remote in case it is https://
			$remoteServerUrl = 'https://' . $remoteServerUrl;
		}

		try {
			return $this->invitationMapper->getByRemoteAndAccessToken($remoteServerUrl, $remoteAttendeeId, $sharedSecret);
		} catch (DoesNotExistException) {
			throw new ShareNotFound(FederationManager::OCM_RESOURCE_NOT_FOUND);
		}
	}

	private function notifyAboutNewShare(IUser $shareWith, string $inviteId, string $sharedByFederatedId, string $sharedByName, string $roomName, string $remoteRoomToken, string $remoteServerUrl): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($shareWith->getUID())
			->setDateTime(new \DateTime())
			->setObject('remote_talk_share', $inviteId)
			->setSubject('remote_talk_share', [
				'sharedByDisplayName' => $sharedByName,
				'sharedByFederatedId' => $sharedByFederatedId,
				'roomName' => $roomName,
				'serverUrl' => $remoteServerUrl,
				'roomToken' => $remoteRoomToken,
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
	#[\Override]
	public function getSupportedShareTypes(): array {
		return ['user'];
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getFederationIdFromSharedSecret(
		#[SensitiveParameter]
		string $sharedSecret,
		array $payload,
	): string {
		$remoteServerUrl = $payload['remoteServerUrl'];
		if (str_starts_with($remoteServerUrl, 'https://')) {
			$remoteServerUrl = substr($remoteServerUrl, strlen('https://'));
		}

		try {
			$invite = $this->invitationMapper->getByRemoteServerAndAccessToken($payload['remoteServerUrl'], $sharedSecret);
			return $invite->getInviterCloudId();
		} catch (DoesNotExistException) {
		}

		$attendees = $this->attendeeMapper->getByAccessToken($sharedSecret);
		foreach ($attendees as $attendee) {
			if (str_ends_with($attendee->getActorId(), '@' . $remoteServerUrl)) {
				return $attendee->getActorId();
			}
		}

		return '';
	}
}
