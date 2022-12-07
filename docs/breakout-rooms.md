# Breakout rooms management

Group and public conversations can be used to host breakout rooms.

* Only moderators can configure and remove breakout rooms
* Only moderators can start and stop breakout rooms
* Moderators in the parent conversation are added as moderators to all breakout rooms and remove from all on demotion

## Base endpoint

* API v1: Base endpoint `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 26

## Configure breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}`
* Data:

| field         | type   | Description                                                                                          |
|---------------|--------|------------------------------------------------------------------------------------------------------|
| `mode`        | int    | Participant assignment mode (see [constants list](constants.md#breakout-room-modes))                 |
| `amount`      | int    | Number of breakout rooms to create (Minimum `1`, maximum `20`)                                       |
| `attendeeMap` | string | A json encoded Map of attendeeId => room number (0 based) (Only considered when the mode is "manual" |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When breakout rooms are disabled on the server
        + `400 Bad Request` When breakout rooms are already configured
        + `400 Bad Request` When the conversation is not a group or public conversation
        + `400 Bad Request` When the conversation is a breakout room itself
        + `400 Bad Request` When the mode is invalid
        + `400 Bad Request` When the amount is below the minimum or above the maximum
        + `400 Bad Request` When the attendee map contains an invalid room number
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Remove breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Start breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/rooms`

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` When breakout rooms are not configured
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant

## Stop breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}/rooms`

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` When breakout rooms are not configured
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant

## Broadcast message to breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/broadcast`
* Data:

| field     | type   | Description                                                                                                    |
|-----------|--------|----------------------------------------------------------------------------------------------------------------|
| `message` | string | A chat message to be posted in all breakout rooms in the name of the moderator                                 |
| `token`   | string | **Note:** The token in the URL is the parent room. The message will appear in all breakout rooms automatically |

* Response:
	- Status code:
		+ `201 Created`
		+ `400 Bad Request` When the room does not have breakout rooms configured
		+ `403 Forbidden` When the participant is not a moderator
		+ `404 Not Found` When the conversation could not be found for the participant
		+ `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (check the `spreed => config => chat => max-length` capability for the limit)

## Request assistance

This endpoint allows participants to raise their hand (token is the breakout room) and moderators will see it in any of the breakout rooms as well as the parent room.

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/request-assistance`
* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` When the room is not a breakout room or breakout rooms are not started
		+ `404 Not Found` When the conversation could not be found for the participant

## Reset request for assistance

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}/request-assistance`
* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` When the room does not have breakout rooms configured
		+ `404 Not Found` When the conversation could not be found for the participant
