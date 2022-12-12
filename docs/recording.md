# Call recording API

* API v1: 🏁 since Nextcloud 26

## Start call recording

* Required capability: `recording-v1`
* Method: `POST`
* Endpoint: `/recording/{token}`
* Data:

| Field  | Type | Description                                           |
| ------ | ---- | ----------------------------------------------------- |
| status | int  | Type of call recording when 1 is video and 2 is audio |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the status to start is invalid
        + `400 Bad Request` The haven't the capability `recording-v1`
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

## Stop call recording

* Required capability: `recording-v1`
* Method: `DELETE`
* Endpoint: `/recording/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` The haven't the capability `recording-v1`
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
