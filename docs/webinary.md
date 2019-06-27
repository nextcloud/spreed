# Webinary management

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Set lobby for a conversation

* Required capability: `webinary-lobby`
* Method: `PUT`
* Endpoint: `/room/{token}/webinary/lobby`
* Data:

    field | type | Description
    ------|------|------------
    `state` | int | New state for the conversation

* Response:
    - Header:
        + `200 OK`
        + `400 Bad Request` When the conversation type does not support lobby (only group and public conversation atm)
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
