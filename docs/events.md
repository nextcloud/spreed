# PHP Events

## Conversation related events

### Created conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Event name: `OCA\Talk\Room::createdRoom`

### Create token for conversation

* Event class: `OCA\Talk\Events\CreateRoomTokenEvent`
* Event name: `OCA\Talk\Manager::generateNewToken`

### Set name

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Pre-event name: `OCA\Talk\Room::preSetName`
* Post-event name: `OCA\Talk\Room::postSetName`

### Set password

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
  - No old value is provided
* Pre-event name: `OCA\Talk\Room::preSetPassword`
* Post-event name: `OCA\Talk\Room::postSetPassword`

### Set type

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Pre-event name: `OCA\Talk\Room::preSetType`
* Post-event name: `OCA\Talk\Room::postSetType`

### Set read-only

* Event class: `OCA\Talk\Events\ModifyRoomEvent`
* Pre-event name: `OCA\Talk\Room::preSetReadOnly`
* Post-event name: `OCA\Talk\Room::postSetReadOnly`

### Set lobby

* Event class: `OCA\Talk\Events\ModifyLobbyEvent`
* Pre-event name: `OCA\Talk\Room::preSetLobbyState`
* Post-event name: `OCA\Talk\Room::postSetLobbyState`

### Clean up guests

* Event class: `OCA\Talk\Events\RoomEvent`
* Pre-event name: `OCA\Talk\Room::preCleanGuests`
* Post-event name: `OCA\Talk\Room::postCleanGuests`

### Verify password

* Event class: `OCA\Talk\Events\VerifyRoomPasswordEvent`
* Event name: `OCA\Talk\Room::verifyPassword`

### Delete conversation

* Event class: `OCA\Talk\Events\RoomEvent`
* Pre-event name: `OCA\Talk\Room::preDeleteRoom`
* Post-event name: `OCA\Talk\Room::postDeleteRoom`

## Participant related events

### Add participants

* Event class: `OCA\Talk\Events\AddParticipantsEvent`
* Pre-event name: `OCA\Talk\Room::preAddUsers`
* Post-event name: `OCA\Talk\Room::postAddUsers`

### Add email

* Event class: `OCA\Talk\Events\AddEmailEvent`
* Pre-event name: `OCA\Talk\GuestManager::preInviteByEmail`
* Post-event name: `OCA\Talk\GuestManager::postInviteByEmail`

### Set participant type for user

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preSetParticipantType`
* Post-event name: `OCA\Talk\Room::postSetParticipantType`

### Set participant type for guests

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preSetParticipantTypeBySession`
* Post-event name: `OCA\Talk\Room::postSetParticipantTypeBySession`

### Join a conversation as user

* Event class: `OCA\Talk\Events\JoinRoomUserEvent`
* Pre-event name: `OCA\Talk\Room::preJoinRoom`
* Post-event name: `OCA\Talk\Room::postJoinRoom`

### Join a conversation as guest

* Event class: `OCA\Talk\Events\JoinRoomGuestEvent`
* Pre-event name: `OCA\Talk\Room::preJoinRoomGuest`
* Post-event name: `OCA\Talk\Room::postJoinRoomGuest`

### Join a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preSessionJoinCall`
* Post-event name: `OCA\Talk\Room::postSessionJoinCall`

### Leave a call

* Event class: `OCA\Talk\Events\ModifyParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preSessionLeaveCall`
* Post-event name: `OCA\Talk\Room::postSessionLeaveCall`

### Leave conversation

* Event class: `OCA\Talk\Events\ParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preUserDisconnectRoom`
* Post-event name: `OCA\Talk\Room::postUserDisconnectRoom`

### Remove user

* Event class: `OCA\Talk\Events\RemoveUserEvent`
* Pre-event name: `OCA\Talk\Room::preRemoveUser`
* Post-event name: `OCA\Talk\Room::postRemoveUser`

### Remove participant by session

* Event class: `OCA\Talk\Events\RemoveParticipantEvent`
* Pre-event name: `OCA\Talk\Room::preRemoveBySession`
* Post-event name: `OCA\Talk\Room::postRemoveBySession`

## Chat related events

### System message has been posted

* Event class: `OCA\Talk\Events\ChatEvent`
* Event name: `OCA\Talk\Chat\ChatManager::postSendSystemMessage`

### Post chat message

* Event class: `OCA\Talk\Events\ChatEvent`
* Pre-event name: `OCA\Talk\Chat\ChatManager::preSendMessage`
* Post-event name: `OCA\Talk\Chat\ChatManager::postSendMessage`

### Parse chat message

* Event class: `OCA\Talk\Events\ChatMessageEvent`
* Event name: `OCA\Talk\Chat\MessageParser::parseMessage`
