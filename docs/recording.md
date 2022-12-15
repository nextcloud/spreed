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
        + `400 Bad Request` Message: `status`. When the status to start is invalid.
        + `400 Bad Request` Message: `config`. Need to enable the config `recording`.
        + `400 Bad Request` Message: `room`. Already have a recording in progress.
        + `400 Bad Request` Message: `call`. Call is not activated.
        + `403 Forbidden` When the user is not a moderator/owner.
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator.

## Stop call recording

* Required capability: `recording-v1`
* Method: `DELETE`
* Endpoint: `/recording/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Message: `config`. Need to enable the config `recording`.
        + `400 Bad Request` Message: `room`. Recording has already been stopped.
        + `400 Bad Request` Message: `call`. Call is not activated.
        + `403 Forbidden` When the user is not a moderator/owner.
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator.
