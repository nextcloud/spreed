# Call API

* API v1: 🏁 Removed with API v4: until Nextcloud 21
* API v2: 🏁 Removed with API v4: Nextcloud 21 only
* API v3: 🏁 Removed with API v4: Nextcloud 21 only
* API v4: Base endpoint `/ocs/v2.php/apps/spreed/api/v4`: since Nextcloud 22

!!! note

    At the moment, joining a room is only possible with cookies, as you need a
    session.

## Get list of connected participants

* Federation capability: `federation-v2`
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

| field         | type   | Added | Removed | Description                                                                                |
|---------------|--------|-------|---------|--------------------------------------------------------------------------------------------|
| `actorType`   | string | v3    |         | Actor type of the attendee (see [Constants - Attendee types](constants.md#attendee-types)) |
| `actorId`     | string | v3    |         | The user id, guest random id or email address of the attendee                              |
| `userId`      | string | v1    | v3      | The user id replaced with actorType = users and actorId being the userId                   |
| `displayName` | string | v3    |         | The display name of the attendee                                                           |
| `lastPing`    | int    | v1    |         | Timestamp of the last ping of the user (should be used for sorting)                        |
| `sessionId`   | string | v1    |         | 512 character long string                                                                  |

## Join a call

* Federation capability: `federation-v2`
* Method: `POST`
* Endpoint: `/call/{token}`
* Data:

| field              | type | Description                                                                                                                                                                                                                        |
|--------------------|------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `flags`            | int  | Flags what streams are provided by the participant (see [Constants - Participant in-call flag](constants.md#participant-in-call-flag))                                                                                             |
| `silent`           | bool | Disable start call notifications for group/public calls                                                                                                                                                                            |
| `recordingConsent` | bool | When the user ticked a checkbox and agreed with being recorded (Only needed when the `config => call => recording-consent` capability is set to `1` or the capability is `2` and the conversation `recordingConsent` value is `1`) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When recording consent is required but was not given
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user did not join the conversation before
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

## Send call notification

* Required capability: `send-call-notification`
* Federation capability: `federation-v2`
* Method: `POST`
* Endpoint: `/call/{token}/ring/{attendeeId}`
* Data:

| field        | type | Description               |
|--------------|------|---------------------------|
| `attendeeId` | int  | The participant to notify |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the target participant is not a user (Guest, group, etc.)
        + `400 Bad Request` When the target participant is already in the call
        + `400 Bad Request` When the room has no call in process
        + `400 Bad Request` When the actor is not in the call
        + `403 Forbidden` When the current user is not a moderator
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the attendee could not be found in the conversation
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

## Send SIP dial-out request

* Required capability: `sip-support-dialout`
* Method: `POST`
* Endpoint: `/call/{token}/dialout/{attendeeId}`
* Data:

| field        | type | Description             |
|--------------|------|-------------------------|
| `attendeeId` | int  | The participant to call |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the room has no call in process
        + `400 Bad Request` When the actor is not in the call
        + `403 Forbidden` When the current user does not have the "Start call" permission
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the target participant could not be found or is not a phone number (Guest, group, etc.)
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `501 Not Implemented` When the SIP functionality is not configured

## Update call flags

* Federation capability: `federation-v2`
* Method: `PUT`
* Endpoint: `/call/{token}`
* Data:

| field   | type | Description                                                                                                                            |
|---------|------|----------------------------------------------------------------------------------------------------------------------------------------|
| `flags` | int  | Flags what streams are provided by the participant (see [Constants - Participant in-call flag](constants.md#participant-in-call-flag)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the user is not in the call
        + `400 Bad Request` When the flags do not contain "in call"
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user did not join the conversation before
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

## Leave a call (but staying in the conversation for future calls and chat)

* Federation capability: `federation-v2`
* Method: `DELETE`
* Endpoint: `/call/{token}`
* Data:

| field | type | Description                                                                 |
|-------|------|-----------------------------------------------------------------------------|
| `all` | bool | If sent as a moderator, end the meeting and all participants leave the call |


* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user did not join the conversation before
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
