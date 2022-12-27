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

## Store call recording

* Required capability: `recording-v1`
* Method: `POPST`
* Endpoint: `/recording/{token}/store`

* Header:
| field                     | type   | Description                                                                                                               |
| ------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------------- |
| `TALK_SIPBRIDGE_RANDOM`   | string | Random string that need to be concatenated with room token to generate the checksum using the `sip_bridge_shared_secret`. |
| `TALK_SIPBRIDGE_CHECKSUM` | string | The checksum generated with `TALK_SIPBRIDGE_RANDOM`.                                                                      |

* Data:
| field   | type   | Description                                     |
| ------- | ------ | ----------------------------------------------- |
| `file`  | string | Blob of image in a multipart/form-data request. |
| `owner` | string | The moderator/owner of room.                    |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Message: `no_file`. No file defined to be stored.
        + `400 Bad Request` Message: `invalid_file`. File in block list or invalid.
        + `400 Bad Request` Message: `file_mimetype`. Invalid mimetype.
        + `400 Bad Request` Message: `file_name`. Invalid file name.
        + `400 Bad Request` Message: `file_extension`. Invalid file extension.
        + `400 Bad Request` Message: `owner_participant`. Onwer need to be a participant of room.
        + `400 Bad Request` Message: `owner_invalid`. Onwer invalid.
        + `400 Bad Request` Message: `owner_permission`. Onwer have not permission to store record file.
        + `401 Unauthorized` When the validation as SIP bridge failed
        + `404 Not Found` Invalid room or brute force identified.
