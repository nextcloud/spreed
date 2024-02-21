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

## Participant related events

### Attendees added

* Before event: `OCA\Talk\Events\BeforeAttendeesAddedEvent`
  * Since: 18.0.0
* After event: `OCA\Talk\Events\AttendeesAddedEvent`
* Since: 12.0.0

### Attendees removed

* Before event: *Not available*
* After event: `OCA\Talk\Events\AttendeesRemovedEvent`
* Since: 12.0.0

### Attendee removed

* Before event: `OCA\Talk\Events\BeforeAttendeeRemovedEvent`
* After event: `OCA\Talk\Events\AttendeeRemovedEvent`
* Since: 18.0.0

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

### Email invitation send

* Before event: `OCA\Talk\Events\BeforeEmailInvitationSentEvent`
* After event: `OCA\Talk\Events\EmailInvitationSentEvent`
* Since: 18.0.0

### Session left a conversation

This is the invert action to `User joined a conversation`, `Federated user joined a conversation` and `Guest joined a conversation`

* Before event: `OCA\Talk\Events\BeforeSessionLeftRoomEvent`
* After event: `OCA\Talk\Events\SessionLeftRoomEvent`
* Since: 18.0.0

### Participant modified

* Before event: `OCA\Talk\Events\BeforeParticipantModifiedEvent`
* After event: `OCA\Talk\Events\ParticipantModifiedEvent`
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

## Inbound events to invoke Talk

### Bot install

Dispatch this event in your app to install a bot on this server

* Event: `OCA\Talk\Events\BotInstallEvent`
* Since: 17.0.0
* Since: 19.0.0 - Features flag was added

### Bot uninstall

Dispatch this event in your app to install a bot on this server

* Event: `OCA\Talk\Events\BotUninstallEvent`
* Since: 17.0.0
