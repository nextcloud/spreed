# Webinar management

Group and public conversations can be used to host webinars. Those online meetings can have a lobby, which come with the following restrictions:

* Only moderators can start/join a call
* Only moderators can read and write chat messages
* Normal users can only join the room. They then pull the room endpoint regularly for an update and should start the chat and signaling as well as allowing to join the call, once the lobby got disabled.

## Base endpoint

* API v1: 🏁 Removed with API v4: Nextcloud 17 - 21
* API v2: 🏁 Removed with API v4: Nextcloud 19 - 21
* API v3: 🏁 Removed with API v4: Nextcloud 21 only
* API v4: Base endpoint `/ocs/v2.php/apps/spreed/api/v4`: since Nextcloud 22

## Set lobby for a conversation

* Required capability: `webinary-lobby`
* Method: `PUT`
* Endpoint: `/room/{token}/webinar/lobby`
* Data:

| field   | type     | Description                                                                              |
|---------|----------|------------------------------------------------------------------------------------------|
| `state` | int      | New state for the conversation (see [constants list](constants.md#webinar-lobby-states)) |
| `timer` | int/null | Timestamp when the lobby state is reset to no lobby                                      |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support lobby (only group and public conversation atm)
        + `400 Bad Request` When the given timestamp is invalid
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

    - Data: See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations)

## Enabled or disable SIP dial-in

* Required capability: `sip-support`
* Method: `PUT`
* Endpoint: `/room/{token}/webinar/sip`
* Data:

| field   | type | Description                                                    |
|---------|------|----------------------------------------------------------------|
| `state` | int  | New SIP state for the conversation (see [constants list](constants.md#sip-states)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the state was invalid or the same
        + `400 Bad Request` When the conversation is a breakout room
        + `401 Unauthorized` When the user can not enabled SIP
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When SIP is not configured on the server

    - Data: See array definition in [Get user´s conversations](conversation.md#get-user-s-conversations)
