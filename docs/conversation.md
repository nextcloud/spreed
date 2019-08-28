# Conversation API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Creating a new conversation

* Method: `POST`
* Endpoint: `/room`
* Data:

    field | type | Description
    ------|------|------------
    `roomType` | int |
    `invite` | string | user id (`roomType = 1`), group id (`roomType = 2` - optional)
    `roomName` | string | conversation name (Not available for `roomType = 1`)

* Response:
    - Header:
        + `200 OK` when the "one to one" conversation already exists
        + `201 Created` when the conversation was created
        + `400 Bad Request` when an invalid conversation type was given
        + `401 Unauthorized` when the user is not logged in
        + `404 Not Found` when the user or group does not exist

    - Data:

        field | type | Description
        ------|------|------------
        `token` | string | Token identifier of the conversation which is used for further interaction
        `name` | string | Name of the conversation (can also be empty)
        `displayName` | string | `name` if non empty, otherwise it falls back to a list of participants

## Get user´s conversations

* Method: `GET`
* Endpoint: `/room`

* Response:
    - Header:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in

    - Data:
        Array of conversations, each conversation has at least:

        field | type | Description
        ------|------|------------
        `token` | string | Token identifier of the conversation which is used for further interaction
        `type` | int |
        `name` | string | Name of the conversation (can also be empty)
        `displayName` | string | `name` if non empty, otherwise it falls back to a list of participants
        `participantType` | int | Permissions level of the current user
        `participantInCall` | bool | Flag if the current user is in the call (deprecated, use `participantFlags` instead)
        `participantFlags` | int | Flags of the current user (only available with `in-call-flags` capability)
        `readOnly` | int | Read-only state for the current user (only available with `read-only-rooms` capability)
        `count` | int | Number of active users
        `numGuests` | int | Number of active guests
        `lastPing` | int | Timestamp of the last ping of the current user (should be used for sorting)
        `sessionId` | string | `'0'` if not connected, otherwise a 512 character long string
        `hasPassword` | bool | Flag if the conversation has a password
        `hasCall` | bool | Flag if the conversation has an active call
        `lastActivity` | int | Timestamp of the last activity in the conversation, in seconds and UTC time zone
        `isFavorite` | bool | Flag if the conversation is favorited by the user
        `notificationLevel` | int | The notification level for the user (one of `Participant::NOTIFY_*` (1-3))
        `lobbyState` | int | Webinary lobby restriction (0-1), if the participant is a moderator they can always join the conversation (only available with `webinary-lobby` capability)
        `lobbyTimer` | int | Timestamp when the lobby will be automatically disabled (only available with `webinary-lobby` capability)
        `unreadMessages` | int | Number of unread chat messages in the conversation (only available with `chat-v2` capability)
        `unreadMention` | bool | Flag if the user was mentioned since their last visit
        `lastReadMessage` | int | ID of the last read message in a room (only available with `chat-read-marker` capability)
        `lastMessage` | message | Last message in a conversation if available, otherwise empty
        `objectType` | string | The type of object that the conversation is associated with; "share:password" if the conversation is used to request a password for a share, otherwise empty
        `objectId` | string | Share token if "objectType" is "share:password", otherwise empty
       
## Get single conversation (also for guests)

* Method: `GET`
* Endpoint: `/room/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant

    - Data: See array definition in `Get user´s conversations`

## Rename a conversation

* Method: `PUT`
* Endpoint: `/room/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `roomName` | string | New name for the conversation (1-200 characters)

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the name is too long or empty
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
        + `405 Method Not Allowed` When the conversation is a one to one conversation

## Set read-only for a conversation

* Method: `PUT`
* Endpoint: `/room/{token}/read-only`
* Data:

    field | type | Description
    ------|------|------------
    `state` | int | New state for the conversation

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support read-only (only group and public conversation atm)
        + `403 Forbidden` When the current user is not a moderator/owner or the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

## Set password for a conversation

* Method: `PUT`
* Endpoint: `/room/{token}/password`
* Data:

    field | type | Description
    ------|------|------------
    `password` | string | New password for the conversation

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner or the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

## Delete a conversation

* Method: `DELETE`
* Endpoint: `/room/{token}`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Allow guests in a conversation (public conversation)

* Method: `POST`
* Endpoint: `/room/{token}/public`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Disallow guests in a conversation (group conversation)

* Method: `DELETE`
* Endpoint: `/room/{token}/public`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Set conversation password

* Method: `PUT`
* Endpoint: `/room/{token}/password`
* Data:

    field | type | Description
    ------|------|------------
    `password` | string | Set a new password for the conversation

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `403 Forbidden` When the conversation is not a public conversation
        + `404 Not Found` When the conversation could not be found for the participant

## Add conversation to favorites

* Method: `POST`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant or the participant is a guest

## Remove conversation from favorites

* Method: `DELETE`
* Endpoint: `/room/{token}/favorite`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant or the participant is a guest

## Set notification level

* Method: `POST`
* Endpoint: `/room/{token}/notify`
* Data:

    field | type | Description
    ------|------|------------
    `level` | int | The notification level (one of `Participant::NOTIFY_*` (1-3))

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the the given level is invalid
        + `404 Not Found` When the conversation could not be found for the participant or the participant is a guest
