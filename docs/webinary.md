# Webinary management

Group and public conversations can be used to host webinaries. Those online meetings can have a lobby, which come with the following restrictions:
* Only moderators can start/join a call
* Only moderators can read and write chat messages
* Normal users can only join the room. They then pull the room endpoint regularly for an update and should start the chat and signaling as well as allowing to join the call, once the lobby got disabled.


Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Set lobby for a conversation

* Required capability: `webinary-lobby`
* Method: `PUT`
* Endpoint: `/room/{token}/webinary/lobby`
* Data:

    field | type | Description
    ------|------|------------
    `state` | int | New state for the conversation
    `timer` | int/null | Timestamp when the lobby state is reset to all participants

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support lobby (only group and public conversation atm)
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
