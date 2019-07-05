# Call API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Get list of connected participants

* Method: `GET`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant

    - Data:
        Array of participants, each participant has at least:

        field | type | Description
        ------|------|------------
        `userId` | string | Is empty for guests
        `lastPing` | int | Timestamp of the last ping of the user (should be used for sorting)
        `sessionId` | string | 512 character long string

## Join a call

* Method: `POST`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant

## Leave a call (but staying in the conversation for future calls and chat)

* Method: `DELETE`
* Endpoint: `/call/{token}`

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
