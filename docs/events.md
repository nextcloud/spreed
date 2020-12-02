# PHP Events

Explanations:
* `Event class`: is the PHP class of the event object that is passed by the event
* `Event name`: refers to the PHP constant that contains the full name for the event. You should always use the constants instead of copying the value to avoid problems in the future. Most events have a `Before` and `After` event name. They behave the same and reflect if the event is triggered before or after the action described.

## Conversation related events

### Get conversations list

* Event class: `OCA\Talk\Events\UserEvent`
* Event name: `OCA\Talk\Controller\RoomController::EVENT_BEFORE_ROOMS_GET`
* Since: 8.0.0

### Search listed conversations

* Event class: `OCA\Talk\Events\UserEvent`
* Event name: `OCA\Talk\Controller\RoomController::EVENT_BEFORE_LISTED_ROOMS_GET`
* Since: 11.0.0

### Create conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_CREATE`
* Since: 8.0.0

### Create token for conversation

* Event class: `OCA\Talk\Events\CreateRoomTokenEvent`
* Event name: `OCA\Talk\Manager::EVENT_TOKEN_GENERATE`
* Since: 8.0.0

### Set name

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_NAME_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_NAME_SET`
* Since: 8.0.0

### Set password

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
  - No old value is provided
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PASSWORD_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PASSWORD_SET`
* Since: 8.0.0

### Set type

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_TYPE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_TYPE_SET`
* Since: 8.0.0

### Set read-only

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_READONLY_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_READONLY_SET`
* Since: 8.0.0

### Set listable

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_LISTABLE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_LISTABLE_SET`
* Since: 11.0.0

### Set lobby

* Event class: `OCA\Talk\Events\ModifyLobbyEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_LOBBY_STATE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_LOBBY_STATE_SET`
* Since: 8.0.0

### Clean up guests

* Event class: `OCA\Talk\Events\RoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_GUESTS_CLEAN`
* After event name: `OCA\Talk\Room::EVENT_AFTER_GUESTS_CLEAN`
* Since: 8.0.0

### Verify password

* Event class: `OCA\Talk\Events\VerifyRoomPasswordEvent`
* Event name: `OCA\Talk\Room::EVENT_PASSWORD_VERIFY`
* Since: 8.0.0

### Delete conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_DELETE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_DELETE`
* Since: 8.0.0

## Participant related events

### Add participants

* Event class: `OCA\Talk\Events\AddParticipantsEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_USERS_ADD`
* After event name: `OCA\Talk\Room::EVENT_AFTER_USERS_ADD`
* Since: 8.0.0

### Add email

* Event class: `OCA\Talk\Events\AddEmailEvent`
* Before event name: `OCA\Talk\GuestManager::EVENT_BEFORE_EMAIL_INVITE`
* After event name: `OCA\Talk\GuestManager::EVENT_AFTER_EMAIL_INVITE`
* Since: 8.0.0

### Remove participant by session

* Event class: `OCA\Talk\Events\RemoveParticipantEvent`
* Event name: `OCA\Talk\GuestManager::EVENT_AFTER_NAME_UPDATE`
* Since: 8.0.0

### Set participant type for user

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PARTICIPANT_TYPE_SET`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PARTICIPANT_TYPE_SET`
* Since: 8.0.0

### Join a conversation as user (Connect)

* Event class: `OCA\Talk\Events\JoinRoomUserEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_CONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_CONNECT`
* Since: 8.0.0

### Join a conversation as guest (Connect)

* Event class: `OCA\Talk\Events\JoinRoomGuestEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_GUEST_CONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_GUEST_CONNECT`
* Since: 8.0.0

### Join a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_SESSION_JOIN_CALL`
* After event name: `OCA\Talk\Room::EVENT_AFTER_SESSION_JOIN_CALL`
* Since: 8.0.0

### Leave a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_SESSION_LEAVE_CALL`
* After event name: `OCA\Talk\Room::EVENT_AFTER_SESSION_LEAVE_CALL`
* Since: 8.0.0

### Leave a conversation (Disconnect)

* Event class: `OCA\Talk\Events\ParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_ROOM_DISCONNECT`
* After event name: `OCA\Talk\Room::EVENT_AFTER_ROOM_DISCONNECT`
* Since: 8.0.0

### Remove user

* Event class: `OCA\Talk\Events\RemoveUserEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_USER_REMOVE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_USER_REMOVE`
* Since: 8.0.0

### Remove participant by session

* Event class: `OCA\Talk\Events\RemoveParticipantEvent`
* Before event name: `OCA\Talk\Room::EVENT_BEFORE_PARTICIPANT_REMOVE`
* After event name: `OCA\Talk\Room::EVENT_AFTER_PARTICIPANT_REMOVE`
* Since: 8.0.0

## Chat related events

### System message

* Event class: `OCA\Talk\Events\ChatEvent`
* Before event name: `OCA\Talk\Chat\ChatManager::EVENT_BEFORE_SYSTEM_MESSAGE_SEND`
* After event name: `OCA\Talk\Chat\ChatManager::EVENT_AFTER_SYSTEM_MESSAGE_SEND`
* Since: 8.0.0

### Post chat message

* Event class: `OCA\Talk\Events\ChatEvent`
* Before event name: `OCA\Talk\Chat\ChatManager::EVENT_BEFORE_MESSAGE_SEND`
* After event name: `OCA\Talk\Chat\ChatManager::EVENT_AFTER_MESSAGE_SEND`
* Since: 8.0.0

### Parse chat message

* Event class: `OCA\Talk\Events\ChatMessageEvent`
* Event name: `OCA\Talk\Chat\MessageParser::EVENT_MESSAGE_PARSE`
* Since: 8.0.0

### Command execution for apps

* Event class: `OCA\Talk\Events\CommandEvent`
* Event name: `OCA\Talk\Chat\Command\Executor::EVENT_APP_EXECUTE`
* Since: 8.0.0


## Other events

### Signaling backend

* Event class: `OCA\Talk\Events\SignalingEvent`
* Event name: `OCA\Talk\Controller\SignalingController::EVENT_BACKEND_SIGNALING_ROOMS`
* Since: 8.0.0

### Get conversation properties for signaling

* Event class: `OCA\Talk\Events\SignalingRoomPropertiesEvent`
* Event name: `OCA\Talk\Room::EVENT_BEFORE_SIGNALING_PROPERTIES`
* Since: 8.0.5
