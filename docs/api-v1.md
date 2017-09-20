## API v1 Documentation

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

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

### Get room by token (also for guests)

* Method: `GET`
* Endpoint: `/room/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant

    - Data: See array definition in `Get user´s rooms`
