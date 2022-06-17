# Signaling API

* Base endpoint for API v1 is: 🏁 Removed with API v3: Nextcloud 13 - 21
* Base endpoint for API v2 is: 🏁 Removed with API v3: Nextcloud 21 only
* Base endpoint for API v3 is: `/ocs/v2.php/apps/spreed/api/v3`: since Nextcloud 22

All calls to OCS endpoints require the `OCS-APIRequest` header to be set to `true`.

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
    `stunservers` | array | v3 | STUN servers
    `turnservers` | array | v3 | TURN servers
    `sipDialinInfo` | string | v2 | Generic SIP dial-in information for this conversation (admin free text containing the phone number etc)

    - STUN server

       field | type | Description
       ------|------|------------
       `urls` | array | Each element is a STUN server URL as a string

    - TURN server

       field | type | Description
       ------|------|------------
       `urls` | array | Each element is a TURN server URL as a string
       `username` | string | User name for the TURN server
       `credential` | string | User password for the TURN server

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found`

### Internal signaling API

Todo

### External signaling API

See [External signaling API](https://nextcloud-spreed-signaling.readthedocs.io/en/latest/) for the Signaling API of the High-Performance Backend.
