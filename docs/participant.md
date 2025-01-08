# Participant API

* API v1: 🏁 Removed with API v4: until Nextcloud 21
* API v2: 🏁 Removed with API v4: Nextcloud 19 - 21
* API v3: 🏁 Removed with API v4: Nextcloud 21 only
* API v4: Base endpoint `/ocs/v2.php/apps/spreed/api/v4`: since Nextcloud 22

## Get list of participants in a conversation

* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/room/{token}/participants`
* Data:

| field           | type | Description                                                 |
|-----------------|------|-------------------------------------------------------------|
| `includeStatus` | bool | Whether the user status information also needs to be loaded |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the participant is a guest
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        Array of participants, each participant has at least:

| field                 | type   | Added | Removed | Description                                                                                                                                                                                                             |
|-----------------------|--------|-------|---------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `attendeeId`          | int    | v3    |         | Unique attendee id                                                                                                                                                                                                      |
| `actorType`           | string | v3    |         | Currently known `users                                                                                                                                                                                                  |guests|emails|groups|circles`
| `actorId`             | string | v3    |         | The unique identifier for the given actor type                                                                                                                                                                          |
| `displayName`         | string | v1    |         | Can be empty for guests                                                                                                                                                                                                 |
| `participantType`     | int    | v1    |         | Permissions level of the participant (see [constants list](constants.md#participant-types))                                                                                                                             |
| `lastPing`            | int    | v1    |         | Timestamp of the last ping of the user (should be used for sorting)                                                                                                                                                     |
| `inCall`              | int    | v1    |         | Call flags the user joined with (see [constants list](constants.md#participant-in-call-flag))                                                                                                                           |
| `permissions`         | int    | v4    |         | Combined final permissions for the participant, permissions are picked in order of attendee then call then default and the first which is `Custom` will apply (see [constants list](constants.md#attendee-permissions)) |
| `attendeePermissions` | int    | v4    |         | Dedicated permissions for the current participant, if not `Custom` this are not the resulting permissions (see [constants list](constants.md#attendee-permissions))                                                     |
| `sessionId`           | string | v1    | v4      | `'0'` if not connected, otherwise a 512 character long string                                                                                                                                                           |
| `sessionIds`          | array  | v4    |         | array of session ids, each are 512 character long strings, or empty if no session                                                                                                                                       |
| `status`              | string | v2    |         | Optional: Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation                                                                       |
| `statusIcon`          | string | v2    |         | Optional: Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation                                                                       |
| `statusMessage`       | string | v2    |         | Optional: Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation                                                                       |
| `roomToken`           | string | v4    |         | Optional: Only available with `breakout-rooms-v1` capability                                                                                                                                                            |
| `phoneNumber`         | string | v4    |         | Optional: Only available with `sip-support-dialout` capability and only filled for moderators that are allowed to configure SIP for conversations                                                                       |
| `callId`              | string | v4    |         | Optional: Only available with `sip-support-dialout` capability and only filled for moderators that are allowed to configure SIP for conversations                                                                       |


## Get list of participants in a conversation including its breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `GET`
* Endpoint: `/room/{token}/breakout-rooms/participants`
* Data: See Data in [Get list of participants in a conversations](participant.md#get-list-of-participants-in-a-conversation)
* Response: See Response in [Get list of participants in a conversations](participant.md#get-list-of-participants-in-a-conversation)

## Add a participant to a conversation

*Note:* Adding a participant to a breakout room will automatically add them to the parent room as well.

*Note:* Only source users can be added directly to a breakout room.

*Note:* Adding a participant to a breakout room, that is already a participant in another breakout room of the same parent will remove them from there.

* Method: `POST`
* Endpoint: `/room/{token}/participants`
* Data:

| field            | type   | Description                                                                                           |
|------------------|--------|-------------------------------------------------------------------------------------------------------|
| `newParticipant` | string | User, group, email or circle to add                                                                   |
| `source`         | string | Source of the participant(s) as returned by the autocomplete suggestion endpoint (default is `users`) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the source type is unknown, currently `users`, `groups`, `emails` are supported. `circles` are supported with `circles-support` capability
        + `400 Bad Request` When the conversation is a one-to-one conversation or a conversation to request a password for a share
        + `403 Forbidden` When the current user is not a moderator or owner
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the user or group to add could not be found

    - Data:

| field  | type | Description                                                      |
|--------|------|------------------------------------------------------------------|
| `type` | int  | In case the conversation type changed, the new value is returned |

## Delete an attendee by id from a conversation

* Method: `DELETE`
* Endpoint: `/room/{token}/attendees`
* Data:

| field        | type | Description               |
|--------------|------|---------------------------|
| `attendeeId` | int  | The participant to delete |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the participant is a moderator or owner
        + `400 Bad Request` When there are no other moderators or owners left
        + `403 Forbidden` When the current user is not a moderator or owner
        + `403 Forbidden` When the participant to remove is an owner
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the participant to remove could not be found

## Remove yourself from a conversation

* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/room/{token}/participants/self`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the participant is a moderator or owner and there are no other moderators or owners left.
        + `404 Not Found` When the conversation could not be found for the participant

## Join a conversation (available for call and chat)

* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/room/{token}/participants/active`
* Data:

| field      | type   | Description                                                                                                                                 |
|------------|--------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `password` | string | Optional: Password is only required for users which are self joined or guests and only when the conversation has `hasPassword` set to true. |
| `force`    | bool   | If set to `false` and the user has an active session already a `409 Conflict` will be returned (Default: true - to keep the old behaviour)  |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the password is required and didn't match
        + `404 Not Found` When the conversation could not be found for the participant
        + `409 Conflict` When the user already has an active Talk session in the conversation with this Nextcloud session. The suggested behaviour is to ask the user whether they want to kill the old session and force join unless the last ping is older than 60 seconds or older than 40 seconds when the conflicting session is not marked as in a call.

    - Data in case of `200 OK`: See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations)

    - Data in case of `409 Conflict`:

| field       | type   | Description                                                  |
|-------------|--------|--------------------------------------------------------------|
| `sessionId` | string | 512 character long string                                    |
| `inCall`    | int    | Flags whether the conflicting session is in a potential call |
| `lastPing`  | int    | Timestamp of the last ping of the conflicting session        |

## Resend participant emails

* Required capability: `sip-support`
* Method: `POST`
* Endpoint: `/room/{token}/participants/resend-invitations`
* Data:

| field        | type        | Description                                                                              |
|--------------|-------------|------------------------------------------------------------------------------------------|
| `attendeeId` | int or null | Attendee id can be used for guests and users, not setting it will resend all invitations |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator or owner
        + `404 Not Found` When the given attendee was not found in the conversation

## Set session state

* Required capability: `session-state`
* Federation capability: `federation-v1`
* Method: `PUT`
* Endpoint: `/room/{token}/participants/state`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the provided state is invalid (see [constants list](constants.md#participant-state))
        + `404 Not Found` When the conversation could not be found for the participant

## Leave a conversation (not available for call and chat anymore)

* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/room/{token}/participants/active`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant

## Promote a user or guest to moderator

* Method: `POST`
* Endpoint: `/room/{token}/moderators`
* Data:

| field        | type | Description                                  |
|--------------|------|----------------------------------------------|
| `attendeeId` | int  | Attendee id can be used for guests and users |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the participant to promote is not a normal user (type `3`), normal guest (type `4`) or self-joined user (type `5`)
        + `403 Forbidden` When the current user is not a moderator or owner
        + `403 Forbidden` When the participant to remove is an owner
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the participant to remove could not be found

## Demote a moderator to user or guest

* Method: `DELETE`
* Endpoint: `/room/{token}/moderators`
* Data:

| field        | type | Description                                  |
|--------------|------|----------------------------------------------|
| `attendeeId` | int  | Attendee id can be used for guests and users |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the participant to demote is not a moderator (type `2`) or guest moderator (type `6`)
        + `403 Forbidden` When the current participant is not a moderator or owner
        + `403 Forbidden` When the current participant tries to demote themselves
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the participant to demote could not be found

## Set permissions for an attendee

Setting custom permissions for a self-joined user will also make them a permanent user to the conversation.

* Method: `PUT`
* Endpoint: `/room/{token}/attendees/permissions`
* Data:

| field         | type   | Description                                                                                                                                                                                                                                                                                                                                                 |
|---------------|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `attendeeId`  | int    | Attendee id can be used for guests and users                                                                                                                                                                                                                                                                                                                |
| `method`      | string | Mode of how permissions should be manipulated [constants list](constants.md#attendee-permission-modifications). If the permissions were `0` (default) and the modification is `add` or `remove`, they will be initialised with the call or default conversation permissions before, falling back to `126` for moderators and `118` for normal participants. |
| `permissions` | int    | New permissions for the attendee, see [constants list](constants.md#attendee-permissions). If permissions are not `0` (default), the `1` (custom) permission will always be added.                                                                                                                                                                          |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support setting publishing permissions, e.g. one-to-one conversations
        + `400 Bad Request` When the attendee type is `groups` or `circles`
        + `400 Bad Request` When the mode is invalid
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the attendee to set publishing permissions could not be found

## Set permissions for all attendees

* Deprecated: 🏁 The API is no-op since Talk 20
* Method: `PUT`
* Endpoint: `/room/{token}/attendees/permissions/all`
* Data:

| field         | type   | Description                                                                                                                                                                                                                                                                                                                                                 |
|---------------|--------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `method`      | string | Mode of how permissions should be manipulated [constants list](constants.md#attendee-permission-modifications). If the permissions were `0` (default) and the modification is `add` or `remove`, they will be initialised with the call or default conversation permissions before, falling back to `126` for moderators and `118` for normal participants. |
| `permissions` | int    | New permissions for the attendees, see [constants list](constants.md#attendee-permissions). If permissions are not `0` (default), the `1` (custom) permission will always be added.                                                                                                                                                                         |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support setting publishing permissions, e.g. one-to-one conversations
        + `400 Bad Request` When the mode is invalid
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

## Get a participant by their pin

Note: Deprecated - Use [Verify a dial-in PIN](#Verify-a-dial-in-PIN) instead
Note: This is only allowed as validate SIP bridge requests

* Required capability: `sip-support`
* Method: `GET`
* Endpoint: `/room/{token}/pin/{pin}`

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the validation as SIP bridge failed
        + `404 Not Found` When the conversation or participant could not be found

    - Data: See array definition in `Get user´s conversations`

## Verify a dial-in PIN

Note: This is only allowed as validate SIP bridge requests

* Required capability: `sip-support-dialout`
* Method: `POST`
* Endpoint: `/room/{token}/verify-dialin`
* Data:

| field | type   | Description                         |
|-------|--------|-------------------------------------|
| `pin` | string | PIN the participant used to dial-in |

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the validation as SIP bridge failed
        + `404 Not Found` When the conversation or participant could not be found
        + `501 Not Implemented` When SIP is not configured

    - Data: See array definition in `Get user´s conversations`

## Verify a dial-out number

Note: This is only allowed as validate SIP bridge requests

* Required capability: `sip-support-dialout`
* Method: `POST`
* Endpoint: `/room/{token}/verify-dialout`
* Data:

| field     | type   | Description                                                                                                                     |
|-----------|--------|---------------------------------------------------------------------------------------------------------------------------------|
| `number`  | string | E164 formatted phone number                                                                                                     |
| `options` | string | Additional details to verify the validity of the request as JSON encoded object (Should contain actorId, actorType, attendeeId) |

* Response:
    - Status code:
        + `200 OK`
        + `401 Unauthorized` When the validation as SIP bridge failed
        + `404 Not Found` When the conversation or participant could not be found
        + `501 Not Implemented` When SIP or SIP dial-out is not configured

    - Data: See array definition in `Get user´s conversations`

## Reset call ID of rejected dial-out

Note: This is only allowed as validate SIP bridge requests

* Required capability: `sip-support-dialout`
* Method: `DELETE`
* Endpoint: `/room/{token}/rejected-dialout`
* Data:

| field     | type   | Description                                    |
|-----------|--------|------------------------------------------------|
| `callId`  | string | The call ID that was rejected                  |
| `options` | string | The options as received in the dialout request |

* Response:
    - Status code:
        + `200 OK` Call ID reset
        + `400 Bad Request` Call ID mismatch
        + `401 Unauthorized` SIP request invalid
        + `404 Not Found` Participant was not found
        + `501 Not Implemented` When SIP or SIP dial-out is not configured

## Set display name as a guest

* API: Only `v1`
* Method: `POST`
* Endpoint: `/guest/{token}/name`
* Data:

| field         | type   | Description          |
|---------------|--------|----------------------|
| `displayName` | string | The new display name |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a guest
        + `404 Not Found` When the conversation could not be found for the participant
