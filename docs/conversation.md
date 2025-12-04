# Conversation API

* API v1: 🏁 Removed with API v4: until Nextcloud 21
* API v2: 🏁 Removed with API v4: Nextcloud 19 - 21
* API v3: 🏁 Removed with API v4: Nextcloud 21 only
* API v4: Base endpoint `/ocs/v2.php/apps/spreed/api/v4`: since Nextcloud 22

## Get user´s conversations

!!! note

    Due to the structure of the `lastMessage.reactions` array the response is not valid in XML.
    It is therefor recommended to use `format=json` or send the `Accept: application/json` header,
    to receive a JSON response.

!!! note

    When `modifiedSince` is provided only conversations with a newer `lastActivity` are returned. If `includeStatus` is set to `true` all one-to-one conversations will be returned to make sure the latest user status is returned.
    Due to the nature of the data structure we can not return information that a conversation was deleted
    or the user removed from a conversation. Therefore it is recommended to do a full refresh:
    * Every 5 minutes
    * When a signaling message of type `delete` or `disinvite` was received
    * Internal signaling mode is used


* Method: `GET`
* Endpoint: `/room`
* Data:

| field            | type | Description                                                                                                                                                                     |
|------------------|------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `noStatusUpdate` | int  | When the user status should not be automatically set to online set to 1 (default 0)                                                                                             |
| `includeStatus`  | bool | Whether the user status information of all one-to-one conversations should be loaded (default false)                                                                            |
| `modifiedSince`  | int  | **Use with care as per note above.** When provided only conversations with a newer `lastActivity` (and one-to-one conversations when `includeStatus` is provided) are returned. |

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in

    - Header:

| field                                 | type   | Description                                                                                                                                                         |
|---------------------------------------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Nextcloud-Talk-Hash`               | string | Sha1 value over some config. When you receive a different value on subsequent requests, the capabilities and the signaling settings should be refreshed.            |
| `X-Nextcloud-Talk-Modified-Before`    | string | Timestamp from before the database request that can be used as `modifiedSince` parameter in the next request                                                        |
| `X-Nextcloud-Talk-Federation-Invites` | string | *Optional:* Number of pending invites to federated conversations the user has. (Only available when the user can do federation and has at least one invite pending) |

    - Data:
        Array of conversations, each conversation has at least:

| field                   | type    | Added | Removed | Description                                                                                                                                                                                                                                                                                                                                                                                       |
|-------------------------|---------|-------|---------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `id`                    | int     | v1    |         | Numeric identifier of the conversation                                                                                                                                                                                                                                                                                                                                                            |
| `token`                 | string  | v1    |         | Token identifier of the conversation which is used for further interaction                                                                                                                                                                                                                                                                                                                        |
| `type`                  | int     | v1    |         | See list of conversation types in the [constants list](constants.md#conversation-types)                                                                                                                                                                                                                                                                                                           |
| `name`                  | string  | v1    |         | Name of the conversation (can also be empty)                                                                                                                                                                                                                                                                                                                                                      |
| `displayName`           | string  | v1    |         | `name` if non empty, otherwise it falls back to a list of participants                                                                                                                                                                                                                                                                                                                            |
| `description`           | string  | v3    |         | Description of the conversation (can also be empty) (only available with `room-description` capability)                                                                                                                                                                                                                                                                                           |
| `participantType`       | int     | v1    |         | Permissions level of the current user                                                                                                                                                                                                                                                                                                                                                             |
| `attendeeId`            | int     | v3    |         | Unique attendee id                                                                                                                                                                                                                                                                                                                                                                                |
| `attendeePin`           | string  | v3    |         | Unique dial-in authentication code for this user, when the conversation has SIP enabled (see `sipEnabled` attribute)                                                                                                                                                                                                                                                                              |
| `actorType`             | string  | v3    |         | Actor type of the current user (see [constants list](constants.md#attendee-types))                                                                                                                                                                                                                                                                                                                |
| `actorId`               | string  | v3    |         | The unique identifier for the given actor type                                                                                                                                                                                                                                                                                                                                                    |
| `permissions`           | int     | v4    |         | Combined final permissions for the current participant, permissions are picked in order of attendee then call then default and the first which is `Custom` will apply (see [constants list](constants.md#attendee-permissions))                                                                                                                                                                   |
| `attendeePermissions`   | int     | v4    |         | Dedicated permissions for the current participant, if not `Custom` this are not the resulting permissions (see [constants list](constants.md#attendee-permissions))                                                                                                                                                                                                                               |
| `callPermissions`       | int     | v4    |         | Call permissions, if not `Custom` this are not the resulting permissions, if set they will reset after the end of the call (see [constants list](constants.md#attendee-permissions))                                                                                                                                                                                                              |
| `defaultPermissions`    | int     | v4    |         | Default permissions for new participants (see [constants list](constants.md#attendee-permissions))                                                                                                                                                                                                                                                                                                |
| `participantInCall`     | bool    | v1    | v2      | **Removed:** use `participantFlags` instead                                                                                                                                                                                                                                                                                                                                                       |
| `participantFlags`      | int     | v1    |         | "In call" flags of the user's session making the request (only available with `in-call-flags` capability)                                                                                                                                                                                                                                                                                         |
| `readOnly`              | int     | v1    |         | Read-only state for the current user (only available with `read-only-rooms` capability)                                                                                                                                                                                                                                                                                                           |
| `listable`              | int     | v3    |         | Listable scope for the room (only available with `listable-rooms` capability)                                                                                                                                                                                                                                                                                                                     |
| `messageExpiration`     | int     | v4    |         | The message expiration time in seconds in this chat. Zero if disabled. (only available with `message-expiration` capability)                                                                                                                                                                                                                                                                      |
| `count`                 | int     | v1    | v2      | **Removed:** Count the users on the [Get list of participants in a conversation](participant.md#get-list-of-participants-in-a-conversation) endpoint                                                                                                                                                                                                                                              |
| `numGuests`             | int     | v1    | v2      | **Removed:** Count the guests on the [Get list of participants in a conversation](participant.md#get-list-of-participants-in-a-conversation) endpoin                                                                                                                                                                                                                                              |
| `lastPing`              | int     | v1    |         | Timestamp of the user's session making the request                                                                                                                                                                                                                                                                                                                                                |
| `sessionId`             | string  | v1    |         | `'0'` if not connected, otherwise an up to 512 character long string that is the identifier of the user's session making the request. Should only be used to pre-check if the user joined already with this session, but this might be outdated by the time of usage, so better check via [Get list of participants in a conversation](participant.md#get-list-of-participants-in-a-conversation) |
| `hasPassword`           | bool    | v1    |         | Flag if the conversation has a password                                                                                                                                                                                                                                                                                                                                                           |
| `hasCall`               | bool    | v1    |         | Flag if the conversation has an active call                                                                                                                                                                                                                                                                                                                                                       |
| `callFlag`              | int     | v3    |         | Combined flag of all participants in the current call (see [constants list](constants.md#participant-in-call-flag), only available with `conversation-call-flags` capability)                                                                                                                                                                                                                     |
| `canStartCall`          | bool    | v1    |         | Flag if the user can start a new call in this conversation (joining is always possible) (only available with `start-call-flag` capability)                                                                                                                                                                                                                                                        |
| `canDeleteConversation` | bool    | v2    |         | Flag if the user can delete the conversation for everyone (not possible without moderator permissions or in one-to-one conversations)                                                                                                                                                                                                                                                             |
| `canLeaveConversation`  | bool    | v2    |         | Flag if the user can leave the conversation (not possible for the last user with moderator permissions)                                                                                                                                                                                                                                                                                           |
| `lastActivity`          | int     | v1    |         | Timestamp of the last activity in the conversation, in seconds and UTC time zone                                                                                                                                                                                                                                                                                                                  |
| `isFavorite`            | bool    | v1    |         | Flag if the conversation is favorited by the user                                                                                                                                                                                                                                                                                                                                                 |
| `notificationLevel`     | int     | v1    |         | The notification level for the user (See [Participant notification levels](constants.md#participant-notification-levels))                                                                                                                                                                                                                                                                         |
| `lobbyState`            | int     | v1    |         | Webinar lobby restriction (0-1), if the participant is a moderator they can always join the conversation (only available with `webinary-lobby` capability) (See [Webinar lobby states](constants.md#webinar-lobby-states))                                                                                                                                                                        |
| `lobbyTimer`            | int     | v1    |         | Timestamp when the lobby will be automatically disabled (only available with `webinary-lobby` capability)                                                                                                                                                                                                                                                                                         |
| `sipEnabled`            | int     | v3    |         | SIP enable status (see [constants list](constants.md#sip-states))                                                                                                                                                                                                                                                                                                                                 |
| `canEnableSIP`          | int     | v3    |         | Whether the given user can enable SIP for this conversation. Note that when the token is not-numeric only, SIP can not be enabled even if the user is permitted and a moderator of the conversation                                                                                                                                                                                               |
| `unreadMessages`        | int     | v1    |         | Number of unread chat messages in the conversation (only available with `chat-v2` capability)                                                                                                                                                                                                                                                                                                     |
| `unreadMention`         | bool    | v1    |         | Flag if the user was mentioned since their last visit                                                                                                                                                                                                                                                                                                                                             |
| `unreadMentionDirect`   | bool    | v4    |         | Flag if the user was mentioned directly (ignoring @all mentions) since their last visit (only available with `direct-mention-flag` capability)                                                                                                                                                                                                                                                    |
| `lastReadMessage`       | int     | v1    |         | ID of the last read message in a room (only available with `chat-read-marker` capability)                                                                                                                                                                                                                                                                                                         |
| `lastCommonReadMessage` | int     | v3    |         | ID of the last message read by every user that has read privacy set to public in a room. When the user themself has it set to private the value is `0` (only available with `chat-read-status` capability)                                                                                                                                                                                        |
| `lastMessage`           | message | v1    |         | Last message in a conversation if available, otherwise empty. **Note:** Even when given the message will not contain the `parent` or `reactionsSelf` attribute due to performance reasons                                                                                                                                                                                                         |
| `objectType`            | string  | v1    |         | The type of object that the conversation is associated with (See [Object types](constants.md#object-types))                                                                                                                                                                                                                                                                                       |
| `objectId`              | string  | v1    |         | See [Object types](constants.md#object-types) documentation for explanation                                                                                                                                                                                                                                                                                                                       |
| `breakoutRoomMode`      | string  | v4    |         | Breakout room configuration mode (see [constants list](constants.md#breakout-room-modes)) (only available with `breakout-rooms-v1` capability)                                                                                                                                                                                                                                                    |
| `breakoutRoomStatus`    | string  | v4    |         | Breakout room status (see [constants list](constants.md#breakout-room-status)) (only available with `breakout-rooms-v1` capability)                                                                                                                                                                                                                                                               |
| `status`                | string  | v4    |         | Optional: Only available for one-to-one conversations, when  `includeStatus=true` is set and the user has a status                                                                                                                                                                                                                                                                                |
| `statusIcon`            | ?string | v4    |         | Optional: Only available for one-to-one conversations, when  `includeStatus=true` is set and the user has a status, can still be null even with a status                                                                                                                                                                                                                                          |
| `statusMessage`         | ?string | v4    |         | Optional: Only available for one-to-one conversations, when  `includeStatus=true` is set and the user has a status, can still be null even with a status                                                                                                                                                                                                                                          |
| `statusClearAt`         | ?int    | v4    |         | Optional: Only available for one-to-one conversations, when  `includeStatus=true` is set and the user has a status, can still be null even with a status                                                                                                                                                                                                                                          |
| `participants`          | array   | v1    | v2      | **Removed**                                                                                                                                                                                                                                                                                                                                                                                       |
| `guestList`             | string  | v1    | v2      | **Removed**                                                                                                                                                                                                                                                                                                                                                                                       |
| `avatarVersion`         | string  | v4    |         | Version of conversation avatar used to easier expiration of the avatar in case a moderator updates it, since the avatar endpoint should be cached for 24 hours. (only available with `avatar` capability)                                                                                                                                                                                         |
| `isCustomAvatar`        | bool    | v4    |         | Flag if the conversation has a custom avatar (only available with `avatar` capability)                                                                                                                                                                                                                                                                                                            |
| `callStartTime`         | int     | v4    |         | Timestamp when the call was started (only available with `recording-v1` capability)                                                                                                                                                                                                                                                                                                               |
| `callRecording`         | int     | v4    |         | Type of call recording (see [Constants - Call recording status](constants.md#call-recording-status)) (only available with `recording-v1` capability)                                                                                                                                                                                                                                              |
| `recordingConsent`      | int     | v4    |         | Whether recording consent is required before joining a call (Only 0 and 1 will be returned, see [constants list](constants.md#recording-consent-required)) (only available with `recording-consent` capability)                                                                                                                                                                                   |
| `mentionPermissions`    | int     | v4    |         | Whether all participants can mention using `@all` or only moderators (see [constants list](constants.md#mention-permissions)) (only available with `mention-permissions` capability)                                                                                                                                                                                                              |
| `isArchived`            | bool    | v4    |         | Flag if the conversation is archived by the user (only available with `archived-conversations-v2` capability)                                                                                                                                                                                                                                                                                     |                                                                                                                                                                                                          |

## Creating a new conversation

*Note:* Creating a conversation as a child breakout room, will automatically set the lobby when breakout rooms are not started and will always overwrite the room type with the parent room type. Also, moderators of the parent conversation will be automatically added as moderators.

* Method: `POST`
* Endpoint: `/room`
* Data:

| field        | type   | Description                                                                                                                                                              |
|--------------|--------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `roomType`   | int    | See [constants list](constants.md#conversation-types)                                                                                                                    |
| `invite`     | string | user id (`roomType = 1`), group id (`roomType = 2` - optional), circle id (`roomType = 2`, `source = 'circles'`], only available with `circles-support` capability))     |
| `source`     | string | The source for the invite, only supported on `roomType = 2` for `groups` and `circles` (only available with `circles-support` capability)                                |
| `roomName`   | string | Conversation name up to 255 characters (Not available for `roomType = 1`)                                                                                                |
| `objectType` | string | Type of an object this room references, currently only allowed value is `room` to indicate the parent of a breakout room (See [Object types](constants.md#object-types)) |
| `objectId`   | string | Id of an object this room references, room token is used for the parent of a breakout room                                                                               |
| `password`   | string | Password for the room (only available with `conversation-creation-password` capability)                                                                                  |

* Response:
    - Status code:
        + `200 OK` When the "one to one" conversation already exists
        + `201 Created` When the conversation was created
        + `400 Bad Request` When an invalid conversation type was given
        + `400 Bad Request` When the conversation name is empty for `type = 3`
        + `400 Bad Request` When a password is required for a public room or when the password is invalid according to the password policy
        + `401 Unauthorized` When the user is not logged in
        + `404 Not Found` When the target to invite does not exist

    - Data: See array definition in `Get user´s conversations`

## Get single conversation (also for guests)

* Method: `GET`
* Endpoint: `/room/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant

    - Header:

| field                   | type   | Description                                                                                                                                              |
|-------------------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Nextcloud-Talk-Hash` | string | Sha1 value over some config. When you receive a different value on subsequent requests, the capabilities and the signaling settings should be refreshed. |

    - Data: See array definition in `Get user´s conversations`

## Get "Note-to-self" conversation

The conversation is like a group conversation and the user is the owner, but with the following limitations:

* Can not add participants to the conversation
* Can not allow guests
* Can not make read-only
* Can not open the conversations to users or guests
* Can not change permissions
* Can not set lobby
* Can not enable SIP
* Can not configure breakout rooms
* Can not call

If the conversation does not exist at the moment, it will be generated and the user added automatically.

* Required capability: `note-to-self`
* Method: `GET`
* Endpoint: `/room/note-to-self`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the user is not logged in

    - Header:

| field                   | type   | Description                                                                                                                                              |
|-------------------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Nextcloud-Talk-Hash` | string | Sha1 value over some config. When you receive a different value on subsequent requests, the capabilities and the signaling settings should be refreshed. |

    - Data: See array definition in `Get user´s conversations`

## Get open conversations

* Required capability: `listable-rooms`
* Method: `GET`
* Endpoint: `/listed-room`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in

    - Header:

| field        | type   | Description |
|--------------|--------|-------------|
| `searchTerm` | string | search term |

    - Data: See array definition in `Get user´s conversations`

## Get breakout rooms

Get all (for moderators and in case of "free selection") or the assigned breakout room

* Required capability: `breakout-rooms-v1`
* Method: `GET`
* Endpoint: `/room/{token}/breakout-rooms`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation does not have breakout rooms configured
        + `400 Bad Request` When the breakout rooms are not started and the participant is not a moderator
        + `401 Unauthorized` When the user is not logged in
        + `404 Not Found` When the conversation could not be found for the participant

    - Data: See array definition in `Get user´s conversations`

## Rename a conversation

* Method: `PUT`
* Endpoint: `/room/{token}`
* Data:

| field      | type   | Description                                          |
|------------|--------|------------------------------------------------------|
| `roomName` | string | New name for the conversation (up to 255 characters) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the name is too long or empty
        + `400 Bad Request` When the conversation is a one to one conversation
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Delete a conversation

*Note:* Deleting a conversation that is the parent of breakout rooms, will also delete them.

* Method: `DELETE`
* Endpoint: `/room/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation is a one-to-one conversation (Use [Remove yourself from a conversation](participant.md#Remove-yourself-from-a-conversation) instead)
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Set description for a conversation

* Required capability: `room-description`
* Method: `PUT`
* Endpoint: `/room/{token}/description`
* Data:

| field         | type   | Description                                                                                |
|---------------|--------|--------------------------------------------------------------------------------------------|
| `description` | string | New description for the conversation (limited to 2.000 characters, was 500 before Talk 21) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the description is too long
        + `400 Bad Request` When the conversation is a one to one conversation
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Allow guests in a conversation (public conversation)

* Method: `POST`
* Endpoint: `/room/{token}/public`
* Data:

| field      | type    | Description                                                                                     |
|------------|---------|-------------------------------------------------------------------------------------------------|
| `password` | ?string | Password for the conversation (only available with `conversation-creation-password` capability) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation is not a group conversation
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Disallow guests in a conversation (group conversation)

* Method: `DELETE`
* Endpoint: `/room/{token}/public`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation is not a public conversation
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Set read-only for a conversation

* Required capability: `read-only-rooms`
* Method: `PUT`
* Endpoint: `/room/{token}/read-only`
* Data:

| field   | type | Description                                                                         |
|---------|------|-------------------------------------------------------------------------------------|
| `state` | int  | New state for the conversation, see [constants list](constants.md#read-only-states) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support read-only (only group and public conversation)
        + `403 Forbidden` When the current user is not a moderator/owner or the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

## Set password for a conversation

* Method: `PUT`
* Endpoint: `/room/{token}/password`
* Data:

| field      | type   | Description                       |
|------------|--------|-----------------------------------|
| `password` | string | New password for the conversation |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the password does not match the password policy. Show `ocs.data.message` to the user in this case
        + `400 Bad Request` When the conversation is a breakout room
        + `403 Forbidden` When the current user is not a moderator or owner
        + `403 Forbidden` When the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

    - Data:
        field | type | Description
        ---|---|---
        `message` | string | Only available on `400 Bad Request`, translated error with the violated password policy rules

## Set default or call permissions for a conversation

* Method: `PUT`
* Endpoint: `/room/{token}/permissions/{mode}`
* Data:

| field         | type   | Description                                                                                                                                                                                                                                                                    |
|---------------|--------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `mode`        | string | `default` or `call`, in case of call the permissions will be reset to `0` (default) after the end of a call. (🏁 `call` is no-op since Talk 20)                                                                                                                                |
| `permissions` | int    | New permissions for the attendees, see [constants list](constants.md#attendee-permissions). If permissions are not `0` (default), the `1` (custom) permission will always be added. Note that this will reset all custom permissions that have been given to attendees so far. |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support setting publishing permissions, e.g. one-to-one conversations
        + `400 Bad Request` When the mode is invalid
        + `400 Bad Request` When the conversation is a breakout room
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

## Add conversation to favorites

* Required capability: `favorites`
* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Remove conversation from favorites

* Required capability: `favorites`
* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Set notification level

* Required capability: `notification-levels`
* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/room/{token}/notify`
* Data:

| field   | type | Description                                                                                                  |
|---------|------|--------------------------------------------------------------------------------------------------------------|
| `level` | int  | The notification level (See [Participant notification levels](constants.md#participant-notification-levels)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the given level is invalid
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Set notification level for calls

* Required capability: `notification-calls`
* Method: `POST`
* Endpoint: `/room/{token}/notify-calls`
* Data:

| field   | type | Description                                                                                                                 |
|---------|------|-----------------------------------------------------------------------------------------------------------------------------|
| `level` | int  | The call notification level (See [Participant call notification levels](constants.md#participant-call-notification-levels)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the given level is invalid
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Set message expiration

* Required capability: `message-expiration`
* Method: `POST`
* Endpoint: `/room/{token}/message-expiration`
* Data:

| field     | type | Description                                                                                 |
|-----------|------|---------------------------------------------------------------------------------------------|
| `seconds` | int  | The messages expiration in seconds. If is zero, messages will not be deleted automatically. |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Invalid value
        + `400 Bad Request` When the conversation is a breakout room
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

## Set recording consent

* Required capability: `recording-consent`
* Method: `PUT`
* Endpoint: `/room/{token}/recording-consent`
* Data:

| field              | type | Description                                                                                                                                 |
|--------------------|------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `recordingConsent` | int  | New consent setting for the conversation (Only `0` and `1` from the [constants](constants.md#recording-consent-required) are allowed here.) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the consent value is invalid
        + `400 Bad Request` When the consent is being enabled while a call is going on
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Open a conversation

* Required capability: `listable-rooms`
* Method: `PUT`
* Endpoint: `/room/{token}/listable`
* Data:

| field   | type | Description                    |
|---------|------|--------------------------------|
| `scope` | int  | The room listable scope (See [listable scopes](constants.md#listable-scope)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support making it listable (only group and public conversation)
        + `400 Bad Request` When the conversation is a breakout room
        + `403 Forbidden` When the current user is not a moderator/owner or the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

## Set mention permissions

* Required capability: `mention-permissions`
* Method: `PUT`
* Endpoint: `/room/{token}/mention-permissions`
* Data:

| field                | type | Description                                                                                               |
|----------------------|------|-----------------------------------------------------------------------------------------------------------|
| `mentionPermissions` | int  | New mention permissions for the conversation (See [mention permssions](constants.md#mention-permissions)) |

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` When the conversation type does not support setting mention permissions (only group and public conversation)
		+ `400 Bad Request` When the conversation is a breakout room
		+ `400 Bad Request` When permissions value is invalid
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant

## Get conversation capabilities

See [Capability handling in federated conversations](https://github.com/nextcloud/spreed/issues/10680) to learn which capabilities
should be considered from the local server or from the remote server.

* Required capability: `federation-v1`
* Method: `GET`
* Endpoint: `/room/{token}/capabilities`

* Response:
    - Status code:
        + `200 OK` Get capabilities
        + `404 Not Found` When the conversation could not be found for the participant

    - Header:

| field                         | type   | Description                                                                                |
|-------------------------------|--------|--------------------------------------------------------------------------------------------|
| `X-Nextcloud-Talk-Proxy-Hash` | string | Sha1 value over the capabilities in case the conversation is hosted **on another server**. |
| `X-Nextcloud-Talk-Hash`       | string | Sha1 value over the capabilities in case the conversation is hosted **on this server**.    |

    - Data: Server capabilities limited to the `spreed` sub-array or an empty array in case the app is disabled (for the user)
