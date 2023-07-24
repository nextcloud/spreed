<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Chat\SystemMessage;

use DateInterval;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\AddParticipantsEvent;
use OCA\Talk\Events\AlreadySharedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\ModifyEveryoneEvent;
use OCA\Talk\Events\ModifyLobbyEvent;
use OCA\Talk\Events\ModifyParticipantEvent;
use OCA\Talk\Events\ModifyRoomEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Share\RoomShareProvider;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected IRequest $request,
		protected ChatManager $chatManager,
		protected TalkSession $talkSession,
		protected ISession $session,
		protected IUserSession $userSession,
		protected ITimeFactory $timeFactory,
	) {
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(Room::EVENT_BEFORE_SESSION_JOIN_CALL, self::class . '::sendSystemMessageAboutBeginOfCall');
		$dispatcher->addListener(Room::EVENT_AFTER_SESSION_LEAVE_CALL, self::class . '::sendSystemMessageAboutCallLeft');
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_CREATE, self::class . '::sendSystemMessageAboutConversationCreated');
		$dispatcher->addListener(Room::EVENT_AFTER_NAME_SET, self::class . '::sendSystemMessageAboutConversationRenamed');
		$dispatcher->addListener(Room::EVENT_AFTER_DESCRIPTION_SET, self::class . '::sendSystemMessageAboutRoomDescriptionChanges');
		$dispatcher->addListener(Room::EVENT_AFTER_PASSWORD_SET, self::class . '::sendSystemMessageAboutRoomPassword');
		$dispatcher->addListener(Room::EVENT_AFTER_TYPE_SET, self::class . '::sendSystemGuestPermissionsMessage');
		$dispatcher->addListener(Room::EVENT_AFTER_READONLY_SET, self::class . '::sendSystemReadOnlyMessage');
		$dispatcher->addListener(Room::EVENT_AFTER_LISTABLE_SET, self::class . '::sendSystemListableMessage');
		$dispatcher->addListener(Room::EVENT_AFTER_LOBBY_STATE_SET, self::class . '::sendSystemLobbyMessage');
		$dispatcher->addListener(Room::EVENT_AFTER_USERS_ADD, self::class . '::addSystemMessageUserAdded');
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, self::class . '::sendSystemMessageUserRemoved');
		$dispatcher->addListener(Room::EVENT_AFTER_PARTICIPANT_TYPE_SET, self::class . '::sendSystemMessageAboutPromoteOrDemoteModerator');
		$dispatcher->addListener(BeforeShareCreatedEvent::class, self::class . '::setShareExpiration');
		$dispatcher->addListener(ShareCreatedEvent::class, self::class . '::fixMimeTypeOfVoiceMessage');
		$dispatcher->addListener(RoomShareProvider::EVENT_SHARE_FILE_AGAIN, self::class . '::fixMimeTypeOfVoiceMessage');
		$dispatcher->addListener(Room::EVENT_AFTER_SET_MESSAGE_EXPIRATION, self::class . '::afterSetMessageExpiration');
		$dispatcher->addListener(Room::EVENT_AFTER_SET_CALL_RECORDING, self::class . '::setCallRecording');
		$dispatcher->addListener(Room::EVENT_AFTER_AVATAR_SET, self::class . '::avatarChanged');
	}

	public static function sendSystemMessageAboutBeginOfCall(ModifyParticipantEvent $event): void {
		$room = $event->getRoom();
		$listener = Server::get(self::class);
		$participantService = Server::get(ParticipantService::class);

		if ($participantService->hasActiveSessionsInCall($room)) {
			$listener->sendSystemMessage($room, 'call_joined', [], $event->getParticipant());
		} else {
			$listener->sendSystemMessage($room, 'call_started', [], $event->getParticipant());
		}
	}

	public static function sendSystemMessageAboutCallLeft(ModifyParticipantEvent $event): void {
		if ($event instanceof ModifyEveryoneEvent) {
			// No individual system message if the call is ended for everyone
			return;
		}

		if ($event->getNewValue() === $event->getOldValue()) {
			return;
		}

		$room = $event->getRoom();

		$session = $event->getParticipant()->getSession();
		if (!$session instanceof Session) {
			// This happens in case the user was kicked/lobbied
			return;
		}

		$listener = Server::get(self::class);

		$listener->sendSystemMessage($room, 'call_left', [], $event->getParticipant());
	}

	public static function sendSystemMessageAboutConversationCreated(RoomEvent $event): void {
		$room = $event->getRoom();
		$listener = Server::get(self::class);

		$listener->sendSystemMessage($room, 'conversation_created');
	}

	public static function sendSystemMessageAboutConversationRenamed(ModifyRoomEvent $event): void {
		if ($event->getOldValue() === '' ||
			$event->getNewValue() === '') {
			return;
		}

		$room = $event->getRoom();
		$listener = Server::get(self::class);

		$listener->sendSystemMessage($room, 'conversation_renamed', [
			'newName' => $event->getNewValue(),
			'oldName' => $event->getOldValue(),
		]);
	}

	public static function sendSystemMessageAboutRoomDescriptionChanges(ModifyRoomEvent $event): void {
		$room = $event->getRoom();
		$listener = Server::get(self::class);

		if ($event->getNewValue() !== '') {
			$listener->sendSystemMessage($room, 'description_set', [
				'newDescription' => $event->getNewValue(),
			]);
		} else {
			$listener->sendSystemMessage($room, 'description_removed');
		}
	}

	public static function sendSystemMessageAboutRoomPassword(ModifyRoomEvent $event): void {
		$room = $event->getRoom();
		$listener = Server::get(self::class);

		if ($event->getNewValue() !== '') {
			$listener->sendSystemMessage($room, 'password_set');
		} else {
			$listener->sendSystemMessage($room, 'password_removed');
		}
	}

	public static function sendSystemGuestPermissionsMessage(ModifyRoomEvent $event): void {
		$room = $event->getRoom();

		if ($event->getOldValue() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if ($event->getNewValue() === Room::TYPE_PUBLIC) {
			$listener = Server::get(self::class);
			$listener->sendSystemMessage($room, 'guests_allowed');
		} elseif ($event->getNewValue() === Room::TYPE_GROUP) {
			$listener = Server::get(self::class);
			$listener->sendSystemMessage($room, 'guests_disallowed');
		}
	}

	public static function sendSystemReadOnlyMessage(ModifyRoomEvent $event): void {
		$room = $event->getRoom();

		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return;
		}

		$listener = Server::get(self::class);

		if ($event->getNewValue() === Room::READ_ONLY) {
			$listener->sendSystemMessage($room, 'read_only');
		} elseif ($event->getNewValue() === Room::READ_WRITE) {
			$listener->sendSystemMessage($room, 'read_only_off');
		}
	}

	public static function sendSystemListableMessage(ModifyRoomEvent $event): void {
		$room = $event->getRoom();
		$listener = Server::get(self::class);

		if ($event->getNewValue() === Room::LISTABLE_NONE) {
			$listener->sendSystemMessage($room, 'listable_none');
		} elseif ($event->getNewValue() === Room::LISTABLE_USERS) {
			$listener->sendSystemMessage($room, 'listable_users');
		} elseif ($event->getNewValue() === Room::LISTABLE_ALL) {
			$listener->sendSystemMessage($room, 'listable_all');
		}
	}

	public static function sendSystemLobbyMessage(ModifyLobbyEvent $event): void {
		if ($event->getNewValue() === $event->getOldValue()) {
			return;
		}

		$room = $event->getRoom();
		$listener = Server::get(self::class);

		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			if ($event->getNewValue() === Webinary::LOBBY_NONE) {
				$listener->sendSystemMessage($room, 'breakout_rooms_started');
			} else {
				$listener->sendSystemMessage($room, 'breakout_rooms_stopped');
			}
		} elseif ($event->isTimerReached()) {
			$listener->sendSystemMessage($room, 'lobby_timer_reached');
		} elseif ($event->getNewValue() === Webinary::LOBBY_NONE) {
			$listener->sendSystemMessage($room, 'lobby_none');
		} elseif ($event->getNewValue() === Webinary::LOBBY_NON_MODERATORS) {
			$listener->sendSystemMessage($room, 'lobby_non_moderators');
		}
	}

	public static function addSystemMessageUserAdded(AddParticipantsEvent $event): void {
		$room = $event->getRoom();
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		$listener = Server::get(self::class);
		$participants = $event->getParticipants();

		foreach ($participants as $participant) {
			if ($participant['actorType'] !== 'users') {
				continue;
			}

			$participantType = null;
			if (isset($participant['participantType'])) {
				$participantType = $participant['participantType'];
			}

			$userJoinedFileRoom = $room->getObjectType() === 'file' && $participantType !== Participant::USER_SELF_JOINED;

			// add a message "X joined the conversation", whenever user $userId:
			if (
				// - has joined a file room but not through a public link
				$userJoinedFileRoom
				// - has been added by another user (and not when creating a conversation)
				|| $listener->getUserId() !== $participant['actorId']
				// - has joined a listable room on their own
				|| $participantType === Participant::USER) {
				$comment = $listener->sendSystemMessage(
					$room,
					'user_added',
					['user' => $participant['actorId']],
					null,
					$event->shouldSkipLastMessageUpdate()
				);

				$event->setLastMessage($comment);
			}
		}
	}

	public static function sendSystemMessageUserRemoved(RemoveUserEvent $event): void {
		$room = $event->getRoom();

		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if ($event->getReason() === Room::PARTICIPANT_LEFT
			&& $event->getParticipant()->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
			// Self-joined user closes the tab/window or leaves via the menu
			return;
		}

		$listener = Server::get(self::class);
		$listener->sendSystemMessage($room, 'user_removed', ['user' => $event->getUser()->getUID()]);
	}

	public static function sendSystemMessageAboutPromoteOrDemoteModerator(ModifyParticipantEvent $event): void {
		$room = $event->getRoom();
		$attendee = $event->getParticipant()->getAttendee();

		if ($attendee->getActorType() !== Attendee::ACTOR_USERS && $attendee->getActorType() !== Attendee::ACTOR_GUESTS) {
			return;
		}

		if ($event->getNewValue() === Participant::MODERATOR) {
			$listener = Server::get(self::class);
			$listener->sendSystemMessage($room, 'moderator_promoted', ['user' => $attendee->getActorId()]);
		} elseif ($event->getNewValue() === Participant::USER) {
			if ($event->getOldValue() === Participant::USER_SELF_JOINED) {
				$listener = Server::get(self::class);
				$listener->sendSystemMessage($room, 'user_added', ['user' => $attendee->getActorId()]);
			} else {
				$listener = Server::get(self::class);
				$listener->sendSystemMessage($room, 'moderator_demoted', ['user' => $attendee->getActorId()]);
			}
		} elseif ($event->getNewValue() === Participant::GUEST_MODERATOR) {
			$listener = Server::get(self::class);
			$listener->sendSystemMessage($room, 'guest_moderator_promoted', ['session' => $attendee->getActorId()]);
		} elseif ($event->getNewValue() === Participant::GUEST) {
			$listener = Server::get(self::class);
			$listener->sendSystemMessage($room, 'guest_moderator_demoted', ['session' => $attendee->getActorId()]);
		}
	}

	public static function setShareExpiration(BeforeShareCreatedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM) {
			return;
		}

		$listener = Server::get(self::class);
		$manager = Server::get(Manager::class);

		$room = $manager->getRoomByToken($share->getSharedWith());

		$messageExpiration = $room->getMessageExpiration();
		if (!$messageExpiration) {
			return;
		}

		$dateTime = $listener->timeFactory->getDateTime();
		$dateTime->add(DateInterval::createFromDateString($messageExpiration . ' seconds'));
		$share->setExpirationDate($dateTime);
	}

	public static function fixMimeTypeOfVoiceMessage(ShareCreatedEvent|AlreadySharedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM) {
			return;
		}

		$listener = Server::get(self::class);
		$manager = Server::get(Manager::class);

		$request = Server::get(IRequest::class);
		if ($request->getParam('_route') === 'ocs.spreed.Recording.shareToChat') {
			return;
		}
		$room = $manager->getRoomByToken($share->getSharedWith());
		$metaData = Server::get(IRequest::class)->getParam('talkMetaData') ?? '';
		$metaData = json_decode($metaData, true);
		$metaData = is_array($metaData) ? $metaData : [];

		if (!empty($metaData['noMessage'])) {
			return;
		}

		if (isset($metaData['messageType']) && $metaData['messageType'] === 'voice-message') {
			if ($share->getNode()->getMimeType() !== 'audio/mpeg'
				&& $share->getNode()->getMimeType() !== 'audio/wav') {
				unset($metaData['messageType']);
			}
		}
		$metaData['mimeType'] = $share->getNode()->getMimeType();

		$metaData['caption'] = $metaData['caption'] ?? '';

		$listener->sendSystemMessage($room, 'file_shared', ['share' => $share->getId(), 'metaData' => $metaData]);
	}

	public function handle(Event $event): void {
		if ($event instanceof AttendeesAddedEvent) {
			$this->attendeesAddedEvent($event);
		} elseif ($event instanceof AttendeesRemovedEvent) {
			$this->attendeesRemovedEvent($event);
		}
	}

	protected function attendeesAddedEvent(AttendeesAddedEvent $event): void {
		foreach ($event->getAttendees() as $attendee) {
			if ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
				$this->sendSystemMessage($event->getRoom(), 'group_added', ['group' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
				$this->sendSystemMessage($event->getRoom(), 'circle_added', ['circle' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
				$this->sendSystemMessage($event->getRoom(), 'federated_user_added', ['federated_user' => $attendee->getActorId()]);
			}
		}
	}

	protected function attendeesRemovedEvent(AttendeesRemovedEvent $event): void {
		foreach ($event->getAttendees() as $attendee) {
			if ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
				$this->sendSystemMessage($event->getRoom(), 'group_removed', ['group' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
				$this->sendSystemMessage($event->getRoom(), 'circle_removed', ['circle' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
				$this->sendSystemMessage($event->getRoom(), 'federated_user_removed', ['federated_user' => $attendee->getActorId()]);
			}
		}
	}

	protected function sendSystemMessage(Room $room, string $message, array $parameters = [], Participant $participant = null, bool $shouldSkipLastMessageUpdate = false): IComment {
		if ($participant instanceof Participant) {
			$actorType = $participant->getAttendee()->getActorType();
			$actorId = $participant->getAttendee()->getActorId();
		} else {
			$user = $this->userSession->getUser();
			if ($user instanceof IUser) {
				$actorType = Attendee::ACTOR_USERS;
				$actorId = $user->getUID();
			} elseif (\OC::$CLI || $this->session->exists('talk-overwrite-actor-cli')) {
				$actorType = Attendee::ACTOR_GUESTS;
				$actorId = Attendee::ACTOR_ID_CLI;
			} elseif ($this->session->exists('talk-overwrite-actor-type')) {
				$actorType = $this->session->get('talk-overwrite-actor-type');
				$actorId = $this->session->get('talk-overwrite-actor-id');
			} elseif ($this->session->exists('talk-overwrite-actor-id')) {
				$actorType = Attendee::ACTOR_USERS;
				$actorId = $this->session->get('talk-overwrite-actor-id');
			} else {
				$actorType = Attendee::ACTOR_GUESTS;
				$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
				$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
			}
		}

		// Little hack to get the reference id from the share request into
		// the system message left for the share in the chat.
		$referenceId = $this->request->getParam('referenceId', null);
		if ($referenceId !== null) {
			$referenceId = (string) $referenceId;
		}

		return $this->chatManager->addSystemMessage(
			$room, $actorType, $actorId,
			json_encode(['message' => $message, 'parameters' => $parameters]),
			$this->timeFactory->getDateTime(), $message === 'file_shared',
			$referenceId,
			null,
			$shouldSkipLastMessageUpdate
		);
	}

	protected function getUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user instanceof IUser ? $user->getUID() : null;
	}

	public static function afterSetMessageExpiration(ModifyRoomEvent $event): void {
		$seconds = $event->getNewValue();

		if ($seconds > 0) {
			$message = 'message_expiration_enabled';
		} else {
			$message = 'message_expiration_disabled';
		}

		$listener = Server::get(self::class);
		$listener->sendSystemMessage(
			$event->getRoom(),
			$message,
			[
				'seconds' => $seconds,
			]
		);
	}

	public static function setCallRecording(ModifyRoomEvent $event): void {
		$recordingHasStarted = in_array($event->getOldValue(), [Room::RECORDING_NONE, Room::RECORDING_VIDEO_STARTING, Room::RECORDING_AUDIO_STARTING, Room::RECORDING_FAILED])
			&& in_array($event->getNewValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO]);
		$recordingHasStopped = in_array($event->getOldValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO])
			&& $event->getNewValue() === Room::RECORDING_NONE;
		$recordingHasFailed = in_array($event->getOldValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO])
			&& $event->getNewValue() === Room::RECORDING_FAILED;

		if (!$recordingHasStarted && !$recordingHasStopped && !$recordingHasFailed) {
			return;
		}

		$actor = $event->getActor();
		if ($recordingHasStopped && $actor === null) {
			// No actor means the recording was stopped by the end of the call.
			// So we are not generating a system message
			return;
		}

		$prefix = self::getCallRecordingPrefix($event);
		$suffix = self::getCallRecordingSuffix($event);
		$systemMessage = $prefix . 'recording_' . $suffix;

		$listener = Server::get(self::class);
		$listener->sendSystemMessage($event->getRoom(), $systemMessage, [], $actor);
	}

	private static function getCallRecordingSuffix(ModifyRoomEvent $event): string {
		$newStatus = $event->getNewValue();
		$startStatus = [
			Room::RECORDING_VIDEO,
			Room::RECORDING_AUDIO,
		];
		if (in_array($newStatus, $startStatus)) {
			return 'started';
		}
		if ($newStatus === Room::RECORDING_FAILED) {
			return 'failed';
		}
		return 'stopped';
	}

	private static function getCallRecordingPrefix(ModifyRoomEvent $event): string {
		$newValue = $event->getNewValue();
		$oldValue = $event->getOldValue();
		$isAudioStatus = $newValue === Room::RECORDING_AUDIO
			|| ($oldValue === Room::RECORDING_AUDIO && $newValue !== Room::RECORDING_FAILED);
		return $isAudioStatus ? 'audio_' : '';
	}

	public static function avatarChanged(ModifyRoomEvent $event): void {
		if ($event->getNewValue()) {
			$message = 'avatar_set';
		} else {
			$message = 'avatar_removed';
		}

		$listener = Server::get(self::class);
		$listener->sendSystemMessage($event->getRoom(), $message);
	}
}
