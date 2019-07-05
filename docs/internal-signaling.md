# Internal signaling API

See [External Signaling API](standalone-signaling-api-v1.md) for the Signaling of the High-Performance Backend.

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Get signaling settings

* Method: `GET`
* Endpoint: `/signaling/settings`
* Data:

    field | type | Description
    ------|------|------------
    `stunservers` | array | STUN servers
    `turnservers` | array | TURN servers
    `server` | string | URL of the external signaling server
    `ticket` | string | Ticket for the external signaling server

    - STUN server
    
       field | type | Description
       ------|------|------------
       `url` | string | STUN server URL

    - TURN server
    
       field | type | Description
       ------|------|------------
       `url` | array | One element array with TURN server URL
       `urls` | array | One element array with TURN server URL
       `username` | string | User name for the TURN server
       `credential` | string | User password for the TURN server

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found`

### External signaling API
