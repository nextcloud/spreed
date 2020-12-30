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

* Method: `GET`
* Endpoint: `/room`
* Data:

| field            | type | Description                                                                                                      |
|------------------|------|------------------------------------------------------------------------------------------------------------------|
| `noStatusUpdate` | int  | Whether the "online" user status of the current user should be "kept-alive" (`1`) or not (`0`) (defaults to `0`) |
| `includeStatus`  | bool | Whether the user status information of all one-to-one conversations should be loaded (default false)             |

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in

    - Header:

| field                   | type   | Description                                                                                                                                              |
|-------------------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `X-Nextcloud-Talk-Hash` | string | Sha1 value over some config. When you receive a different value on subsequent requests, the capabilities and the signaling settings should be refreshed. |

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
| `avatarId`              | string  | v3    |         | The type of the avatar ("custom", "user", "icon-public", "icon-contacts", "icon-mail", "icon-password", "icon-changelog", "icon-file")                                                                                                                                                                                                                                                            |
| `avatarVersion`         | int     | v3    |         | The version of the avatar                                                                                                                                                                                                                                                                                                                                                                         |
| `participantType`       | int     | v1    |         | Permissions level of the current user                                                                                                                                                                                                                                                                                                                                                             |
| `attendeeId`            | int     | v3    |         | Unique attendee id                                                                                                                                                                                                                                                                                                                                                                                |
| `attendeePin`           | string  | v3    |         | Unique dial-in authentication code for this user, when the conversation has SIP enabled (see `sipEnabled` attribute)                                                                                                                                                                                                                                                                              |
| `actorType`             | string  | v3    |         | Currently known `users|guests|emails|groups|circles`                                                                                                                                                                                                                                                                                                                                              |
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
| `notificationLevel`     | int     | v1    |         | The notification level for the user (See [Participant notification levels](constants.md#Participant-notification-levels))                                                                                                                                                                                                                                                                         |
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
| `objectType`            | string  | v1    |         | The type of object that the conversation is associated with; "share:password" if the conversation is used to request a password for a share, otherwise empty                                                                                                                                                                                                                                      |
| `objectId`              | string  | v1    |         | Share token if "objectType" is "share:password", otherwise empty                                                                                                                                                                                                                                                                                                                                  |
| `status`                | string  | v4    |         | Optional: Only available for one-to-one conversations and when  `includeStatus=true` is set                                                                                                                                                                                                                                                                                                       |
| `statusIcon`            | string  | v4    |         | Optional: Only available for one-to-one conversations and when  `includeStatus=true` is set                                                                                                                                                                                                                                                                                                       |
| `statusMessage`         | string  | v4    |         | Optional: Only available for one-to-one conversations and when  `includeStatus=true` is set                                                                                                                                                                                                                                                                                                       |
| `participants`          | array   | v1    | v2      | **Removed**                                                                                                                                                                                                                                                                                                                                                                                       |
| `guestList`             | string  | v1    | v2      | **Removed**                                                                                                                                                                                                                                                                                                                                                                                       |

## Creating a new conversation

* Method: `POST`
* Endpoint: `/room`
* Data:

| field      | type   | Description                                                                                                                                                          |
|------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `roomType` | int    | See [constants list](constants.md#conversation-types)                                                                                                                |
| `invite`   | string | user id (`roomType = 1`), group id (`roomType = 2` - optional), circle id (`roomType = 2`, `source = 'circles'`], only available with `circles-support` capability)) |
| `source`   | string | The source for the invite, only supported on `roomType = 2` for `groups` and `circles` (only available with `circles-support` capability)                            |
| `roomName` | string | conversation name (Not available for `roomType = 1`)                                                                                                                 |

* Response:
    - Status code:
        + `200 OK` When the "one to one" conversation already exists
        + `201 Created` When the conversation was created
        + `400 Bad Request` When an invalid conversation type was given
        + `400 Bad Request` When the conversation name is empty for `type = 3`
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

## Rename a conversation

* Method: `PUT`
* Endpoint: `/room/{token}`
* Data:

| field      | type   | Description                                      |
|------------|--------|--------------------------------------------------|
| `roomName` | string | New name for the conversation (1-200 characters) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the name is too long or empty
        + `400 Bad Request` When the conversation is a one to one conversation
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Delete a conversation

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

| field         | type   | Description                          |
|---------------|--------|--------------------------------------|
| `description` | string | New description for the conversation |

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
| `mode`        | string | `default` or `call`, in case of call the permissions will be reset to `0` (default) after the end of a call.                                                                                                                                                                   |
| `permissions` | int    | New permissions for the attendees, see [constants list](constants.md#attendee-permissions). If permissions are not `0` (default), the `1` (custom) permission will always be added. Note that this will reset all custom permissions that have been given to attendees so far. |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support setting publishing permissions, e.g. one-to-one conversations
        + `400 Bad Request` When the mode is invalid
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

## Add conversation to favorites

* Required capability: `favorites`
* Method: `POST`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Remove conversation from favorites

* Required capability: `favorites`
* Method: `DELETE`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant

## Set notification level

* Required capability: `notification-levels`
* Method: `POST`
* Endpoint: `/room/{token}/notify`
* Data:

| field   | type | Description                                                                                                  |
|---------|------|--------------------------------------------------------------------------------------------------------------|
| `level` | int  | The notification level (See [Participant notification levels](constants.md#Participant-notification-levels)) |

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
| `level` | int  | The call notification level (See [Participant call notification levels](constants.md#Participant-call-notification-levels)) |

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
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

## Open a conversation

* Required capability: `listable-rooms`
* Method: `PUT`
* Endpoint: `/room/{token}/listable`
* Data:

| field   | type | Description                    |
|---------|------|--------------------------------|
| `scope` | int  | New flags for the conversation |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support making it listable (only group and public conversation)
        + `403 Forbidden` When the current user is not a moderator/owner or the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant
