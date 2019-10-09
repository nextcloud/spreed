# Integration

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Get conversation for an internal file

* Method: `GET`
* Endpoint: `/file/{fileId}`
* Data:

    field | type | Description
    ------|------|------------
    `fileId` | int | File id for which the conversation should be given

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the item was not found
        + `404 Not Found` When the found item is not a file
        + `404 Not Found` When the user can not access the file
        + `404 Not Found` When the file is not shared with anyone

    - Data:

        field | type | Description
        ------|------|------------
        `token` | string | The token of the conversation for this file

## Get conversation for a public share

* Method: `GET`
* Endpoint: `/publicshare/{shareToken}`
* Data:

    field | type | Description
    ------|------|------------
    `shareToken` | string | Share token for which the conversation should be given

* Response:
    - Header:
        + `200 OK`
        + `404 Not Found` When the share was not found
        + `404 Not Found` When the user can not access the share
        + `404 Not Found` When the shared item is not a file

    - Data:

        field | type | Description
        ------|------|------------
        `token` | string | The token of the conversation for this file
