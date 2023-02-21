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
        + `401 Unauthorized` When the participant is a guest.
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
        + `400 Bad Request` Message: `call`. Call is not activated.
        + `401 Unauthorized` When the participant is a guest.
        + `403 Forbidden` When the user is not a moderator/owner.
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator.

## Store call recording

* Required capability: `recording-v1`
* Method: `POST`
* Endpoint: `/recording/{token}/store`

* Header:

| field                     | type   | Description                                                                                                                   |
| ------------------------- | ------ | ----------------------------------------------------------------------------------------------------------------------------- |
| `TALK_RECORDING_RANDOM`   | string | Random string that needs to be concatenated with room token to generate the checksum using the `recording_servers['secret']`. |
| `TALK_RECORDING_CHECKSUM` | string | The checksum generated with `TALK_RECORDING_RANDOM`.                                                                          |

* Data:

| field   | type   | Description                                               |
| ------- | ------ | --------------------------------------------------------- |
| `file`  | string | File with the recording in a multipart/form-data request. |
| `owner` | string | The person that started the recording.                    |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Error: `invalid_file`: File in block list or invalid
        + `400 Bad Request` Error: `empty_file`: Invalid file extension
        + `400 Bad Request` Error: `file_mimetype`: Invalid mimetype
        + `400 Bad Request` Error: `file_name`. :nvalid file name
        + `400 Bad Request` Error: `file_extension`: Invalid file extension
        + `400 Bad Request` Error: `owner_participant`: Owner is not to be a participant of room
        + `400 Bad Request` Error: `owner_invalid`: Owner invalid
        + `400 Bad Request` Error: `owner_permission`: Owner have not permission to store record file
        + `401 Unauthorized` When the validation as recording server failed
        + `404 Not Found` Room not found
        + `429 Too Many Request` Brute force protection

## Dismiss store call recording notification

* Required capability: `recording-v1`
* Method: `DELETE`
* Endpoint: `/recording/{token}/notification`
* Data:

| field       | type   | Description                                                           |
| ----------- | ------ | --------------------------------------------------------------------- |
| `timestamp` | string | Timestamp in seconds and UTC time zone that notification was created. |

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the user is not a moderator/owner.
        + `404 Not Found` Room not found

## Share store call recording

* Required capability: `recording-v1`
* Method: `POST`
* Endpoint: `/recording/{token}/share-chat`
* Data:

| field       | type    | Description                                                           |
| ----------- | ------- | --------------------------------------------------------------------- |
| `timestamp` | string  | Timestamp in seconds and UTC time zone that notification was created. |
| `fileId`    | integer | File id of recording to share at the room.                            |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` Error: `file`: Shared file is invalid
        + `400 Bad Request` Error: `system`: Internal system error
        + `403 Forbidden` When the user is not a moderator/owne
        + `404 Not Found` Room not found

## Recording server requests

* Required capability: `recording-v1`
* Method: `POST`
* Endpoint: `/recording/backend`

* Header:

| field                     | type   | Description                                                                                                                         |
| ------------------------- | ------ | ----------------------------------------------------------------------------------------------------------------------------------- |
| `TALK_RECORDING_RANDOM`   | string | Random string that needs to be concatenated with request body to generate the checksum using the secret configured for the backend. |
| `TALK_RECORDING_CHECKSUM` | string | The checksum generated with `TALK_RECORDING_RANDOM`.                                                                                |

* Data:

    - Body as a JSON encoded string; format depends on the request type, see below.

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request`: When the body data does not match the expected format.
        + `403 Forbidden`: When the request validation failed.

### Started call recording

* Data format:

    ```json
    {
      "type": "started",
      "started": {
        "token": "the-token-of-the-room",
        "status": "the-type-of-recording (see [Constants - Call recording status](constants.md#call-recording-status))",
        "actor": {
          "type": "the-type-of-the-actor",
          "id": "the-id-of-the-actor",
        },
      },
    }
    ```

* Response:
    - (Additional) Status code:
        + `404 Not Found`: When the room is not found.

### Stopped call recording

* Data format:

    ```json
    {
      "type": "stopped",
      "stopped": {
        "token": "the-token-of-the-room",
        "actor": {
          "type": "the-type-of-the-actor",
          "id": "the-id-of-the-actor",
        },
      },
    }
    ```

    - `actor` is optional

* Response:
    - (Additional) Status code:
        + `404 Not Found`: When the room is not found.

### Failed call recording

* Data format:

    ```json
    {
      "type": "failed",
      "failed": {
        "token": "the-token-of-the-room",
      },
    }
    ```

* Response:
    - (Additional) Status code:
        + `404 Not Found`: When the room is not found.
