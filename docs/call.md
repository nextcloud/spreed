# Call API

* Base endpoint for API v1 is: `/ocs/v2.php/apps/spreed/api/v1`
* Base endpoint for API v2 is: `/ocs/v2.php/apps/spreed/api/v2`
* Base endpoint for API v3 is: `/ocs/v2.php/apps/spreed/api/v3`

## Get list of connected participants

* Method: `GET`
* Endpoint: `/call/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        Array of participants, each participant has at least:

        field | type | API | Description
        ------|------|-----|------------
        `userId` | string | v1 and v2 only | Is empty for guests
        `actorType` | string | v3 | Actor type of the attendee (see [Constants - Attendee types](constants.md#attendee-types))
        `actorId` | string | v3 | The user id, guest random id or email address of the attendee
        `displayName` | string | v3 | The display name of the attendee
        `lastPing` | int | * | Timestamp of the last ping of the user (should be used for sorting)
        `sessionId` | string | * | 512 character long string

## Join a call

* Method: `POST`
* Endpoint: `/call/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `flags` | int | Flags what streams are provided by the participant (see [Constants - Participant in-call flag](constants.md#participant-in-call-flag))

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user did not join the conversation before
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

## Leave a call (but staying in the conversation for future calls and chat)

* Method: `DELETE`
* Endpoint: `/call/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user did not join the conversation before
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
