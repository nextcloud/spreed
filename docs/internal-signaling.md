# Signaling API

* Base endpoint for API v1 is: `/ocs/v2.php/apps/spreed/api/v1`
* Base endpoint for API v2 is: `/ocs/v2.php/apps/spreed/api/v2`

## Get signaling settings

* Method: `GET`
* Endpoint: `/signaling/settings`
* Data:

    field | type | Description
    ---|---|---
    `token` | string | The conversation to get the signaling settings for

* Response:

    field | type | Added | Description
    ---|---|---|---
    `signalingMode` | string | v1 | See [Signaling modes](constants.md#Signaling_modes)
    `userId` | string | v1 | Current user id
    `hideWarning` | string | v1 | Don't show a performance warning although internal signaling is used
    `server` | string | v1 | URL of the external signaling server
    `ticket` | string | v1 | Ticket for the external signaling server
    `stunservers` | array | v1 | STUN servers
    `turnservers` | array | v1 | TURN servers
    `sipDialinInfo` | string | v2 | Generic SIP dial-in information for this conversation (admin free text containing the phone number etc)

    - STUN server
    
       field | type | Description
       ---|---|---
       `url` | string | STUN server URL

    - TURN server
    
       field | type | Description
       ---|---|---
       `url` | array | One element array with TURN server URL
       `urls` | array | One element array with TURN server URL
       `username` | string | User name for the TURN server
       `credential` | string | User password for the TURN server

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found`

### Internal signaling API

Todo

### External signaling API

See [External signaling API](standalone-signaling-api-v1.md) for the Signaling of the High-Performance Backend.
