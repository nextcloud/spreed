# API v1 Documentation

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
    `invite` | string | user id (`roomType = 1`), group id (`roomType = 2`)

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
        `lastPing` | int | Timestamp of the last ping of the current user (should be used for sorting)
        `sessionId` | string | `'0'` if not connected, otherwise a 512 character long string

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

### Remove yourself from a room

* Method: `DELETE`
* Endpoint: `/room/{token}/participants/self`

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
