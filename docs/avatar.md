# Conversation avatar API

* API v1: Base endpoint `/ocs/v2.php/apps/spreed/api/v1`: since Nextcloud 27

## Set conversations avatar

* Required capability: `avatar`
* Method: `POST`
* Endpoint: `/room/{token}/avatar`
* Data:

| field  | type   | Description                                                                                                                         |
|--------|--------|-------------------------------------------------------------------------------------------------------------------------------------|
| `file` | string | Blob of image in a multipart/form-data request. Only accept images with mimetype equal to PNG or JPEG and need to be squared image. |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When: is one-to-one, no image, file is too big, invalid mimetype or resource, isn't square, unknown error
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

    - Data:
        + `200 OK`: See array definition in `Get user´s conversations`
        + `400 Bad Request`: Array with a `message` field contain the error in user language

## Set emoji as avatar

* Required capability: `avatar`
* Method: `POST`
* Endpoint: `/room/{token}/avatar/emoji`
* Data:

| field   | type        | Description                                                                                                                                |
|---------|-------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `emoji` | string      | A single emoji being used as avatar (can contain properties like gender, skin color, age, job, etc.)                                       |
| `color` | string/null | HEX color code (6 times 0-9A-F) without the leading `#` character (omit to fallback to the default bright/dark mode icon background color) |

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the conversation is a one-to-one conversation
        + `400 Bad Request` When the emoji is not a single emoji
        + `400 Bad Request` When color was provided but is not matching the expected pattern
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

    - Data:
        + `200 OK`: See array definition in `Get user´s conversations`
        + `400 Bad Request`: Array with a `message` field contain the error in user language

## Delete conversations avatar

!!! note
    To determine if the delete option should be presented to the user, it's recommended to check the `isCustomAvatar` property of the [Get user´s conversations](conversation.md#get-user-s-conversations) API.


* Required capability: `avatar`
* Method: `DELETE`
* Endpoint: `/room/{token}/avatar`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator, owner or guest moderator
        + `404 Not Found` When the conversation could not be found for the participant

    - Data: See array definition in `Get user´s conversations`

## Get conversations avatar (binary)

* Required capability: `avatar`
* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/room/{token}/avatar`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
    - Body: the image file

## Get dark mode conversations avatar (binary)

* Required capability: `avatar`
* Federation capability: `federation-v1`
* Method: `GET`
* Endpoint: `/room/{token}/avatar/dark`

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
    - Body: the image file

## Get federated user avatar (binary)

* Required capability: `federation-v1`
* Method: `GET`
* Endpoint: `/proxy/{token}/user-avatar/{size}`
* Data:

| field     | type   | Description                              |
|-----------|--------|------------------------------------------|
| `size`    | int    | Only 64 and 512 are supported            |
| `cloudId` | string | Federation CloudID to get the avatar for |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
    - Body: the image file

## Get dark mode federated user avatar (binary)

* Required capability: `federation-v1`
* Method: `GET`
* Endpoint: `/proxy/{token}/user-avatar/{size}/dark`
* Data:

| field     | type   | Description                              |
|-----------|--------|------------------------------------------|
| `size`    | int    | Only 64 and 512 are supported            |
| `cloudId` | string | Federation CloudID to get the avatar for |

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the conversation could not be found for the participant
    - Body: the image file
