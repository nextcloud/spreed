# PHP Events

See the general [Nextcloud Developers - Events](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html) documentation for information how to listen to the events.

## Conversation related events

### Conversation list fetched

* Before event: `OCA\Talk\Events\BeforeRoomsFetchEvent`
* After event: *Not available*
* Since: 18.0.0

### Conversation created

* Before event: *Not available*
* After event: `OCA\Talk\Events\RoomCreatedEvent`
* Since: 18.0.0

### Conversation deleted

* Before event: `OCA\Talk\Events\BeforeRoomDeletedEvent`
* After event: `OCA\Talk\Events\RoomDeletedEvent`
* Since: 18.0.0

### Conversation modified

* Before event: `OCA\Talk\Events\BeforeRoomModifiedEvent`
* After event: `OCA\Talk\Events\RoomModifiedEvent`
* Since: 18.0.0

### Lobby modified

* Before event: `OCA\Talk\Events\BeforeLobbyModifiedEvent`
* After event: `OCA\Talk\Events\LobbyModifiedEvent`
* Since: 18.0.0

### Call ended for everyone

* Before event: `OCA\Talk\Events\BeforeCallEndedForEveryoneEvent`
* After event: `OCA\Talk\Events\CallEndedForEveryoneEvent`
* Since: 18.0.0

### Conversation password verify

Allows to verify a password and set a redirect URL for the invalid case

* Event: `OCA\Talk\Events\RoomPasswordVerifyEvent`
* Since: 18.0.0

### Deprecated events

These events were not using the typed-event mechanism and are therefore deprecated and will be removed in a future version.

#### Get conversations list

* Event class: `OCA\Talk\Events\UserEvent`
* Event name: `OCA\Talk\Controller\RoomController::EVENT_BEFORE_ROOMS_GET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomsFetchEvent` instead
* Removed: 19.0.0

#### Create conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_CREATE`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\RoomCreatedEvent` instead
* Removed: 19.0.0

#### Create token for conversation

* Event class: `OCA\Talk\Events\CreateRoomTokenEvent`
* Event name: `OCA\Talk\Manager::EVENT_TOKEN_GENERATE`
* Since: 8.0.0
* Removed: 11.0.0

#### Set name

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_NAME_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_NAME_SET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Set password

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
  - No old value is provided
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PASSWORD_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PASSWORD_SET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Set type

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_TYPE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_TYPE_SET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Set read-only

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_READONLY_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_READONLY_SET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Set listable

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_LISTABLE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_LISTABLE_SET`
* Since: 11.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Set lobby

* Event class: `OCA\Talk\Events\ModifyLobbyEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_LOBBY_STATE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_LOBBY_STATE_SET`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomModifiedEvent` and `OCA\Talk\Events\RoomModifiedEvent` instead
* Removed: 19.0.0

#### Clean up guests

* Event class: `OCA\Talk\Events\RoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_GUESTS_CLEAN`
* After event name: `OCA\Talk\Room::EVENT_AFTER_GUESTS_CLEAN`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeGuestsCleanedUpEvent` and `OCA\Talk\Events\GuestsCleanedUpEvent` instead
* Removed: 19.0.0

#### Verify password

* Event class: `OCA\Talk\Events\VerifyRoomPasswordEvent`
* Event name: `OCA\Talk\Room::EVENT_PASSWORD_VERIFY`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\RoomPasswordVerifyEvent` instead
* Removed: 19.0.0

#### Delete conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_DELETE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_DELETE`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeRoomDeletedEvent` and `OCA\Talk\Events\RoomDeletedEvent` instead
* Removed: 19.0.0

## Participant related events

### Attendees added

* Before event: *Not available*
* After event: `OCA\Talk\Events\AttendeesAddedEvent`
* Since: 12.0.0

### Attendees removed

* Before event: *Not available*
* After event: `OCA\Talk\Events\AttendeesRemovedEvent`
* Since: 12.0.0

### User joined a conversation

* Before event: `OCA\Talk\Events\BeforeUserJoinedRoomEvent`
* After event: `OCA\Talk\Events\UserJoinedRoomEvent`
* Since: 18.0.0

### Federated user joined a conversation

* Before event: `OCA\Talk\Events\BeforeFederatedUserJoinedRoomEvent`
* After event: `OCA\Talk\Events\FederatedUserJoinedRoomEvent`
* Since: 18.0.0

### Guest joined a conversation

* Before event: `OCA\Talk\Events\BeforeGuestJoinedRoomEvent`
* After event: `OCA\Talk\Events\GuestJoinedRoomEvent`
* Since: 18.0.0

### Session left a conversation

This is the invert action to `User joined a conversation`, `Federated user joined a conversation` and `Guest joined a conversation`

* Before event: `OCA\Talk\Events\BeforeSessionLeftRoomEvent`
* After event: `OCA\Talk\Events\SessionLeftRoomEvent`
* Since: 18.0.0

### Call notification send

* **internal:** This event is not part of the public API and you should not rely on it
* Event: `OCA\Talk\Events\CallNotificationSendEvent`
* Since: 18.0.0

### Guests cleaned up

Remove guests without an active session

* Before event: `OCA\Talk\Events\BeforeGuestsCleanedUpEvent`
* After event: `OCA\Talk\Events\GuestsCleanedUpEvent`
* Since: 18.0.0

### Deprecated events

These events were not using the typed-event mechanism and are therefore deprecated and will be removed in a future version.

#### Add participants

* Event class: `OCA\Talk\Events\AddParticipantsEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_USERS_ADD`
* After event name: `OCA\Talk\Room::EVENT_AFTER_USERS_ADD`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Add email

* Event class: `OCA\Talk\Events\AddEmailEvent`
* Before event name: `OCA\Talk\GuestManager::EVENT_BEFORE_EMAIL_INVITE`
* After event name: `OCA\Talk\GuestManager::EVENT_AFTER_EMAIL_INVITE`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Remove participant by session

* Event class: `OCA\Talk\Events\RemoveParticipantEvent`
* Event name: `OCA\Talk\GuestManager::EVENT_AFTER_NAME_UPDATE`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Set participant type for user

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PARTICIPANT_TYPE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PARTICIPANT_TYPE_SET`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Join a conversation as user (Connect)

* Event class: `OCA\Talk\Events\JoinRoomUserEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_CONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_CONNECT`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeUserJoinedRoomEvent` and `OCA\Talk\Events\UserJoinedRoomEvent` instead
* Removed: 19.0.0

#### Join a conversation as guest (Connect)

* Event class: `OCA\Talk\Events\JoinRoomGuestEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_GUEST_CONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_GUEST_CONNECT`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeGuestJoinedRoomEvent` and `OCA\Talk\Events\GuestJoinedRoomEvent` instead
* Removed: 19.0.0

#### Join a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_SESSION_JOIN_CALL`
* After event name: `OCA\Talk\Room::EVENT_AFTER_SESSION_JOIN_CALL`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Leave a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_SESSION_LEAVE_CALL`
* After event name: `OCA\Talk\Room::EVENT_AFTER_SESSION_LEAVE_CALL`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Leave a conversation (Disconnect)

* Event class: `OCA\Talk\Events\ParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_DISCONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_DISCONNECT`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeSessionLeftRoomEvent` and `OCA\Talk\Events\SessionLeftRoomEvent` instead
* Removed: 19.0.0

#### Remove user

* Event class: `OCA\Talk\Events\RemoveUserEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_USER_REMOVE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_USER_REMOVE`
* Since: 8.0.0
* Deprecated: 18.0.0

#### Remove participant by session

* Event class: `OCA\Talk\Events\RemoveParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PARTICIPANT_REMOVE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PARTICIPANT_REMOVE`
* Since: 8.0.0
* Deprecated: 18.0.0

## Chat related events

### Parse chat message

Used to parse mentions, replace parameters in messages with rich objects, transform system messages into readable and translated chat messages etc.

* Event: `OCA\Talk\Events\MessageParseEvent`
* Since: 18.0.0

### Chat message sent

* Before event: `OCA\Talk\Events\BeforeChatMessageSentEvent`
* After event: `OCA\Talk\Events\ChatMessageSentEvent`
* Since: 18.0.0

### Duplicate share sent

Sharing the same file a second time is prevented by the API. But this event is dispatched, so that the chat message can be created nevertheless.

* Before event: `OCA\Talk\Events\BeforeDuplicateShareSentEvent`
* After event: *Not available*
* Since: 18.0.0

### System message sent

`shouldSkipLastActivityUpdate` indicates whether multiple system messages are being sent.
In case you only need to be notified after the last system message was posted,
listen to the `OCA\Talk\Events\SystemMessagesMultipleSentEvent` event instead.

* Before event: `OCA\Talk\Events\BeforeSystemMessageSentEvent`
* After event: `OCA\Talk\Events\SystemMessageSentEvent`
* Final event: `OCA\Talk\Events\SystemMessagesMultipleSentEvent` - Only sent once as per above explanation
* Since: 18.0.0

### Deprecated events

These events were not using the typed-event mechanism and are therefore deprecated and will be removed in a future version.

#### System message

* Event class: `OCA\Talk\Events\ChatEvent`
* Before event name: `OCA\Talk\Chat\ChatManager::EVENT_BEFORE_SYSTEM_MESSAGE_SEND`
* After event name: `OCA\Talk\Chat\ChatManager::EVENT_AFTER_SYSTEM_MESSAGE_SEND`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeSystemMessageSentEvent` and `OCA\Talk\Events\SystemMessageSentEvent` instead
* Removed: 19.0.0

#### Post chat message

* Event class: `OCA\Talk\Events\ChatEvent`
* Before event name: `OCA\Talk\Chat\ChatManager::EVENT_BEFORE_MESSAGE_SEND`
* After event name: `OCA\Talk\Chat\ChatManager::EVENT_AFTER_MESSAGE_SEND`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeChatMessageSentEvent` and `OCA\Talk\Events\ChatMessageSentEvent` instead
* Removed: 19.0.0

#### Parse chat message

* Event class: `OCA\Talk\Events\ChatMessageEvent`
* Event name: `OCA\Talk\Chat\MessageParser::EVENT_MESSAGE_PARSE`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\MessageParseEvent` instead
* Removed: 19.0.0

#### Command execution for apps

* Event class: `OCA\Talk\Events\CommandEvent`
* Event name: `OCA\Talk\Chat\Command\Executor::EVENT_APP_EXECUTE`
* Since: 8.0.0
* Deprecated: 17.0.0 - Commands are deprecated, please migrate to bots instead

## Other events

### Turn servers get

* Before event: `OCA\Talk\Events\BeforeTurnServersGetEvent`
* After event: *Not available*
* Since: 18.0.0

### Signaling room properties sent

* Before event: `OCA\Talk\Events\BeforeSignalingRoomPropertiesSentEvent`
* After event: *Not available*
* Since: 18.0.0

### Signaling response sent

* Before event: `OCA\Talk\Events\BeforeSignalingResponseSentEvent`
* After event: *Not available*
* Since: 18.0.0

### Deprecated events

These events were not using the typed-event mechanism and are therefore deprecated and will be removed in a future version.

#### Signaling backend

* Event class: `OCA\Talk\Events\SignalingEvent`
* Event name: `OCA\Talk\Controller\SignalingController::EVENT_BACKEND_SIGNALING_ROOMS`
* Since: 8.0.0
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeSignalingResponseSentEvent` instead
* Removed: 19.0.0

#### Get conversation properties for signaling

* Event class: `OCA\Talk\Events\SignalingRoomPropertiesEvent`
* Event name: `OCA\Talk\Room::EVENT_BEFORE_SIGNALING_PROPERTIES`
* Since: 8.0.5
* Deprecated: 18.0.0 - Use `OCA\Talk\Events\BeforeSignalingRoomPropertiesSentEvent` instead
* Removed: 19.0.0

## Inbound events to invoke Talk

### Bot install

Dispatch this event in your app to install a bot on this server

* Event: `OCA\Talk\Events\BotInstallEvent`
* Since: 17.0.0

### Bot uninstall

Dispatch this event in your app to install a bot on this server

* Event: `OCA\Talk\Events\BotUninstallEvent`
* Since: 17.0.0
