<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\SystemMessage;

use DateInterval;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Events\AAttendeeRemovedEvent;
use OCA\Talk\Events\AParticipantModifiedEvent;
use OCA\Talk\Events\ARoomEvent;
use OCA\Talk\Events\ARoomModifiedEvent;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\BeforeDuplicateShareSentEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\NoteToSelfService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\SampleConversationsService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\TalkSession;
use OCA\Talk\Webinary;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

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
		protected Manager $manager,
		protected ParticipantService $participantService,
		protected MessageParser $messageParser,
		protected ThreadService $threadService,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof ARoomEvent && $event->getRoom()->isFederatedConversation()) {
			return;
		}

		if ($event instanceof AttendeesAddedEvent) {
			$this->attendeesAddedEvent($event);
		} elseif ($event instanceof AttendeeRemovedEvent) {
			$this->sendSystemMessageUserRemoved($event);
		} elseif ($event instanceof AttendeesRemovedEvent) {
			$this->attendeesRemovedEvent($event);
		} elseif ($event instanceof RoomCreatedEvent) {
			$this->sendSystemMessageAboutConversationCreated($event);
		} elseif ($event instanceof LobbyModifiedEvent) {
			$this->sendSystemLobbyMessage($event);
		} elseif ($event instanceof RoomModifiedEvent) {
			match ($event->getProperty()) {
				ARoomModifiedEvent::PROPERTY_AVATAR => $this->avatarChanged($event),
				ARoomModifiedEvent::PROPERTY_CALL_RECORDING => $this->setCallRecording($event),
				ARoomModifiedEvent::PROPERTY_DESCRIPTION => $this->sendSystemMessageAboutRoomDescriptionChanges($event),
				ARoomModifiedEvent::PROPERTY_LISTABLE => $this->sendSystemListableMessage($event),
				ARoomModifiedEvent::PROPERTY_MESSAGE_EXPIRATION => $this->afterSetMessageExpiration($event),
				ARoomModifiedEvent::PROPERTY_NAME => $this->sendSystemMessageAboutConversationRenamed($event),
				ARoomModifiedEvent::PROPERTY_PASSWORD => $this->sendSystemMessageAboutRoomPassword($event),
				ARoomModifiedEvent::PROPERTY_READ_ONLY => $this->sendSystemReadOnlyMessage($event),
				ARoomModifiedEvent::PROPERTY_TYPE => $this->sendSystemGuestPermissionsMessage($event),
				default => null,
			};
		} elseif ($event instanceof BeforeParticipantModifiedEvent) {
			match ($event->getProperty()) {
				AParticipantModifiedEvent::PROPERTY_IN_CALL => $this->sendSystemMessageAboutBeginOfCall($event),
				default => null,
			};
		} elseif ($event instanceof ParticipantModifiedEvent) {
			match ($event->getProperty()) {
				AParticipantModifiedEvent::PROPERTY_TYPE => $this->sendSystemMessageAboutPromoteOrDemoteModerator($event),
				AParticipantModifiedEvent::PROPERTY_IN_CALL => $this->sendSystemMessageAboutCallLeft($event),
				default => null,
			};
		} elseif ($event instanceof BeforeShareCreatedEvent) {
			$this->setShareExpiration($event);
		} elseif ($event instanceof BeforeDuplicateShareSentEvent || $event instanceof ShareCreatedEvent) {
			$this->fixMimeTypeOfVoiceMessage($event);
		}
	}

	protected function sendSystemMessageAboutBeginOfCall(BeforeParticipantModifiedEvent $event): void {
		if ($event->getOldValue() !== Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() === Participant::FLAG_DISCONNECTED) {
			return;
		}

		if ($this->participantService->hasActiveSessionsInCall($event->getRoom())) {
			$this->sendSystemMessage($event->getRoom(), 'call_joined', [], $event->getParticipant());
		} else {
			$silent = $event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_SILENT) ?? false;
			$this->sendSystemMessage($event->getRoom(), 'call_started', [], $event->getParticipant(), silent: $silent);
		}
	}

	protected function sendSystemMessageAboutCallLeft(ParticipantModifiedEvent $event): void {
		if ($event->getDetail(AParticipantModifiedEvent::DETAIL_IN_CALL_END_FOR_EVERYONE)) {
			// No individual system message if the call is ended for everyone
			return;
		}

		if ($event->getNewValue() === $event->getOldValue()) {
			return;
		}

		if ($event->getOldValue() === Participant::FLAG_DISCONNECTED
			|| $event->getNewValue() !== Participant::FLAG_DISCONNECTED) {
			return;
		}

		$session = $event->getParticipant()->getSession();
		if (!$session instanceof Session) {
			// This happens in case the user was kicked/lobbied
			return;
		}

		$this->sendSystemMessage($event->getRoom(), 'call_left', [], $event->getParticipant());
	}

	protected function sendSystemMessageAboutConversationCreated(RoomCreatedEvent $event): void {
		if ($event->getRoom()->getType() === Room::TYPE_CHANGELOG || $this->isCreatingNoteToSelfAutomatically($event) || $this->isCreatingSample($event)) {
			$this->sendSystemMessage($event->getRoom(), 'conversation_created', forceSystemAsActor: true);
		} else {
			$this->sendSystemMessage($event->getRoom(), 'conversation_created');
		}
	}

	protected function sendSystemMessageAboutConversationRenamed(RoomModifiedEvent $event): void {
		if ($event->getOldValue() === ''
			|| $event->getNewValue() === '') {
			return;
		}

		$this->sendSystemMessage($event->getRoom(), 'conversation_renamed', [
			'newName' => $event->getNewValue(),
			'oldName' => $event->getOldValue(),
		]);
	}

	protected function sendSystemMessageAboutRoomDescriptionChanges(RoomModifiedEvent $event): void {
		if ($event->getNewValue() !== '') {
			if ($this->isCreatingNoteToSelf($event) || $this->isCreatingSample($event)) {
				return;
			}

			$this->sendSystemMessage($event->getRoom(), 'description_set', [
				'newDescription' => $event->getNewValue(),
			]);
		} else {
			$this->sendSystemMessage($event->getRoom(), 'description_removed');
		}
	}

	protected function sendSystemMessageAboutRoomPassword(RoomModifiedEvent $event): void {
		if ($event->getNewValue() !== '') {
			$this->sendSystemMessage($event->getRoom(), 'password_set');
		} else {
			$this->sendSystemMessage($event->getRoom(), 'password_removed');
		}
	}

	protected function sendSystemGuestPermissionsMessage(RoomModifiedEvent $event): void {
		if ($event->getOldValue() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if ($event->getNewValue() === Room::TYPE_PUBLIC) {
			$this->sendSystemMessage($event->getRoom(), 'guests_allowed');
		} elseif ($event->getNewValue() === Room::TYPE_GROUP) {
			$this->sendSystemMessage($event->getRoom(), 'guests_disallowed');
		}
	}

	protected function sendSystemReadOnlyMessage(RoomModifiedEvent $event): void {
		$room = $event->getRoom();

		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return;
		}

		if ($event->getNewValue() === Room::READ_ONLY) {
			$this->sendSystemMessage($room, 'read_only');
		} elseif ($event->getNewValue() === Room::READ_WRITE) {
			$this->sendSystemMessage($room, 'read_only_off');
		}
	}

	protected function sendSystemListableMessage(RoomModifiedEvent $event): void {
		if ($event->getNewValue() === Room::LISTABLE_NONE) {
			$this->sendSystemMessage($event->getRoom(), 'listable_none');
		} elseif ($event->getNewValue() === Room::LISTABLE_USERS) {
			$this->sendSystemMessage($event->getRoom(), 'listable_users');
		} elseif ($event->getNewValue() === Room::LISTABLE_ALL) {
			$this->sendSystemMessage($event->getRoom(), 'listable_all');
		}
	}

	protected function sendSystemLobbyMessage(LobbyModifiedEvent $event): void {
		if ($event->getNewValue() === $event->getOldValue()) {
			return;
		}

		$room = $event->getRoom();
		if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
			if ($event->getNewValue() === Webinary::LOBBY_NONE) {
				$this->sendSystemMessage($room, 'breakout_rooms_started');
			} else {
				$this->sendSystemMessage($room, 'breakout_rooms_stopped');
			}
		} elseif ($event->isTimerReached()) {
			$this->sendSystemMessage($room, 'lobby_timer_reached');
		} elseif ($event->getNewValue() === Webinary::LOBBY_NONE) {
			$this->sendSystemMessage($room, 'lobby_none');
		} elseif ($event->getNewValue() === Webinary::LOBBY_NON_MODERATORS) {
			$this->sendSystemMessage($room, 'lobby_non_moderators');
		}
	}

	protected function addSystemMessageUserAdded(AttendeesAddedEvent $event, Attendee $attendee): void {
		$room = $event->getRoom();
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if ($room->getType() === Room::TYPE_CHANGELOG) {
			return;
		}

		$userJoinedFileRoom = $room->getObjectType() === Room::OBJECT_TYPE_FILE && $attendee->getParticipantType() !== Participant::USER_SELF_JOINED;

		// add a message "X joined the conversation", whenever user $userId:
		if (
			// - has joined a file room but not through a public link
			$userJoinedFileRoom
			// - has been added by another user (and not when creating a conversation)
			|| $this->getUserId() !== $attendee->getActorId()
			// - has joined a listable room on their own
			|| $attendee->getParticipantType() === Participant::USER) {
			$this->logger->debug('User "' . $attendee->getActorId() . '" added to room "' . $room->getToken() . '"', ['app' => 'spreed-bfp']);
			$comment = $this->sendSystemMessage(
				$room,
				'user_added',
				['user' => $attendee->getActorId()],
				null,
				$event->shouldSkipLastMessageUpdate()
			);

			$event->setLastMessage($comment);
		}
	}

	protected function sendSystemMessageUserRemoved(AttendeeRemovedEvent $event): void {
		$room = $event->getRoom();

		if ($event->getAttendee()->getActorType() !== Attendee::ACTOR_USERS) {
			return;
		}

		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if ($event->getReason() === AAttendeeRemovedEvent::REASON_LEFT
			&& $event->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
			// Self-joined user closes the tab/window or leaves via the menu
			return;
		}

		$this->logger->debug('User "' . $event->getAttendee()->getActorId() . '" removed from room "' . $room->getToken() . '"', ['app' => 'spreed-bfp']);
		$this->sendSystemMessage($room, 'user_removed', ['user' => $event->getAttendee()->getActorId()]);
	}

	public function sendSystemMessageAboutPromoteOrDemoteModerator(ParticipantModifiedEvent $event): void {
		$room = $event->getRoom();
		$attendee = $event->getParticipant()->getAttendee();

		if (!in_array($attendee->getActorType(), [
			Attendee::ACTOR_USERS,
			Attendee::ACTOR_EMAILS,
			Attendee::ACTOR_GUESTS,
		], true)) {
			return;
		}

		if ($event->getNewValue() === Participant::MODERATOR) {
			$this->sendSystemMessage($room, 'moderator_promoted', ['user' => $attendee->getActorId()]);
		} elseif ($event->getNewValue() === Participant::USER) {
			if ($event->getOldValue() === Participant::USER_SELF_JOINED) {
				$this->sendSystemMessage($room, 'user_added', ['user' => $attendee->getActorId()]);
			} else {
				$this->sendSystemMessage($room, 'moderator_demoted', ['user' => $attendee->getActorId()]);
			}
		} elseif ($event->getNewValue() === Participant::GUEST_MODERATOR) {
			$this->sendSystemMessage($room, 'guest_moderator_promoted', ['type' => $attendee->getActorType(), 'id' => $attendee->getActorId()]);
		} elseif ($event->getNewValue() === Participant::GUEST) {
			$this->sendSystemMessage($room, 'guest_moderator_demoted', ['type' => $attendee->getActorType(), 'id' => $attendee->getActorId()]);
		}
	}

	protected function setShareExpiration(BeforeShareCreatedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM) {
			return;
		}

		$room = $this->manager->getRoomByToken($share->getSharedWith());

		$messageExpiration = $room->getMessageExpiration();
		if (!$messageExpiration) {
			return;
		}

		$dateTime = $this->timeFactory->getDateTime();
		$dateTime->add(DateInterval::createFromDateString($messageExpiration . ' seconds'));
		$share->setExpirationDate($dateTime);
	}

	protected function fixMimeTypeOfVoiceMessage(ShareCreatedEvent|BeforeDuplicateShareSentEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM) {
			return;
		}

		if (strtolower($this->request->getParam('_route')) === 'ocs.spreed.recording.sharetochat') {
			return;
		}
		$room = $this->manager->getRoomByToken($share->getSharedWith());
		$this->participantService->ensureOneToOneRoomIsFilled($room);

		$metaData = $this->request->getParam('talkMetaData') ?? '';
		$metaData = json_decode($metaData, true);
		$metaData = is_array($metaData) ? $metaData : [];

		if (isset($metaData['messageType']) && $metaData['messageType'] === ChatManager::VERB_VOICE_MESSAGE) {
			if ($share->getNode()->getMimeType() !== 'audio/mpeg'
				&& $share->getNode()->getMimeType() !== 'audio/wav') {
				unset($metaData['messageType']);
			}
		}
		$metaData['mimeType'] = $share->getNode()->getMimeType();

		if (isset($metaData['caption'])) {
			if (is_string($metaData['caption']) && trim($metaData['caption']) !== '') {
				$metaData['caption'] = trim($metaData['caption']);
			} else {
				unset($metaData['caption']);
			}
		}

		if (isset($metaData[Message::METADATA_SILENT])) {
			$silent = (bool)$metaData[Message::METADATA_SILENT];
		} else {
			$silent = false;
		}

		$replyTo = null;
		if (isset($metaData['replyTo'])) {
			$replyTo = (int)$metaData['replyTo'];
			unset($metaData['replyTo']);
		}
		$threadId = null;
		if (isset($metaData['threadId'])) {
			$threadId = (int)$metaData['threadId'];
			unset($metaData['threadId']);
		}

		$threadTitle = '';
		if (isset($metaData['threadTitle'])) {
			if (is_string($metaData['threadTitle']) && trim($metaData['threadTitle']) !== '') {
				$threadTitle = trim($metaData['threadTitle']);
			}
			unset($metaData['threadTitle']);
		}

		$comment = $this->sendSystemMessage(
			$room,
			'file_shared',
			['share' => $share->getId(), 'metaData' => $metaData],
			silent: $silent,
			replyTo: $replyTo,
			threadId: $threadId,
		);
		$messageId = (int)$comment->getId();

		if ($threadTitle !== '' && $comment->getTopmostParentId() === '0') {
			$thread = $this->threadService->createThread($room, $messageId, $threadTitle);
			try {
				// Add to subscribed threads list
				$participant = $this->participantService->getParticipant($room, $this->getUserId());
				$this->threadService->setNotificationLevel($participant->getAttendee(), $thread->getId(), Participant::NOTIFY_DEFAULT);
			} catch (ParticipantNotFoundException) {
			}

			$this->sendSystemMessage(
				$room,
				'thread_created',
				['thread' => $messageId, 'title' => $thread->getName()],
				shouldSkipLastMessageUpdate: true,
				silent: true,
				parent: $comment,
			);
		}
	}

	protected function attendeesAddedEvent(AttendeesAddedEvent $event): void {
		foreach ($event->getAttendees() as $attendee) {
			$this->logger->debug($attendee->getActorType() . ' "' . $attendee->getActorId() . '" added to room "' . $event->getRoom()->getToken() . '"', ['app' => 'spreed-bfp']);
			if ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
				$this->sendSystemMessage($event->getRoom(), 'group_added', ['group' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
				$this->sendSystemMessage($event->getRoom(), 'circle_added', ['circle' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
				$this->sendSystemMessage($event->getRoom(), 'federated_user_added', ['federated_user' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_PHONES) {
				$this->sendSystemMessage($event->getRoom(), 'phone_added', ['phone' => $attendee->getActorId(), 'name' => $attendee->getDisplayName()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_USERS) {
				$this->addSystemMessageUserAdded($event, $attendee);
			}
		}
	}

	protected function attendeesRemovedEvent(AttendeesRemovedEvent $event): void {
		foreach ($event->getAttendees() as $attendee) {
			$this->logger->debug($attendee->getActorType() . ' "' . $attendee->getActorId() . '" removed from room "' . $event->getRoom()->getToken() . '"', ['app' => 'spreed-bfp']);
			if ($attendee->getActorType() === Attendee::ACTOR_GROUPS) {
				$this->sendSystemMessage($event->getRoom(), 'group_removed', ['group' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_CIRCLES) {
				$this->sendSystemMessage($event->getRoom(), 'circle_removed', ['circle' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
				$this->sendSystemMessage($event->getRoom(), 'federated_user_removed', ['federated_user' => $attendee->getActorId()]);
			} elseif ($attendee->getActorType() === Attendee::ACTOR_PHONES) {
				$this->sendSystemMessage($event->getRoom(), 'phone_removed', ['phone' => $attendee->getActorId(), 'name' => $attendee->getDisplayName()]);
			}
		}
	}

	protected function sendSystemMessage(
		Room $room,
		string $message,
		array $parameters = [],
		?Participant $participant = null,
		bool $shouldSkipLastMessageUpdate = false,
		bool $silent = false,
		bool $forceSystemAsActor = false,
		?int $replyTo = null,
		?IComment $parent = null,
		?int $threadId = null,
	): IComment {
		if ($participant instanceof Participant) {
			$actorType = $participant->getAttendee()->getActorType();
			$actorId = $participant->getAttendee()->getActorId();
		} elseif ($forceSystemAsActor) {
			$actorType = Attendee::ACTOR_GUESTS;
			$actorId = Attendee::ACTOR_ID_SYSTEM;
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
			$referenceId = (string)$referenceId;
		}

		if ($parent === null && $replyTo !== null) {
			try {
				$parentComment = $this->chatManager->getParentComment($room, (string)$replyTo);
				$parentMessage = $this->messageParser->createMessage($room, $participant, $parentComment, $this->l);
				$this->messageParser->parseMessage($parentMessage, true);
				if ($parentMessage->isReplyable()) {
					$parent = $parentComment;
				}
			} catch (NotFoundException) {
			}
		} elseif ($parent === null && $threadId !== null) {
			if (!$this->threadService->validateThread($room->getId(), $threadId)) {
				$threadId = null;
			}
		}

		return $this->chatManager->addSystemMessage(
			$room, $participant, $actorType, $actorId,
			json_encode(['message' => $message, 'parameters' => $parameters]),
			$this->timeFactory->getDateTime(),
			$message === 'file_shared',
			$referenceId,
			$parent,
			$shouldSkipLastMessageUpdate,
			$silent,
			$threadId ?? 0,
		);
	}

	protected function getUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user instanceof IUser ? $user->getUID() : null;
	}

	protected function afterSetMessageExpiration(RoomModifiedEvent $event): void {
		$seconds = $event->getNewValue();

		if ($seconds > 0) {
			$message = 'message_expiration_enabled';
		} else {
			$message = 'message_expiration_disabled';
		}

		$this->sendSystemMessage(
			$event->getRoom(),
			$message,
			[
				'seconds' => $seconds,
			]
		);
	}

	protected function setCallRecording(RoomModifiedEvent $event): void {
		$recordingHasStarted = in_array($event->getOldValue(), [Room::RECORDING_NONE, Room::RECORDING_VIDEO_STARTING, Room::RECORDING_AUDIO_STARTING, Room::RECORDING_FAILED], true)
			&& in_array($event->getNewValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO], true);
		$recordingHasStopped = in_array($event->getOldValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO], true)
			&& $event->getNewValue() === Room::RECORDING_NONE;
		$recordingHasFailed = in_array($event->getOldValue(), [Room::RECORDING_VIDEO, Room::RECORDING_AUDIO], true)
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

		$prefix = $this->getCallRecordingPrefix($event);
		$suffix = $this->getCallRecordingSuffix($event);
		$systemMessage = $prefix . 'recording_' . $suffix;

		$this->sendSystemMessage($event->getRoom(), $systemMessage, [], $actor);
	}

	protected function getCallRecordingSuffix(RoomModifiedEvent $event): string {
		$newStatus = $event->getNewValue();
		$startStatus = [
			Room::RECORDING_VIDEO,
			Room::RECORDING_AUDIO,
		];
		if (in_array($newStatus, $startStatus, true)) {
			return 'started';
		}
		if ($newStatus === Room::RECORDING_FAILED) {
			return 'failed';
		}
		return 'stopped';
	}

	protected function getCallRecordingPrefix(RoomModifiedEvent $event): string {
		$newValue = $event->getNewValue();
		$oldValue = $event->getOldValue();
		$isAudioStatus = $newValue === Room::RECORDING_AUDIO
			|| ($oldValue === Room::RECORDING_AUDIO && $newValue !== Room::RECORDING_FAILED);
		return $isAudioStatus ? 'audio_' : '';
	}

	protected function avatarChanged(RoomModifiedEvent $event): void {
		if ($event->getNewValue()) {
			if ($this->isCreatingNoteToSelf($event) || $this->isCreatingSample($event)) {
				return;
			}

			$message = 'avatar_set';
		} else {
			$message = 'avatar_removed';
		}

		$this->sendSystemMessage($event->getRoom(), $message);
	}

	protected function isCreatingNoteToSelf(RoomModifiedEvent $event): bool {
		if ($event->getRoom()->getType() !== Room::TYPE_NOTE_TO_SELF) {
			return false;
		}

		$exception = new \Exception();
		$trace = $exception->getTrace();

		foreach ($trace as $step) {
			if (isset($step['class']) && $step['class'] === NoteToSelfService::class
				&& isset($step['function']) && $step['function'] === 'initialCreateNoteToSelfForUser') {
				return true;
			}
			if (isset($step['class']) && $step['class'] === NoteToSelfService::class
				&& isset($step['function']) && $step['function'] === 'ensureNoteToSelfExistsForUser') {
				return true;
			}
		}

		return false;
	}

	protected function isCreatingSample(ARoomEvent $event): bool {
		if ($event->getRoom()->getType() !== Room::TYPE_GROUP) {
			return false;
		}

		$exception = new \Exception();
		$trace = $exception->getTrace();

		foreach ($trace as $step) {
			if (isset($step['class']) && $step['class'] === SampleConversationsService::class
				&& isset($step['function']) && $step['function'] === 'initialCreateSamples') {
				return true;
			}
		}

		return false;
	}

	protected function isCreatingNoteToSelfAutomatically(RoomCreatedEvent $event): bool {
		if ($event->getRoom()->getType() !== Room::TYPE_NOTE_TO_SELF) {
			return false;
		}

		$exception = new \Exception();
		$trace = $exception->getTrace();

		foreach ($trace as $step) {
			if (isset($step['class']) && $step['class'] === NoteToSelfService::class
				&& isset($step['function']) && $step['function'] === 'initialCreateNoteToSelfForUser') {
				return true;
			}
		}

		return false;
	}
}
