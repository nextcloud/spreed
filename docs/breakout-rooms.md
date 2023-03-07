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

| field         | type   | Description                                                                                           |
|---------------|--------|-------------------------------------------------------------------------------------------------------|
| `mode`        | int    | Participant assignment mode (see [constants list](constants.md#breakout-room-modes))                  |
| `amount`      | int    | Number of breakout rooms to create (Minimum `1`, maximum `20`)                                        |
| `attendeeMap` | string | A json encoded Map of attendeeId => room number (0 based) (Only considered when the mode is "manual") |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Error `config`: When breakout rooms are disabled on the server
        + `400 Bad Request` Error `mode`: When breakout rooms are already configured
        + `400 Bad Request` Error `room`: When the conversation is not a group conversation
        + `400 Bad Request` Error `room`: When the conversation is a breakout room itself
        + `400 Bad Request` Error `mode`: When the mode is invalid
        + `400 Bad Request` Error `amount`: When the amount is below the minimum or above the maximum
        + `400 Bad Request` Error `attendeeMap`: When the attendee map contains an invalid room number or moderator
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
    - Data: Array of conversations (breakout rooms and parent)
      See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Create additional breakout room

In order to create an additional breakout room, create a conversation with object type `room` and object ID being the parent token.
See [Creating a new conversation](conversation.md#creating-a-new-conversation) for more information.

## Delete individual breakout room

The normal deletion API can be used.
See [Delete a conversation](conversation.md#delete-a-conversation) for more information.

## Remove breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
    - Data: Parent conversation
      See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Start breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/rooms`

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `mode`: When breakout rooms are not configured
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Array of conversations (breakout rooms and parent)
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Stop breakout rooms

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}/rooms`

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `mode`: When breakout rooms are not configured
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Array of conversations (breakout rooms and parent)
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

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
		+ `400 Bad Request` Error `mode`: When the room does not have breakout rooms configured
		+ `403 Forbidden` When the participant is not a moderator
		+ `404 Not Found` When the conversation could not be found for the participant
		+ `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (check the `spreed => config => chat => max-length` capability for the limit)
	- Data: Array of conversations (breakout rooms and parent)
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Reorganize attendees

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/attendees`
* Data:

| field         | type   | Description                                                                                           |
|---------------|--------|-------------------------------------------------------------------------------------------------------|
| `attendeeMap` | string | A json encoded Map of attendeeId => room number (0 based) (Only considered when the mode is "manual") |

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `config`: When breakout rooms are disabled on the server
		+ `400 Bad Request` Error `mode`: When breakout rooms are not configured
		+ `400 Bad Request` Error `attendeeMap`: When the attendee map contains an invalid room number or moderator
		+ `403 Forbidden` When the current user is not a moderator/owner
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Array of conversations (breakout rooms and parent)
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Request assistance

This endpoint allows participants to raise their hand (token is the breakout room) and moderators will see it in any of the breakout rooms as well as the parent room.

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/request-assistance`
* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `room`: When the room is not a breakout room or breakout rooms are not started
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Breakout room
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## Reset request for assistance

* Required capability: `breakout-rooms-v1`
* Method: `DELETE`
* Endpoint: `/breakout-rooms/{token}/request-assistance`
* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `room`: When the room is not a breakout room or breakout rooms are not started
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Breakout room
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)

## List all breakout rooms

See [conversation API](conversation.md#get-breakout-rooms))

## Switch to a different breakout room (as non moderator)

This endpoint allows participants to switch between breakout rooms when they are allowed to choose the breakout room and not are automatically or manually assigned by the moderator.

* Required capability: `breakout-rooms-v1`
* Method: `POST`
* Endpoint: `/breakout-rooms/{token}/switch`
* Data:

| field    | type   | Description                                                                   |
|----------|--------|-------------------------------------------------------------------------------|
| `token`  | string | (In the URL) Conversation token of the parent room hosting the breakout rooms |
| `target` | string | Conversation token of the target breakout room                                |

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Error `moderator`: When the participant is a moderator in the conversation
		+ `400 Bad Request` Error `mode`: When breakout rooms are not configured in `free` mode
		+ `400 Bad Request` Error `status`: When breakout rooms are not started
		+ `400 Bad Request` Error `target`: When the target room is not breakout room of the parent
		+ `404 Not Found` When the conversation could not be found for the participant
	- Data: Target breakout room
	  See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations) (API v4)
