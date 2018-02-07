# API v1 Documentation

- [Constants](#constants)
  * [Room types](#room-types)
  * [Participant types](#participant-types)
- [Room management](#room-management)
  * [Creating a new room](#creating-a-new-room)
  * [Get user´s rooms](#get-user-s-rooms)
  * [Get single room (also for guests)](#get-single-room-also-for-guests)
  * [Rename a room](#rename-a-room)
  * [Delete a room](#delete-a-room)
  * [Allow guests in a room (public room)](#allow-guests-in-a-room-public-room)
  * [Disallow guests in a room (group room)](#disallow-guests-in-a-room-group-room)
- [Participant management](#participant-management)
  * [Get list of participants in a room](#get-list-of-participants-in-a-room)
  * [Add a participant to a room](#add-a-participant-to-a-room)
  * [Delete a participant from a room](#delete-a-participant-from-a-room)
  * [Remove yourself from a room](#remove-yourself-from-a-room)
  * [Promote a user to a moderator](#promote-a-user-to-a-moderator)
  * [Demote a moderator to a user](#demote-a-moderator-to-a-user)
- [Call management](#call-management)
  * [Get list of connected participants](#get-list-of-connected-participants)
  * [Join a call](#join-a-call)
  * [Send ping to keep the call alive](#send-ping-to-keep-the-call-alive)
  * [Leave a call (but staying in the room for future calls)](#leave-a-call-but-staying-in-the-room-for-future-calls)
- [Chat](#chat)
  * [Receive chat messages of a room](#receive-chat-messages-of-a-room)
  * [Sending a new chat message](#sending-a-new-chat-message)
- [Signaling](#signaling)


Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Constants

### Room types
* `1` "one to one"
* `2` group
* `3` public

### Participant types
* `1` owner
* `2` moderator
* `3` user
* `4` guest
* `5` user following a public link



## Room management

### Creating a new room

* Method: `POST`
* Endpoint: `/room`
* Data:

    field | type | Description
    ------|------|------------
    `roomType` | int |
    `invite` | string | user id (`roomType = 1`), group id (`roomType = 2` - optional)
    `roomName` | string | room name (Not available for `roomType = 1`)

* Response:
    - Header:
        + `200 OK` when the "one to one" room already exists
        + `201 Created` when the room was created
        + `400 Bad Request` when an invalid room type was given
        + `401 Unauthorized` when the user is not logged in
        + `404 Not Found` when the user or group does not exist

    - Data:

        field | type | Description
        ------|------|------------
        `token` | string | Token identifier of the room which is used for further interaction

### Get user´s rooms

* Method: `GET`
* Endpoint: `/room`

* Response:
    - Header:
        + `200 OK`
        + `401 Unauthorized` when the user is not logged in

    - Data:
        Array of rooms, each room has at least:

        field | type | Description
        ------|------|------------
        `token` | string | Token identifier of the room which is used for further interaction
        `type` | int |
        `name` | string | Name of the room (can also be empty)
        `displayName` | string | `name` if non empty, otherwise it falls back to a list of participants
        `participantType` | int | Permissions level of the current user
        `participantInCall` | bool | Flag if the current user is in the call
        `count` | int | Number of active users
        `numGuests` | int | Number of active guests
        `lastPing` | int | Timestamp of the last ping of the current user (should be used for sorting)
        `sessionId` | string | `'0'` if not connected, otherwise a 512 character long string
        `hasPassword` | bool | Flag if the room has a password
        `hasCall` | bool | Flag if the room has an active call

### Get single room (also for guests)

* Method: `GET`
* Endpoint: `/room/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

    - Data: See array definition in `Get user´s rooms`

### Rename a room

* Method: `PUT`
* Endpoint: `/room/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `roomName` | string | New name for the room (1-200 characters)

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the name is too long
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant
        + `405 Method Not Allowed` When the room is a one to one room

### Set password for a room

* Method: `PUT`
* Endpoint: `/room/{token}/password`
* Data:

    field | type | Description
    ------|------|------------
    `password` | string | New password for the room

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner or the room is not a public room
        + `404 Not Found` When the room could not be found for the participant

### Delete a room

* Method: `DELETE`
* Endpoint: `/room/{token}`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant

### Allow guests in a room (public room)

* Method: `POST`
* Endpoint: `/room/{token}/public`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant

### Disallow guests in a room (group room)

* Method: `DELETE`
* Endpoint: `/room/{token}/public`

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant

### Set room password

* Method: `PUT`
* Endpoint: `/room/{token}/password`
* Data:

    field | type | Description
    ------|------|------------
    `password` | string | Set a new password for the room

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `403 Forbidden` When the room is not a public room
        + `404 Not Found` When the room could not be found for the participant


## Participant management

### Get list of participants in a room

* Method: `GET`
* Endpoint: `/room/{token}/participants`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

    - Data:
        Array of participants, each participant has at least:

        field | type | Description
        ------|------|------------
        `userId` | string | Is empty for guests
        `displayName` | string | Can be empty for guests
        `participantType` | int | Permissions level of the participant
        `lastPing` | int | Timestamp of the last ping of the user (should be used for sorting)
        `sessionId` | string | `'0'` if not connected, otherwise a 512 character long string

### Add a participant to a room

* Method: `POST`
* Endpoint: `/room/{token}/participants`
* Data:

    field | type | Description
    ------|------|------------
    `newParticipant` | string | User to add

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant
        + `404 Not Found` When the user to add could not be found

    - Data:

        field | type | Description
        ------|------|------------
        `type` | string | In case the room type changed, the new value is returned

### Delete a participant from a room

* Method: `DELETE`
* Endpoint: `/room/{token}/participants`
* Data:

    field | type | Description
    ------|------|------------
    `participant` | string | User to remove

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `403 Forbidden` When the participant to remove is an owner
        + `404 Not Found` When the room could not be found for the participant
        + `404 Not Found` When the participant to remove could not be found

### Remove a guest from a room

* Method: `DELETE`
* Endpoint: `/room/{token}/participants/guests`
* Data:

    field | type | Description
    ------|------|------------
    `participant` | string | Session ID of the guest to remove

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `403 Forbidden` When the target participant is not a guest
        + `404 Not Found` When the room could not be found for the participant
        + `404 Not Found` When the target participant could not be found

### Remove yourself from a room

* Method: `DELETE`
* Endpoint: `/room/{token}/participants/self`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

### Join a room (available for call and chat)

* Method: `POST`
* Endpoint: `/room/{token}/participants/active`
* Data:

    field | type | Description
    ------|------|------------
    `password` | string | Optional: Password is only required for users which are of type `4` or `5` and only when the room has `hasPassword` set to true.

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the password is required and didn't match
        + `404 Not Found` When the room could not be found for the participant

    - Data:

        field | type | Description
        ------|------|------------
        `sessionId` | string | 512 character long string

### Leave a room (not available for call and chat anymore)

* Method: `DELETE`
* Endpoint: `/room/{token}/participants/active`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

### Promote a user to a moderator

* Method: `POST`
* Endpoint: `/room/{token}/moderators`
* Data:

    field | type | Description
    ------|------|------------
    `participant` | string | User to promote

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `403 Forbidden` When the participant to remove is an owner
        + `404 Not Found` When the room could not be found for the participant
        + `404 Not Found` When the participant to remove could not be found
        + `412 Precondition Failed` When the participant to promote is not a normal user (type `3`)

### Demote a moderator to a user

* Method: `DELETE`
* Endpoint: `/room/{token}/moderators`
* Data:

    field | type | Description
    ------|------|------------
    `participant` | string | User to promote

* Response:
    - Header:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the room could not be found for the participant
        + `404 Not Found` When the participant to remove could not be found
        + `412 Precondition Failed` When the participant to demote is not a moderator (type `2`)



## Call management

### Get list of connected participants

* Method: `GET`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

    - Data:
        Array of participants, each participant has at least:

        field | type | Description
        ------|------|------------
        `userId` | string | Is empty for guests
        `lastPing` | int | Timestamp of the last ping of the user (should be used for sorting)
        `sessionId` | string | 512 character long string

### Join a call

* Method: `POST`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

### Send ping to keep the call alive

* Method: `POST`
* Endpoint: `/call/{token}/ping`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

### Leave a call (but staying in the room for future calls and chat)

* Method: `DELETE`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

## Chat

### Receive chat messages of a room

* Method: `GET`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `offset` | int | Ignores the first N messages
    `notOlderThanTimestamp` | int | Timestamp in seconds and UTC time zone
    `timeout` | int | Number of seconds to wait for new messages (30 by default, 60 at most)

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

    - Data:
        Array of messages, each message has at least:

        field | type | Description
        ------|------|------------
        `id` | int | ID of the comment
        `actorType` | string | `guests` or `users`
        `actorId` | string | User id of the message author
        `actorDisplayName` | string | Display name of the message author
        `timestamp` | int | Timestamp in seconds and UTC time zone
        `message` | string | Message in plain text

### Sending a new chat message

* Method: `POST`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `message` | string | The message the user wants to say

* Response:
    - Header:
        + `201 Created`
        + `404 Not Found` When the room could not be found for the participant

## Signaling

See the [Draft](https://github.com/nextcloud/spreed/wiki/Signaling-API) in the wiki…
