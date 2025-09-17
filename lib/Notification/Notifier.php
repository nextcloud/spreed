<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Notification;

use OCA\Circles\CirclesManager;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\Federation\ICloudIdManager;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IAction;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use OCP\RichObjectStrings\Definitions;
use OCP\Server;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {

	/** @var Room[] */
	protected array $rooms = [];
	/** @var Participant[][] */
	protected array $participants = [];
	/** @var array<string, string> */
	protected array $circleNames = [];
	/** @var array<string, string> */
	protected array $circleLinks = [];
	protected ICommentsManager $commentManager;

	public function __construct(
		protected IFactory $lFactory,
		protected IURLGenerator $url,
		protected Config $config,
		protected IAppManager $appManager,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
		protected GuestManager $guestManager,
		private IShareManager $shareManager,
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected AvatarService $avatarService,
		protected INotificationManager $notificationManager,
		CommentsManager $commentManager,
		protected ProxyCacheMessageMapper $proxyCacheMessageMapper,
		protected MessageParser $messageParser,
		protected IRootFolder $rootFolder,
		protected ITimeFactory $timeFactory,
		protected Definitions $definitions,
		protected AddressHandler $addressHandler,
		protected BotServerMapper $botServerMapper,
		protected FederationManager $federationManager,
		protected ICloudIdManager $cloudIdManager,
		protected LoggerInterface $logger,
	) {
		$this->commentManager = $commentManager;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	#[\Override]
	public function getID(): string {
		return 'talk';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	#[\Override]
	public function getName(): string {
		return $this->lFactory->get(Application::APP_ID)->t('Talk');
	}

	/**
	 * @param string $objectId
	 * @param string $userId
	 * @return Room
	 * @throws RoomNotFoundException
	 */
	protected function getRoom(string $objectId, string $userId): Room {
		if (array_key_exists($objectId, $this->rooms)) {
			if ($this->rooms[$objectId] === null) {
				throw new RoomNotFoundException('Room does not exist');
			}

			return $this->rooms[$objectId];
		}

		try {
			$room = $this->manager->getRoomByToken($objectId, $userId);
			$this->rooms[$objectId] = $room;
			return $room;
		} catch (RoomNotFoundException $e) {
			if (!is_numeric($objectId)) {
				// Room does not exist
				$this->rooms[$objectId] = null;
				throw $e;
			}

			try {
				// Before 3.2.3 the id was passed in notifications
				$room = $this->manager->getRoomById((int)$objectId);
				$this->rooms[$objectId] = $room;
				return $room;
			} catch (RoomNotFoundException $e) {
				// Room does not exist
				$this->rooms[$objectId] = null;
				throw $e;
			}
		}
	}

	/**
	 * @param Room $room
	 * @param string $userId
	 * @return Participant
	 * @throws ParticipantNotFoundException
	 */
	protected function getParticipant(Room $room, string $userId): Participant {
		$roomId = $room->getId();
		if (array_key_exists($roomId, $this->participants) && array_key_exists($userId, $this->participants[$roomId])) {
			if ($this->participants[$roomId][$userId] === null) {
				throw new ParticipantNotFoundException('Participant does not exist');
			}

			return $this->participants[$roomId][$userId];
		}

		try {
			$participant = $this->participantService->getParticipant($room, $userId, false);
			$this->participants[$roomId][$userId] = $participant;
			return $participant;
		} catch (ParticipantNotFoundException $e) {
			// Participant does not exist
			$this->participants[$roomId][$userId] = null;
			throw $e;
		}
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws AlreadyProcessedException
	 * @throws UnknownNotificationException
	 * @since 9.0.0
	 */
	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('Incorrect app');
		}

		if (!($this->notificationManager->isPreparingPushNotification() && $notification->getSubject() === 'call')) {
			$userId = $notification->getUser();
			$user = $this->userManager->get($userId);
			if (!$user instanceof IUser || $this->config->isDisabledForUser($user)) {
				throw new AlreadyProcessedException();
			}
		}

		$l = $this->lFactory->get(Application::APP_ID, $languageCode);

		if ($notification->getObjectType() === 'hosted-signaling-server') {
			return $this->parseHostedSignalingServer($notification, $l);
		}

		if ($notification->getObjectType() === 'remote_talk_share') {
			return $this->parseRemoteInvitationMessage($notification, $l);
		}

		if ($notification->getObjectType() === 'certificate_expiration') {
			return $this->parseCertificateExpiration($notification, $l);
		}

		if ($this->notificationManager->isPreparingPushNotification() && $notification->getSubject() === 'call') {
			try {
				$room = $this->manager->getRoomByToken($notification->getObjectId());
			} catch (RoomNotFoundException) {
				// Room does not exist
				throw new AlreadyProcessedException();
			}

			// Skip the participant check when we generate push notifications
			// we just looped over the participants to create the notification,
			// they can not be removed between these 2 steps, but we can save
			// n queries.
			$participant = null;
		} else {
			try {
				$room = $this->getRoom($notification->getObjectId(), $userId);
			} catch (RoomNotFoundException $e) {
				// Room does not exist
				throw new AlreadyProcessedException();
			}

			try {
				$participant = $this->getParticipant($room, $userId);
			} catch (ParticipantNotFoundException $e) {
				// Room does not exist
				throw new AlreadyProcessedException();
			}
		}

		$notification
			->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')))
			->setLink($this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]));

		if ($participant instanceof Participant && $this->notificationManager->isPreparingPushNotification()) {
			$notification->setPriorityNotification($participant->getAttendee()->isImportant());
		}

		$subject = $notification->getSubject();
		if ($subject === 'record_file_stored' || $subject === 'transcript_file_stored' || $subject === 'transcript_failed' || $subject === 'summary_file_stored' || $subject === 'summary_failed') {
			return $this->parseStoredRecording($notification, $room, $participant, $l);
		}
		if ($subject === 'record_file_store_fail') {
			return $this->parseStoredRecordingFail($notification, $room, $participant, $l);
		}
		if ($subject === 'invitation') {
			return $this->parseInvitation($notification, $room, $l);
		}
		if ($subject === 'call') {
			if ($participant instanceof Participant
				&& $room->getLobbyState() !== Webinary::LOBBY_NONE
				&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				// User is blocked by the lobby, remove notification
				throw new AlreadyProcessedException();
			}

			if ($room->getObjectType() === 'share:password') {
				return $this->parsePasswordRequest($notification, $room, $l);
			}
			return $this->parseCall($notification, $room, $l);
		}
		if ($subject === 'reply' || $subject === 'mention' || $subject === 'mention_direct' || $subject === 'mention_group' || $subject === 'mention_team' || $subject === 'mention_all' || $subject === 'chat' || $subject === 'reaction' || $subject === 'reminder') {
			if ($participant instanceof Participant
				&& $room->getLobbyState() !== Webinary::LOBBY_NONE
				&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				// User is blocked by the lobby, remove notification
				throw new AlreadyProcessedException();
			}

			return $this->parseChatMessage($notification, $room, $participant, $l);
		}

		$this->notificationManager->markProcessed($notification);
		throw new UnknownNotificationException('Unknown subject');
	}

	protected function shortenJsonEncodedMultibyteSave(string $subject, int $dataLength): string {
		$temp = mb_substr($subject, 0, $dataLength);
		while (strlen(json_encode($temp)) > $dataLength) {
			$temp = mb_substr($temp, 0, -5);
		}
		return $temp;
	}

	protected function parseStoredRecordingFail(
		INotification $notification,
		Room $room,
		Participant $participant,
		IL10N $l,
	): INotification {
		$notification
			->setRichSubject(
				$l->t('Failed to upload call recording'),
			)
			->setRichMessage(
				$l->t('The recording server failed to upload recording of call {call}. Please reach out to the administration.'),
				[
					'call' => [
						'type' => 'call',
						'id' => (string)$room->getId(),
						'name' => $room->getDisplayName($participant->getAttendee()->getActorId()),
						'call-type' => $this->getRoomType($room),
						'icon-url' => $this->avatarService->getAvatarUrl($room),
					],
				]
			);
		return $notification;
	}

	protected function parseStoredRecording(
		INotification $notification,
		Room $room,
		Participant $participant,
		IL10N $l,
	): INotification {
		$parameters = $notification->getSubjectParameters();
		try {
			$userFolder = $this->rootFolder->getUserFolder($notification->getUser());
			/** @var \OCP\Files\File[] */
			$files = $userFolder->getById($parameters['objectId']);
			/** @var \OCP\Files\File $file */
			$file = array_shift($files);
			$path = $userFolder->getRelativePath($file->getPath());
		} catch (\Throwable $th) {
			throw new AlreadyProcessedException();
		}

		$shareAction = $notification->createAction()
			->setParsedLabel($l->t('Share to chat'))
			->setPrimary(true)
			->setLink(
				$this->url->linkToOCSRouteAbsolute(
					'spreed.Recording.shareToChat',
					[
						'apiVersion' => 'v1',
						'fileId' => $file->getId(),
						'timestamp' => $notification->getDateTime()->getTimestamp(),
						'token' => $room->getToken()
					]
				),
				IAction::TYPE_POST
			);
		$dismissAction = $notification->createAction()
			->setParsedLabel($l->t('Dismiss notification'))
			->setLink(
				$this->url->linkToOCSRouteAbsolute(
					'spreed.Recording.notificationDismiss',
					[
						'apiVersion' => 'v1',
						'token' => $room->getToken(),
						'timestamp' => $notification->getDateTime()->getTimestamp(),
					]
				),
				IAction::TYPE_DELETE
			);

		if ($notification->getSubject() === 'record_file_stored') {
			$subject = $l->t('Call recording now available');
			$message = $l->t('The recording for the call in {call} was uploaded to {file}.');
		} elseif ($notification->getSubject() === 'transcript_file_stored') {
			$subject = $l->t('Transcript now available');
			$message = $l->t('The transcript for the call in {call} was uploaded to {file}.');
		} elseif ($notification->getSubject() === 'transcript_failed') {
			$subject = $l->t('Failed to transcript call recording');
			$message = $l->t('The server failed to transcript the recording at {file} for the call in {call}. Please reach out to the administration.');
		} elseif ($notification->getSubject() === 'summary_file_stored') {
			$subject = $l->t('Call summary now available');
			$message = $l->t('The summary for the call in {call} was uploaded to {file}.');
		} else {
			$subject = $l->t('Failed to summarize call recording');
			$message = $l->t('The server failed to summarize the recording at {file} for the call in {call}. Please reach out to the administration.');
		}

		$notification
			->setRichSubject($subject)
			->setRichMessage(
				$message,
				[
					'call' => [
						'type' => 'call',
						'id' => (string)$room->getId(),
						'name' => $room->getDisplayName($participant->getAttendee()->getActorId()),
						'call-type' => $this->getRoomType($room),
						'icon-url' => $this->avatarService->getAvatarUrl($room),
					],
					'file' => [
						'type' => 'file',
						'id' => (string)$file->getId(),
						'name' => $file->getName(),
						'path' => (string)$path,
						'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $file->getId()]),
					],
				]);

		if ($notification->getSubject() !== 'transcript_failed' && $notification->getSubject() !== 'summary_failed') {
			$notification->addParsedAction($shareAction);
			$notification->addParsedAction($dismissAction);
		}

		return $notification;
	}

	protected function parseRemoteInvitationMessage(INotification $notification, IL10N $l): INotification {
		$subjectParameters = $notification->getSubjectParameters();

		try {
			$invite = $this->federationManager->getRemoteShareById((int)$notification->getObjectId());
			if ($invite->getUserId() !== $notification->getUser()) {
				throw new AlreadyProcessedException();
			}
			$room = $this->manager->getRoomById($invite->getLocalRoomId());
		} catch (DoesNotExistException) {
			// Invitation does not exist
			throw new AlreadyProcessedException();
		} catch (RoomNotFoundException) {
			// Room does not exist
			throw new AlreadyProcessedException();
		}

		[$sharedById, $sharedByServer] = $this->addressHandler->splitUserRemote($subjectParameters['sharedByFederatedId']);

		$message = $l->t('{user1} invited you to join {roomName} on {remoteServer}');

		$rosParameters = [
			'user1' => [
				'type' => 'user',
				'id' => $sharedById,
				'name' => $subjectParameters['sharedByDisplayName'],
				'server' => $sharedByServer,
			],
			'roomName' => [
				'type' => 'highlight',
				'id' => $subjectParameters['serverUrl'] . '::' . $subjectParameters['roomToken'],
				'name' => $room->getName(),
			],
			'remoteServer' => [
				'type' => 'highlight',
				'id' => $subjectParameters['serverUrl'],
				'name' => $subjectParameters['serverUrl'],
			]
		];

		$acceptAction = $notification->createAction();
		$acceptAction->setParsedLabel($l->t('Accept'));
		$acceptAction->setLink($this->url->linkToOCSRouteAbsolute(
			'spreed.Federation.acceptShare',
			['apiVersion' => 'v1', 'id' => (int)$notification->getObjectId()]
		), IAction::TYPE_POST);
		$acceptAction->setPrimary(true);
		$notification->addParsedAction($acceptAction);

		$declineAction = $notification->createAction();
		$declineAction->setParsedLabel($l->t('Decline'));
		$declineAction->setLink($this->url->linkToOCSRouteAbsolute(
			'spreed.Federation.rejectShare',
			['apiVersion' => 'v1', 'id' => (int)$notification->getObjectId()]
		), IAction::TYPE_DELETE);
		$notification->addParsedAction($declineAction);

		$notification->setRichSubject($l->t('{user1} invited you to a federated conversation'), ['user1' => $rosParameters['user1']]);
		$notification->setRichMessage($message, $rosParameters);

		return $notification;
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param Participant $participant
	 * @param IL10N $l
	 * @return INotification
	 * @throws AlreadyProcessedException
	 * @throws UnknownNotificationException
	 */
	protected function parseChatMessage(INotification $notification, Room $room, Participant $participant, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'chat' && $notification->getObjectType() !== 'reminder') {
			throw new UnknownNotificationException('Unknown object type');
		}

		$messageParameters = $notification->getMessageParameters();
		if (!isset($messageParameters['commentId']) && !isset($messageParameters['proxyId'])) {
			throw new AlreadyProcessedException();
		}

		if (isset($messageParameters['commentId'])) {
			if (!$this->notificationManager->isPreparingPushNotification()
				&& $notification->getObjectType() === 'chat'
				/**
				 * Notification only contains the message id of the target comment
				 * not the one of the reaction, so we can't determine if it was read.
				 * @see Listener::markReactionNotificationsRead()
				 */
				&& $notification->getSubject() !== 'reaction'
				&& ((int)$messageParameters['commentId']) <= $participant->getAttendee()->getLastReadMessage()) {
				// Mark notifications of messages that are read as processed
				throw new AlreadyProcessedException();
			}

			try {
				$comment = $this->commentManager->get($messageParameters['commentId']);
			} catch (NotFoundException) {
				throw new AlreadyProcessedException();
			}

			if ($comment->getObjectType() !== 'chat'
				|| $room->getId() !== (int)$comment->getObjectId()) {
				$this->logger->warning('Ignoring ' . $notification->getSubject() . ' notification for user ' . $notification->getUser() . ' as messages #' . $comment->getId() . ' could not be found for conversation ' . $room->getToken());
				throw new AlreadyProcessedException();
			}

			$message = $this->messageParser->createMessage($room, $participant, $comment, $l);
			$this->messageParser->parseMessage($message, true);

			if (!$message->getVisibility()) {
				throw new AlreadyProcessedException();
			}
		} else {
			try {
				$proxy = $this->proxyCacheMessageMapper->findById($room, $messageParameters['proxyId']);
				$message = $this->messageParser->createMessageFromProxyCache($room, $participant, $proxy, $l);
			} catch (DoesNotExistException) {
				throw new AlreadyProcessedException();
			}
		}

		$subjectParameters = $notification->getSubjectParameters();

		$richSubjectUser = null;
		$isGuest = false;
		if ($subjectParameters['userType'] === Attendee::ACTOR_USERS) {
			$userId = $subjectParameters['userId'];
			$userDisplayName = $this->userManager->getDisplayName($userId);

			if ($userDisplayName !== null) {
				$richSubjectUser = [
					'type' => 'user',
					'id' => $userId,
					'name' => $userDisplayName,
				];
			}
		} elseif ($subjectParameters['userType'] === Attendee::ACTOR_FEDERATED_USERS) {
			try {
				$cloudId = $this->cloudIdManager->resolveCloudId($message->getActorId());
				$richSubjectUser = [
					'type' => 'user',
					'id' => $cloudId->getUser(),
					'name' => $message->getActorDisplayName(),
					'server' => $cloudId->getRemote(),
				];
			} catch (\InvalidArgumentException) {
				$richSubjectUser = [
					'type' => 'highlight',
					'id' => $message->getActorId(),
					'name' => $message->getActorId(),
				];
			}
		} elseif ($subjectParameters['userType'] === Attendee::ACTOR_BOTS) {
			$botId = $subjectParameters['userId'];
			try {
				$bot = $this->botServerMapper->findByUrlHash(substr($botId, strlen(Attendee::ACTOR_BOT_PREFIX)));
				$richSubjectUser = [
					'type' => 'highlight',
					'id' => $botId,
					'name' => $bot->getName() . ' (Bot)',
				];
			} catch (DoesNotExistException $e) {
				$richSubjectUser = [
					'type' => 'highlight',
					'id' => $botId,
					'name' => 'Bot',
				];
			}
		} else {
			$isGuest = true;
		}

		$richSubjectCall = [
			'type' => 'call',
			'id' => (string)$room->getId(),
			'name' => $room->getDisplayName($notification->getUser()),
			'call-type' => $this->getRoomType($room),
			'icon-url' => $this->avatarService->getAvatarUrl($room),
		];

		// Set the link to the specific message
		$urlParams = [
			'token' => $room->getToken(),
			'_fragment' => 'message_' . $message->getMessageId(),
		];
		if (isset($messageParameters['threadId'])) {
			$urlParams['threadId'] = $messageParameters['threadId'];
		}
		$notification->setLink($this->url->linkToRouteAbsolute('spreed.Page.showCall', $urlParams));

		$now = $this->timeFactory->getDateTime();
		$expireDate = $message->getExpirationDateTime();
		if ($expireDate instanceof \DateTimeInterface && $expireDate < $now) {
			throw new AlreadyProcessedException();
		}

		if ($message->getMessageType() === ChatManager::VERB_MESSAGE_DELETED) {
			throw new AlreadyProcessedException();
		}

		$placeholders = $replacements = [];
		foreach ($message->getMessageParameters() as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'user' || $parameter['type'] === 'guest') {
				$replacements[] = '@' . $parameter['name'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$parsedMessage = str_replace($placeholders, $replacements, $message->getMessage());
		if (!$this->notificationManager->isPreparingPushNotification() && !$participant->getAttendee()->isSensitive()) {
			$notification->setParsedMessage($parsedMessage);
			$notification->setRichMessage($message->getMessage(), $message->getMessageParameters());

			// Forward the message ID as well to the clients, so they can quote the message on replies
			$notification->setObject($notification->getObjectType(), $notification->getObjectId() . '/' . $message->getMessageId());
			if (isset($messageParameters['threadId'])) {
				$notification->setObject($notification->getObjectType(), $notification->getObjectId() . '/' . $messageParameters['threadId']);
			}
		}

		$richSubjectParameters = [
			'user' => $richSubjectUser,
			'call' => $richSubjectCall,
		];

		if ($participant->getAttendee()->isSensitive()) {
			// Prevent message preview and conversation name in sensitive conversations

			if ($this->notificationManager->isPreparingPushNotification()) {
				$translatedPrivateConversation = $l->t('Private conversation');

				if ($notification->getSubject() === 'reaction') {
					// TRANSLATORS Someone reacted in a private conversation
					$subject = $translatedPrivateConversation . "\n" . $l->t('Someone reacted');
				} elseif ($notification->getSubject() === 'chat') {
					// TRANSLATORS You received a new message in a private conversation
					$subject = $translatedPrivateConversation . "\n" . $l->t('New message');
				} elseif ($notification->getSubject() === 'reminder') {
					// TRANSLATORS Reminder for a message in a private conversation
					$subject = $translatedPrivateConversation . "\n" . $l->t('Reminder');
				} elseif (str_starts_with($notification->getSubject(), 'mention_')) {
					// TRANSLATORS Someone mentioned you in a private conversation
					$subject = $translatedPrivateConversation . "\n" . $l->t('Someone mentioned you');
				} else {
					// TRANSLATORS There's a notification in a private conversation
					$subject = $translatedPrivateConversation . "\n" . $l->t('Notification');
				}
			} else {
				if ($notification->getSubject() === 'reaction') {
					$subject = $l->t('Someone reacted in a private conversation');
				} elseif ($notification->getSubject() === 'chat') {
					$subject = $l->t('You received a message in a private conversation');
				} elseif ($notification->getSubject() === 'reminder') {
					$subject = $l->t('Reminder in a private conversation');
				} elseif (str_starts_with($notification->getSubject(), 'mention_')) {
					$subject = $l->t('Someone mentioned you in a private conversation');
				} else {
					$subject = $l->t('Notification in a private conversation');
				}
			}

			$richSubjectParameters = [];
		} elseif ($this->notificationManager->isPreparingPushNotification()) {
			$shortenMessage = $this->shortenJsonEncodedMultibyteSave($parsedMessage, 100);
			if ($shortenMessage !== $parsedMessage) {
				$shortenMessage .= '…';
			}
			$richSubjectParameters['message'] = [
				'type' => 'highlight',
				'id' => (string)$message->getMessageId(),
				'name' => $shortenMessage,
			];
			if ($notification->getSubject() === 'reminder') {
				if ($message->getActorId() === $notification->getUser()) {
					// TRANSLATORS Reminder for a message you sent in the conversation {call}
					$subject = $l->t('Reminder: You in {call}') . "\n{message}";
				} elseif ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
					// TRANSLATORS Reminder for a message from {user} in conversation {call}
					$subject = $l->t('Reminder: {user} in {call}') . "\n{message}";
				} elseif ($richSubjectUser) {
					// TRANSLATORS Reminder for a message from {user} in conversation {call}
					$subject = $l->t('Reminder: {user} in {call}') . "\n{message}";
				} elseif (!$isGuest) {
					// TRANSLATORS Reminder for a message from a deleted user in conversation {call}
					$subject = $l->t('Reminder: Deleted user in {call}') . "\n{message}";
				} else {
					try {
						$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
						// TRANSLATORS Reminder for a message from a guest in conversation {call}
						$subject = $l->t('Reminder: {guest} (guest) in {call}') . "\n{message}";
					} catch (ParticipantNotFoundException $e) {
						// TRANSLATORS Reminder for a message from a guest in conversation {call}
						$subject = $l->t('Reminder: Guest in {call}') . "\n{message}";
					}
				}
			} elseif ($notification->getSubject() === 'reaction') {
				$richSubjectParameters['reaction'] = [
					'type' => 'highlight',
					'id' => $subjectParameters['reaction'],
					'name' => $subjectParameters['reaction'],
				];

				if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
					$subject = $l->t('{user} reacted with {reaction}') . "\n{message}";
				} elseif ($richSubjectUser) {
					$subject = $l->t('{user} reacted with {reaction} in {call}') . "\n{message}";
				} elseif (!$isGuest) {
					$subject = $l->t('Deleted user reacted with {reaction} in {call}') . "\n{message}";
				} else {
					try {
						$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
						$subject = $l->t('{guest} (guest) reacted with {reaction} in {call}') . "\n{message}";
					} catch (ParticipantNotFoundException $e) {
						$subject = $l->t('Guest reacted with {reaction} in {call}') . "\n{message}";
					}
				}
			} else {
				if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
					$subject = "{user}\n{message}";
				} elseif ($richSubjectUser) {
					$subject = $l->t('{user} in {call}') . "\n{message}";
				} elseif (!$isGuest) {
					$subject = $l->t('Deleted user in {call}') . "\n{message}";
				} else {
					try {
						$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
						$subject = $l->t('{guest} (guest) in {call}') . "\n{message}";
					} catch (ParticipantNotFoundException $e) {
						$subject = $l->t('Guest in {call}') . "\n{message}";
					}
				}
			}
		} elseif ($notification->getSubject() === 'chat') {
			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$subject = $l->t('{user} sent you a private message');
			} elseif ($richSubjectUser) {
				$subject = $l->t('{user} sent a message in conversation {call}');
			} elseif (!$isGuest) {
				$subject = $l->t('A deleted user sent a message in conversation {call}');
			} else {
				try {
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
					$subject = $l->t('{guest} (guest) sent a message in conversation {call}');
				} catch (ParticipantNotFoundException $e) {
					$subject = $l->t('A guest sent a message in conversation {call}');
				}
			}
		} elseif ($notification->getSubject() === 'reply') {
			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$subject = $l->t('{user} replied to your private message');
			} elseif ($richSubjectUser) {
				$subject = $l->t('{user} replied to your message in conversation {call}');
			} elseif (!$isGuest) {
				$subject = $l->t('A deleted user replied to your message in conversation {call}');
			} else {
				try {
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
					$subject = $l->t('{guest} (guest) replied to your message in conversation {call}');
				} catch (ParticipantNotFoundException $e) {
					$subject = $l->t('A guest replied to your message in conversation {call}');
				}
			}
		} elseif ($notification->getSubject() === 'reminder') {
			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				if ($message->getActorId() === $notification->getUser()) {
					$subject = $l->t('Reminder: You in private conversation {call}');
				} elseif ($room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
					$subject = $l->t('Reminder: A deleted user in private conversation {call}');
				} else {
					$subject = $l->t('Reminder: {user} in private conversation');
				}
			} elseif ($richSubjectUser) {
				if ($message->getActorId() === $notification->getUser()) {
					$subject = $l->t('Reminder: You in conversation {call}');
				} else {
					$subject = $l->t('Reminder: {user} in conversation {call}');
				}
			} elseif (!$isGuest) {
				$subject = $l->t('Reminder: A deleted user in conversation {call}');
			} else {
				try {
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
					$subject = $l->t('Reminder: {guest} (guest) in conversation {call}');
				} catch (ParticipantNotFoundException) {
					$subject = $l->t('Reminder: A guest in conversation {call}');
				}
			}
		} elseif ($notification->getSubject() === 'reaction') {
			$richSubjectParameters['reaction'] = [
				'type' => 'highlight',
				'id' => $subjectParameters['reaction'],
				'name' => $subjectParameters['reaction'],
			];

			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$subject = $l->t('{user} reacted with {reaction} to your private message');
			} elseif ($richSubjectUser) {
				$subject = $l->t('{user} reacted with {reaction} to your message in conversation {call}');
			} elseif (!$isGuest) {
				$subject = $l->t('A deleted user reacted with {reaction} to your message in conversation {call}');
			} else {
				try {
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
					$subject = $l->t('{guest} (guest) reacted with {reaction} to your message in conversation {call}');
				} catch (ParticipantNotFoundException $e) {
					$subject = $l->t('A guest reacted with {reaction} to your message in conversation {call}');
				}
			}
		} elseif ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			$subject = $l->t('{user} mentioned you in a private conversation');
		} elseif ($richSubjectUser) {
			if ($notification->getSubject() === 'mention_group') {
				$groupName = $this->groupManager->getDisplayName($subjectParameters['sourceId']) ?? $subjectParameters['sourceId'];
				$richSubjectParameters['group'] = [
					'type' => 'user-group',
					'id' => $subjectParameters['sourceId'],
					'name' => $groupName,
				];

				$subject = $l->t('{user} mentioned group {group} in conversation {call}');
			} elseif ($notification->getSubject() === 'mention_team') {
				$richSubjectParameters['team'] = $this->getCircle($subjectParameters['sourceId']);
				$subject = $l->t('{user} mentioned team {team} in conversation {call}');
			} elseif ($notification->getSubject() === 'mention_all') {
				$subject = $l->t('{user} mentioned everyone in conversation {call}');
			} else {
				$subject = $l->t('{user} mentioned you in conversation {call}');
			}
		} elseif (!$isGuest) {
			if ($notification->getSubject() === 'mention_group') {
				$groupName = $this->groupManager->getDisplayName($subjectParameters['sourceId']) ?? $subjectParameters['sourceId'];
				$richSubjectParameters['group'] = [
					'type' => 'user-group',
					'id' => $subjectParameters['sourceId'],
					'name' => $groupName,
				];

				$subject = $l->t('A deleted user mentioned group {group} in conversation {call}');
			} elseif ($notification->getSubject() === 'mention_team') {
				$richSubjectParameters['team'] = $this->getCircle($subjectParameters['sourceId']);
				$subject = $l->t('A deleted user mentioned team {team} in conversation {call}');
			} elseif ($notification->getSubject() === 'mention_all') {
				$subject = $l->t('A deleted user mentioned everyone in conversation {call}');
			} else {
				$subject = $l->t('A deleted user mentioned you in conversation {call}');
			}
		} else {
			try {
				$richSubjectParameters['guest'] = $this->getGuestParameter($room, $message->getActorType(), $message->getActorId());
				if ($notification->getSubject() === 'mention_group') {
					$groupName = $this->groupManager->getDisplayName($subjectParameters['sourceId']) ?? $subjectParameters['sourceId'];
					$richSubjectParameters['group'] = [
						'type' => 'user-group',
						'id' => $subjectParameters['sourceId'],
						'name' => $groupName,
					];

					$subject = $l->t('{guest} (guest) mentioned group {group} in conversation {call}');
				} elseif ($notification->getSubject() === 'mention_team') {
					$richSubjectParameters['team'] = $this->getCircle($subjectParameters['sourceId']);
					$subject = $l->t('{guest} (guest) mentioned team {team} in conversation {call}');
				} elseif ($notification->getSubject() === 'mention_all') {
					$subject = $l->t('{guest} (guest) mentioned everyone in conversation {call}');
				} else {
					$subject = $l->t('{guest} (guest) mentioned you in conversation {call}');
				}
			} catch (ParticipantNotFoundException $e) {
				if ($notification->getSubject() === 'mention_group') {
					$groupName = $this->groupManager->getDisplayName($subjectParameters['sourceId']) ?? $subjectParameters['sourceId'];
					$richSubjectParameters['group'] = [
						'type' => 'user-group',
						'id' => $subjectParameters['sourceId'],
						'name' => $groupName,
					];

					$subject = $l->t('A guest mentioned group {group} in conversation {call}');
				} elseif ($notification->getSubject() === 'mention_team') {
					$richSubjectParameters['team'] = $this->getCircle($subjectParameters['sourceId']);
					$subject = $l->t('A guest mentioned team {team} in conversation {call}');
				} elseif ($notification->getSubject() === 'mention_all') {
					$subject = $l->t('A guest mentioned everyone in conversation {call}');
				} else {
					$subject = $l->t('A guest mentioned you in conversation {call}');
				}
			}
		}

		if ($notification->getObjectType() === 'reminder') {
			$notification = $this->addActionButton($notification, 'message_view', $l->t('View message'));

			$action = $notification->createAction();
			$action->setLabel('reminder_dismiss')
				->setParsedLabel($l->t('Dismiss reminder'))
				->setLink(
					$this->url->linkToOCSRouteAbsolute(
						'spreed.Chat.deleteReminder',
						[
							'apiVersion' => 'v1',
							'token' => $room->getToken(),
							'messageId' => $message->getMessageId(),
						]
					),
					IAction::TYPE_DELETE
				);

			$notification->addParsedAction($action);
		} else {
			$notification = $this->addActionButton($notification, 'chat_view', $l->t('View chat'), false);
		}

		if (array_key_exists('user', $richSubjectParameters) && $richSubjectParameters['user'] === null) {
			unset($richSubjectParameters['user']);
		}

		$placeholders = $replacements = [];
		foreach ($richSubjectParameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$notification->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richSubjectParameters);

		return $notification;
	}

	/**
	 * @param Room $room
	 * @param Attendee::ACTOR_* $actorType
	 * @param string $actorId
	 * @return array
	 * @throws ParticipantNotFoundException
	 */
	protected function getGuestParameter(Room $room, string $actorType, string $actorId): array {
		if (!in_array($actorType, [Attendee::ACTOR_GUESTS, Attendee::ACTOR_EMAILS], true)) {
			throw new ParticipantNotFoundException('Not a guest actor type');
		}

		$participant = $this->participantService->getParticipantByActor($room, $actorType, $actorId);
		$name = $participant->getAttendee()->getDisplayName();
		if (trim($name) === '') {
			throw new ParticipantNotFoundException('Empty name');
		}

		return [
			'type' => 'guest',
			'id' => $actorId,
			'name' => $name,
		];
	}

	/**
	 * @param Room $room
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRoomType(Room $room): string {
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
			case Room::TYPE_ONE_TO_ONE_FORMER:
				return 'one2one';
			case Room::TYPE_GROUP:
			case Room::TYPE_NOTE_TO_SELF:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param IL10N $l
	 * @return INotification
	 * @throws AlreadyProcessedException
	 * @throws UnknownNotificationException
	 */
	protected function parseInvitation(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'room') {
			throw new UnknownNotificationException('Unknown object type');
		}

		$parameters = $notification->getSubjectParameters();
		$uid = $parameters['actorId'] ?? $parameters[0];

		$userDisplayName = $this->userManager->getDisplayName($uid);
		if ($userDisplayName === null) {
			throw new AlreadyProcessedException();
		}

		$roomName = $room->getDisplayName($notification->getUser());
		if (\in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			$subject = $l->t('{user} invited you to a group conversation: {call}');
			if ($this->participantService->hasActiveSessionsInCall($room)) {
				$notification = $this->addActionButton($notification, 'call_view', $l->t('Join call'), true, true);
			} else {
				$notification = $this->addActionButton($notification, 'chat_view', $l->t('View chat'), false);
			}

			$notification
				->setParsedSubject(str_replace(['{user}', '{call}'], [$userDisplayName, $roomName], $subject))
				->setRichSubject(
					$subject, [
						'user' => [
							'type' => 'user',
							'id' => $uid,
							'name' => $userDisplayName,
						],
						'call' => [
							'type' => 'call',
							'id' => (string)$room->getId(),
							'name' => $roomName,
							'call-type' => $this->getRoomType($room),
							'icon-url' => $this->avatarService->getAvatarUrl($room),
						],
					]
				);
		} else {
			throw new AlreadyProcessedException();
		}

		return $notification;
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param IL10N $l
	 * @return INotification
	 * @throws AlreadyProcessedException
	 * @throws UnknownNotificationException
	 */
	protected function parseCall(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'call') {
			throw new UnknownNotificationException('Unknown object type');
		}

		$roomName = $room->getDisplayName($notification->getUser());
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			$parameters = $notification->getSubjectParameters();
			$calleeId = $parameters['callee']; // TODO can be null on federated conversations, so needs to be changed once we have federated 1-1
			$userDisplayName = $this->userManager->getDisplayName($calleeId);
			if ($userDisplayName !== null) {
				if ($this->notificationManager->isPreparingPushNotification() || $this->participantService->hasActiveSessionsInCall($room)) {
					$notification = $this->addActionButton($notification, 'call_view', $l->t('Answer call'), true, true);
					$subject = $l->t('{user} would like to talk with you');
				} else {
					$notification = $this->addActionButton($notification, 'call_view', $l->t('Call back'));
					$subject = $l->t('You missed a call from {user}');
				}

				$notification
					->setParsedSubject(str_replace('{user}', $userDisplayName, $subject))
					->setRichSubject(
						$subject, [
							'user' => [
								'type' => 'user',
								'id' => $calleeId,
								'name' => $userDisplayName,
							],
							'call' => [
								'type' => 'call',
								'id' => (string)$room->getId(),
								'name' => $roomName,
								'call-type' => $this->getRoomType($room),
								'icon-url' => $this->avatarService->getAvatarUrl($room),
							],
						]
					);
			} else {
				throw new AlreadyProcessedException();
			}
		} elseif ($room->getObjectId() === Room::OBJECT_ID_PHONE_INCOMING
			&& in_array($room->getObjectType(), [Room::OBJECT_TYPE_PHONE_PERSIST, Room::OBJECT_TYPE_PHONE_TEMPORARY, Room::OBJECT_TYPE_PHONE_LEGACY], true)) {
			if ($this->notificationManager->isPreparingPushNotification()
				|| (!$room->isFederatedConversation() && $this->participantService->hasActiveSessionsInCall($room))
				|| ($room->isFederatedConversation() && $room->getActiveSince())
			) {
				$notification = $this->addActionButton($notification, 'call_view', $l->t('Accept call'), true, true);
				$subject = $l->t('Incoming phone call from {call}');
			} else {
				$notification = $this->addActionButton($notification, 'chat_view', $l->t('View chat'), false);
				$subject = $l->t('You missed a phone call from {call}');
			}

			$notification
				->setParsedSubject(str_replace('{call}', $roomName, $subject))
				->setRichSubject(
					$subject, [
						'call' => [
							'type' => 'call',
							'id' => (string)$room->getId(),
							'name' => $roomName,
							'call-type' => $this->getRoomType($room),
							'icon-url' => $this->avatarService->getAvatarUrl($room),
						],
					]
				);
		} elseif (\in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			if ($this->notificationManager->isPreparingPushNotification()
				|| (!$room->isFederatedConversation() && $this->participantService->hasActiveSessionsInCall($room))
				|| ($room->isFederatedConversation() && $room->getActiveSince())
			) {
				$notification = $this->addActionButton($notification, 'call_view', $l->t('Join call'), true, true);
				$subject = $l->t('A group call has started in {call}');
			} else {
				$notification = $this->addActionButton($notification, 'chat_view', $l->t('View chat'), false);
				$subject = $l->t('You missed a group call in {call}');
			}

			$notification
				->setParsedSubject(str_replace('{call}', $roomName, $subject))
				->setRichSubject(
					$subject, [
						'call' => [
							'type' => 'call',
							'id' => (string)$room->getId(),
							'name' => $roomName,
							'call-type' => $this->getRoomType($room),
							'icon-url' => $this->avatarService->getAvatarUrl($room),
						],
					]
				);
		} else {
			throw new AlreadyProcessedException();
		}

		return $notification;
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param IL10N $l
	 * @return INotification
	 * @throws AlreadyProcessedException
	 * @throws UnknownNotificationException
	 */
	protected function parsePasswordRequest(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'call') {
			throw new UnknownNotificationException('Unknown object type');
		}

		try {
			$share = $this->shareManager->getShareByToken($room->getObjectId());
		} catch (ShareNotFound $e) {
			throw new AlreadyProcessedException();
		}

		try {
			$file = [
				'type' => 'highlight',
				'id' => (string)$share->getNodeId(),
				'name' => $share->getNode()->getName(),
			];
		} catch (\OCP\Files\NotFoundException $e) {
			throw new AlreadyProcessedException();
		}

		$callIsActive = $this->notificationManager->isPreparingPushNotification() || $this->participantService->hasActiveSessionsInCall($room);
		if ($callIsActive) {
			$notification = $this->addActionButton($notification, 'call_view', $l->t('Answer call'), true, true);
		} else {
			$notification = $this->addActionButton($notification, 'call_view', $l->t('Call back'));
		}

		if ($share->getShareType() === IShare::TYPE_EMAIL) {
			$sharedWith = $share->getSharedWith();
			if ($callIsActive) {
				$subject = $l->t('{email} is requesting the password to access {file}');
			} else {
				$subject = $l->t('{email} tried to request the password to access {file}');
			}

			$notification
				->setParsedSubject(str_replace(['{email}', '{file}'], [$sharedWith, $file['name']], $subject))
				->setRichSubject($subject, [
					'email' => [
						'type' => 'email',
						'id' => $sharedWith,
						'name' => $sharedWith,
					],
					'file' => $file,
				]
				);
		} else {
			if ($callIsActive) {
				$subject = $l->t('Someone is requesting the password to access {file}');
			} else {
				$subject = $l->t('Someone tried to request the password to access {file}');
			}

			$notification
				->setParsedSubject(str_replace('{file}', $file['name'], $subject))
				->setRichSubject($subject, ['file' => $file]);
		}

		return $notification;
	}

	protected function addActionButton(INotification $notification, string $labelKey, string $label, bool $primary = true, bool $directCallLink = false): INotification {
		$link = $notification->getLink();
		if ($directCallLink) {
			$link .= '#direct-call';
		}

		$action = $notification->createAction();
		$action->setLabel($labelKey)
			->setParsedLabel($label)
			->setLink($link, IAction::TYPE_WEB)
			->setPrimary($primary);

		$notification->addParsedAction($action);

		return $notification;
	}

	/**
	 * @throws UnknownNotificationException
	 */
	protected function parseHostedSignalingServer(INotification $notification, IL10N $l): INotification {
		$action = $notification->createAction();
		$action->setLabel('open_settings')
			->setParsedLabel($l->t('Open settings'))
			->setLink($notification->getLink(), IAction::TYPE_WEB)
			->setPrimary(true);

		$parsedParameters = [];
		$icon = '';
		switch ($notification->getSubject()) {
			case 'added':
				$subject = $l->t('Hosted signaling server added');
				$message = $l->t('The hosted signaling server is now configured and will be used.');
				$icon = $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/video.svg'));
				break;
			case 'removed':
				$subject = $l->t('Hosted signaling server removed');
				$message = $l->t('The hosted signaling server was removed and will not be used anymore.');
				$icon = $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/video-off.svg'));
				break;
			case 'changed-status':
				$subject = $l->t('Hosted signaling server changed');
				$message = $l->t('The hosted signaling server account has changed the status from "{oldstatus}" to "{newstatus}".');
				$icon = $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/video-switch.svg'));

				$parameters = $notification->getSubjectParameters();
				$parsedParameters = [
					'oldstatus' => $this->createHPBParameter($parameters['oldstatus'], $l),
					'newstatus' => $this->createHPBParameter($parameters['newstatus'], $l),
				];
				break;
			default:
				throw new UnknownNotificationException('Unknown subject');
		}

		return $notification
			->setRichSubject($subject)
			->setRichMessage($message, $parsedParameters)
			->setIcon($icon)
			->addParsedAction($action);
	}

	protected function hostedHPBStatusToLabel(string $status, IL10N $l): string {
		return match ($status) {
			'pending' => $l->t('pending'),
			'active' => $l->t('active'),
			'expired' => $l->t('expired'),
			'blocked' => $l->t('blocked'),
			'error' => $l->t('error'),
			default => $status,
		};
	}

	protected function createHPBParameter(string $status, IL10N $l): array {
		return [
			'type' => 'highlight',
			'id' => $status,
			'name' => $this->hostedHPBStatusToLabel($status, $l),
		];
	}

	protected function parseCertificateExpiration(INotification $notification, IL10N $l): INotification {
		$subjectParameters = $notification->getSubjectParameters();

		$host = $subjectParameters['host'];
		$daysToExpire = $subjectParameters['days_to_expire'];

		if ($daysToExpire > 0) {
			$subject = $l->t('The certificate of {host} expires in {days} days');
		} else {
			$subject = $l->t('The certificate of {host} expired');
		}

		$subject = str_replace(
			['{host}', '{days}'],
			[$host, $daysToExpire],
			$subject
		);

		$notification->setParsedSubject($subject);

		return $notification;
	}

	protected function getCircle(string $circleId): array {
		if (!$this->appManager->isEnabledForUser('circles')) {
			return [
				'type' => 'highlight',
				'id' => $circleId,
				'name' => $circleId,
			];
		}

		if (!isset($this->circleNames[$circleId])) {
			$this->loadCircleDetails($circleId);
		}

		if (!isset($this->circleNames[$circleId])) {
			return [
				'type' => 'highlight',
				'id' => $circleId,
				'name' => $circleId,
			];
		}

		return [
			'type' => 'circle',
			'id' => $circleId,
			'name' => $this->circleNames[$circleId],
			'link' => $this->circleLinks[$circleId],
		];
	}

	protected function loadCircleDetails(string $circleId): void {
		try {
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSuperSession();
			$circle = $circlesManager->getCircle($circleId);

			$this->circleNames[$circleId] = $circle->getDisplayName();
			$this->circleLinks[$circleId] = $circle->getUrl();
		} catch (\Exception) {
		} finally {
			$circlesManager?->stopSession();
		}
	}
}
