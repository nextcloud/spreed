# Call recording API

* API v1: Base endpoint `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 26

## Start call recording

* Required capability: `recording-v1`
* Method: `POST`
* Endpoint: `/recording/{token}`
* Data:

| Field  | Type | Description                                                                                          |
| ------ | ---- | ---------------------------------------------------------------------------------------------------- |
| status | int  | Type of call recording (see [Constants - Call recording status](constants.md#call-recording-status)) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Message: `status`. When the status to start is invalid.
        + `400 Bad Request` Message: `config`. Need to enable the config `recording`.
        + `400 Bad Request` Message: `recording`. Already have a recording in progress.
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
        + `400 Bad Request` Message: `recording`. Recording has already been stopped.
        + `400 Bad Request` Message: `call`. Call is not activated.
        + `403 Forbidden` When the user is not a moderator/owner.
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator.
