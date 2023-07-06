<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Notification;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\HintException;
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
use OCP\RichObjectStrings\Definitions;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

class Notifier implements INotifier {
	protected IFactory $lFactory;
	protected IURLGenerator $url;
	protected Config $config;
	protected IUserManager $userManager;
	protected IGroupManager $groupManager;
	protected GuestManager $guestManager;
	private IShareManager $shareManager;
	protected Manager $manager;
	protected ParticipantService $participantService;
	protected AvatarService $avatarService;
	protected INotificationManager $notificationManager;
	protected ICommentsManager $commentManager;
	protected MessageParser $messageParser;
	protected IURLGenerator $urlGenerator;
	protected IRootFolder $rootFolder;
	protected ITimeFactory $timeFactory;
	protected Definitions $definitions;
	protected AddressHandler $addressHandler;

	/** @var Room[] */
	protected array $rooms = [];
	/** @var Participant[][] */
	protected array $participants = [];

	public function __construct(
		IFactory $lFactory,
		IURLGenerator $url,
		Config $config,
		IUserManager $userManager,
		IGroupManager $groupManager,
		GuestManager $guestManager,
		IShareManager $shareManager,
		Manager $manager,
		ParticipantService $participantService,
		AvatarService $avatarService,
		INotificationManager $notificationManager,
		CommentsManager $commentManager,
		MessageParser $messageParser,
		IURLGenerator $urlGenerator,
		IRootFolder $rootFolder,
		ITimeFactory $timeFactory,
		Definitions $definitions,
		AddressHandler $addressHandler,
	) {
		$this->lFactory = $lFactory;
		$this->url = $url;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->guestManager = $guestManager;
		$this->shareManager = $shareManager;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->avatarService = $avatarService;
		$this->notificationManager = $notificationManager;
		$this->commentManager = $commentManager;
		$this->messageParser = $messageParser;
		$this->urlGenerator = $urlGenerator;
		$this->rootFolder = $rootFolder;
		$this->timeFactory = $timeFactory;
		$this->definitions = $definitions;
		$this->addressHandler = $addressHandler;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'talk';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
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
				$room = $this->manager->getRoomById((int) $objectId);
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
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Incorrect app');
		}

		$userId = $notification->getUser();
		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser || $this->config->isDisabledForUser($user)) {
			throw new AlreadyProcessedException();
		}

		$l = $this->lFactory->get(Application::APP_ID, $languageCode);

		if ($notification->getObjectType() === 'hosted-signaling-server') {
			return $this->parseHostedSignalingServer($notification, $l);
		}

		if ($notification->getObjectType() === 'remote_talk_share') {
			return $this->parseRemoteInvitationMessage($notification, $l);
		}

		try {
			$room = $this->getRoom($notification->getObjectId(), $userId);
		} catch (RoomNotFoundException $e) {
			// Room does not exist
			throw new AlreadyProcessedException();
		}

		if ($this->notificationManager->isPreparingPushNotification() && $notification->getSubject() === 'call') {
			// Skip the participant check when we generate push notifications
			// we just looped over the participants to create the notification,
			// they can not be removed between these 2 steps, but we can save
			// n queries.
		} else {
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

		$subject = $notification->getSubject();
		if ($subject === 'record_file_stored' || $subject === 'transcript_file_stored' || $subject === 'transcript_failed') {
			return $this->parseStoredRecording($notification, $room, $participant, $l);
		}
		if ($subject === 'record_file_store_fail') {
			return $this->parseStoredRecordingFail($notification, $room, $participant, $l);
		}
		if ($subject === 'invitation') {
			return $this->parseInvitation($notification, $room, $l);
		}
		if ($subject === 'call') {
			if ($room->getLobbyState() !== Webinary::LOBBY_NONE &&
				$participant instanceof Participant &&
				!($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				// User is blocked by the lobby, remove notification
				throw new AlreadyProcessedException();
			}

			if ($room->getObjectType() === 'share:password') {
				return $this->parsePasswordRequest($notification, $room, $l);
			}
			return $this->parseCall($notification, $room, $l);
		}
		if ($subject === 'reply' || $subject === 'mention' || $subject === 'mention_direct' || $subject === 'mention_group' || $subject === 'mention_all' || $subject === 'chat' || $subject === 'reaction') {
			if ($room->getLobbyState() !== Webinary::LOBBY_NONE &&
				$participant instanceof Participant &&
				!($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				// User is blocked by the lobby, remove notification
				throw new AlreadyProcessedException();
			}

			return $this->parseChatMessage($notification, $room, $participant, $l);
		}

		$this->notificationManager->markProcessed($notification);
		throw new \InvalidArgumentException('Unknown subject');
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
		IL10N $l
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
						'id' => $room->getId(),
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
		IL10N $l
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
				$this->urlGenerator->linkToOCSRouteAbsolute(
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
				$this->urlGenerator->linkToOCSRouteAbsolute(
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
		} else {
			$subject = $l->t('Failed to transcript call recording');
			$message = $l->t('The server failed to transcript the recording at {file} for the call in {call}. Please reach out to the administration.');
		}

		$notification
			->setRichSubject($subject)
			->setRichMessage(
				$message,
				[
					'call' => [
						'type' => 'call',
						'id' => $room->getId(),
						'name' => $room->getDisplayName($participant->getAttendee()->getActorId()),
						'call-type' => $this->getRoomType($room),
						'icon-url' => $this->avatarService->getAvatarUrl($room),
					],
					'file' => [
						'type' => 'file',
						'id' => $file->getId(),
						'name' => $file->getName(),
						'path' => $path,
						'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $file->getId()]),
					],
				]);

		if ($notification->getSubject() !== 'transcript_failed') {
			$notification->addParsedAction($shareAction);
			$notification->addParsedAction($dismissAction);
		}

		return $notification;
	}

	/**
	 * @throws HintException
	 */
	protected function parseRemoteInvitationMessage(INotification $notification, IL10N $l): INotification {
		$subjectParameters = $notification->getSubjectParameters();

		[$sharedById, $sharedByServer] = $this->addressHandler->splitUserRemote($subjectParameters['sharedByFederatedId']);

		$message = $l->t('{user1} shared room {roomName} on {remoteServer} with you');

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
				'name' => $subjectParameters['roomName'],
			],
			'remoteServer' => [
				'type' => 'highlight',
				'id' => $subjectParameters['serverUrl'],
				'name' => $subjectParameters['serverUrl'],
			]
		];

		$placeholders = $replacements = [];
		foreach ($rosParameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder .'}';
			if ($parameter['type'] === 'user') {
				$replacements[] = '@' . $parameter['name'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$notification->setParsedSubject(str_replace($placeholders, $replacements, $message));
		$notification->setRichSubject($message, $rosParameters);

		return $notification;
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param Participant $participant
	 * @param IL10N $l
	 * @return INotification
	 * @throws \InvalidArgumentException
	 */
	protected function parseChatMessage(INotification $notification, Room $room, Participant $participant, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'chat') {
			throw new \InvalidArgumentException('Unknown object type');
		}

		$subjectParameters = $notification->getSubjectParameters();

		$richSubjectUser = null;
		$isGuest = false;
		if ($subjectParameters['userType'] === 'users') {
			$userId = $subjectParameters['userId'];
			$userDisplayName = $this->userManager->getDisplayName($userId);

			if ($userDisplayName !== null) {
				$richSubjectUser = [
					'type' => 'user',
					'id' => $userId,
					'name' => $userDisplayName,
				];
			}
		} else {
			$isGuest = true;
		}

		$richSubjectCall = [
			'type' => 'call',
			'id' => $room->getId(),
			'name' => $room->getDisplayName($notification->getUser()),
			'call-type' => $this->getRoomType($room),
			'icon-url' => $this->avatarService->getAvatarUrl($room),
		];

		$messageParameters = $notification->getMessageParameters();
		if (!isset($messageParameters['commentId'])) {
			throw new AlreadyProcessedException();
		}

		try {
			$comment = $this->commentManager->get($messageParameters['commentId']);
		} catch (NotFoundException $e) {
			throw new AlreadyProcessedException();
		}

		$message = $this->messageParser->createMessage($room, $participant, $comment, $l);
		$this->messageParser->parseMessage($message);

		if (!$message->getVisibility()) {
			throw new AlreadyProcessedException();
		}

		$now = $this->timeFactory->getDateTime();
		$expireDate = $message->getComment()->getExpireDate();
		if ($expireDate instanceof \DateTime && $expireDate < $now) {
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
		if (!$this->notificationManager->isPreparingPushNotification()) {
			$notification->setParsedMessage($parsedMessage);
			$notification->setRichMessage($message->getMessage(), $message->getMessageParameters());

			// Forward the message ID as well to the clients, so they can quote the message on replies
			$notification->setObject('chat', $notification->getObjectId() . '/' . $comment->getId());
		}

		$richSubjectParameters = [
			'user' => $richSubjectUser,
			'call' => $richSubjectCall,
		];

		if ($this->notificationManager->isPreparingPushNotification()) {
			$shortenMessage = $this->shortenJsonEncodedMultibyteSave($parsedMessage, 100);
			if ($shortenMessage !== $parsedMessage) {
				$shortenMessage .= '…';
			}
			$richSubjectParameters['message'] = [
				'type' => 'highlight',
				'id' => $message->getComment()->getId(),
				'name' => $shortenMessage,
			];
			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$subject = "{user}\n{message}";
			} elseif ($richSubjectUser) {
				$subject = $l->t('{user} in {call}') . "\n{message}";
			} elseif (!$isGuest) {
				$subject = $l->t('Deleted user in {call}') . "\n{message}";
			} else {
				try {
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $comment->getActorId());
					$subject = $l->t('{guest} (guest) in {call}') . "\n{message}";
				} catch (ParticipantNotFoundException $e) {
					$subject = $l->t('Guest in {call}') . "\n{message}";
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
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $comment->getActorId());
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
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $comment->getActorId());
					$subject = $l->t('{guest} (guest) replied to your message in conversation {call}');
				} catch (ParticipantNotFoundException $e) {
					$subject = $l->t('A guest replied to your message in conversation {call}');
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
					$richSubjectParameters['guest'] = $this->getGuestParameter($room, $comment->getActorId());
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
			} elseif ($notification->getSubject() === 'mention_all') {
				$subject = $l->t('A deleted user mentioned everyone in conversation {call}');
			} else {
				$subject = $l->t('A deleted user mentioned you in conversation {call}');
			}
		} else {
			try {
				$richSubjectParameters['guest'] = $this->getGuestParameter($room, $comment->getActorId());
				if ($notification->getSubject() === 'mention_group') {
					$groupName = $this->groupManager->getDisplayName($subjectParameters['sourceId']) ?? $subjectParameters['sourceId'];
					$richSubjectParameters['group'] = [
						'type' => 'user-group',
						'id' => $subjectParameters['sourceId'],
						'name' => $groupName,
					];

					$subject = $l->t('{guest} (guest) mentioned group {group} in conversation {call}');
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
				} elseif ($notification->getSubject() === 'mention_all') {
					$subject = $l->t('A guest mentioned everyone in conversation {call}');
				} else {
					$subject = $l->t('A guest mentioned you in conversation {call}');
				}
			}
		}
		$notification = $this->addActionButton($notification, $l->t('View chat'), false);

		if ($richSubjectParameters['user'] === null) {
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
	 * @param string $actorId
	 * @return array
	 * @throws ParticipantNotFoundException
	 */
	protected function getGuestParameter(Room $room, string $actorId): array {
		$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_GUESTS, $actorId);
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
		return match ($room->getType()) {
			Room::TYPE_ONE_TO_ONE, Room::TYPE_ONE_TO_ONE_FORMER => 'one2one',
			Room::TYPE_GROUP => 'group',
			Room::TYPE_PUBLIC => 'public',
			default => throw new \InvalidArgumentException('Unknown room type'),
		};
	}

	/**
	 * @param INotification $notification
	 * @param Room $room
	 * @param IL10N $l
	 * @return INotification
	 * @throws \InvalidArgumentException
	 * @throws AlreadyProcessedException
	 */
	protected function parseInvitation(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'room') {
			throw new \InvalidArgumentException('Unknown object type');
		}

		$parameters = $notification->getSubjectParameters();
		$uid = $parameters['actorId'] ?? $parameters[0];

		$userDisplayName = $this->userManager->getDisplayName($uid);
		if ($userDisplayName === null) {
			throw new AlreadyProcessedException();
		}

		$roomName = $room->getDisplayName($notification->getUser());
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			$subject = $l->t('{user} invited you to a private conversation');
			if ($this->participantService->hasActiveSessionsInCall($room)) {
				$notification = $this->addActionButton($notification, $l->t('Join call'));
			} else {
				$notification = $this->addActionButton($notification, $l->t('View chat'), false);
			}

			$notification
				->setParsedSubject(str_replace('{user}', $userDisplayName, $subject))
				->setRichSubject(
					$subject, [
						'user' => [
							'type' => 'user',
							'id' => $uid,
							'name' => $userDisplayName,
						],
						'call' => [
							'type' => 'call',
							'id' => $room->getId(),
							'name' => $roomName,
							'call-type' => $this->getRoomType($room),
							'icon-url' => $this->avatarService->getAvatarUrl($room),
						],
					]
				);
		} elseif (\in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			$subject = $l->t('{user} invited you to a group conversation: {call}');
			if ($this->participantService->hasActiveSessionsInCall($room)) {
				$notification = $this->addActionButton($notification, $l->t('Join call'));
			} else {
				$notification = $this->addActionButton($notification, $l->t('View chat'), false);
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
							'id' => $room->getId(),
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
	 * @throws \InvalidArgumentException
	 * @throws AlreadyProcessedException
	 */
	protected function parseCall(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'call') {
			throw new \InvalidArgumentException('Unknown object type');
		}

		$roomName = $room->getDisplayName($notification->getUser());
		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			$parameters = $notification->getSubjectParameters();
			$calleeId = $parameters['callee'];
			$userDisplayName = $this->userManager->getDisplayName($calleeId);
			if ($userDisplayName !== null) {
				if ($this->notificationManager->isPreparingPushNotification() || $this->participantService->hasActiveSessionsInCall($room)) {
					$notification = $this->addActionButton($notification, $l->t('Answer call'));
					$subject = $l->t('{user} would like to talk with you');
				} else {
					$notification = $this->addActionButton($notification, $l->t('Call back'));
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
								'id' => $room->getId(),
								'name' => $roomName,
								'call-type' => $this->getRoomType($room),
								'icon-url' => $this->avatarService->getAvatarUrl($room),
							],
						]
					);
			} else {
				throw new AlreadyProcessedException();
			}
		} elseif (\in_array($room->getType(), [Room::TYPE_GROUP, Room::TYPE_PUBLIC], true)) {
			if ($this->notificationManager->isPreparingPushNotification() || $this->participantService->hasActiveSessionsInCall($room)) {
				$notification = $this->addActionButton($notification, $l->t('Join call'));
				$subject = $l->t('A group call has started in {call}');
			} else {
				$notification = $this->addActionButton($notification, $l->t('View chat'), false);
				$subject = $l->t('You missed a group call in {call}');
			}

			$notification
				->setParsedSubject(str_replace('{call}', $roomName, $subject))
				->setRichSubject(
					$subject, [
						'call' => [
							'type' => 'call',
							'id' => $room->getId(),
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
	 * @throws \InvalidArgumentException
	 * @throws AlreadyProcessedException
	 */
	protected function parsePasswordRequest(INotification $notification, Room $room, IL10N $l): INotification {
		if ($notification->getObjectType() !== 'call') {
			throw new \InvalidArgumentException('Unknown object type');
		}

		try {
			$share = $this->shareManager->getShareByToken($room->getObjectId());
		} catch (ShareNotFound $e) {
			throw new AlreadyProcessedException();
		}

		try {
			$file = [
				'type' => 'highlight',
				'id' => $share->getNodeId(),
				'name' => $share->getNode()->getName(),
			];
		} catch (\OCP\Files\NotFoundException $e) {
			throw new AlreadyProcessedException();
		}

		$callIsActive = $this->notificationManager->isPreparingPushNotification() || $this->participantService->hasActiveSessionsInCall($room);
		if ($callIsActive) {
			$notification = $this->addActionButton($notification, $l->t('Answer call'));
		} else {
			$notification = $this->addActionButton($notification, $l->t('Call back'));
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

	protected function addActionButton(INotification $notification, string $label, bool $primary = true): INotification {
		$action = $notification->createAction();
		$action->setLabel($label)
			->setParsedLabel($label)
			->setLink($notification->getLink(), IAction::TYPE_WEB)
			->setPrimary($primary);

		$notification->addParsedAction($action);

		return $notification;
	}

	protected function parseHostedSignalingServer(INotification $notification, IL10N $l): INotification {
		$action = $notification->createAction();
		$action->setLabel('open_settings')
			->setParsedLabel($l->t('Open settings'))
			->setLink($notification->getLink(), IAction::TYPE_WEB)
			->setPrimary(true);

		switch ($notification->getSubject()) {
			case 'added':
				$subject = $l->t('The hosted signaling server is now configured and will be used.');
				break;
			case 'removed':
				$subject = $l->t('The hosted signaling server was removed and will not be used anymore.');
				break;
			case 'changed-status':
				$subject = $l->t('The hosted signaling server account has changed the status from "{oldstatus}" to "{newstatus}".');

				$parameters = $notification->getSubjectParameters();
				$subject = str_replace(
					['{oldstatus}', '{newstatus}'],
					[$parameters['oldstatus'], $parameters['newstatus']],
					$subject
				);
				break;
			default:
				throw new \InvalidArgumentException('Unknown subject');
		}

		return $notification
			->setParsedSubject($subject)
			->setIcon($notification->getIcon())
			->addParsedAction($action);
	}
}
