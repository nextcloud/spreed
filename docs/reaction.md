# Reaction API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## React to a message

* Required capability: `reactions`
* Method: `POST`
* Endpoint: `/reaction/{token}/{messageId}`
* Data:

    field | type | Description
    ---|---|---
    `reaction` | string | the reaction emoji

* Response:
    - Status code:
        + `200 OK` Reaction already exists
        + `201 Created`
        + `400 Bad Request` In case of no reaction support, message out of reactions context or any other error
        + `404 Not Found` When the conversation or message to react could not be found for the participant
        + `409 Conflict` User already did this reaction to this message

    - Data:
        Array with data of reactions:

        field | type | Description
        ---|---|---
        `actorType` | string | `guests` or `users`
        `actorId` | string | Actor id of the reacting participant
        `actorDisplayName` | string | Display name of the reaction author
        `timestamp` | int | Timestamp in seconds and UTC time zone

## Delete a reaction

* Required capability: `reactions`
* Method: `DELETE`
* Endpoint: `/reaction/{token}/{messageId}`
* Data:

    field | type | Description
    ---|---|---
    `reaction` | string | the reaction emoji

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case of no reaction support, message out of reactions context or any other error
        + `404 Not Found` When the conversation or message to react or reaction could not be found for the participant

    - Data:
        Array with data of reactions:

        field | type | Description
        ---|---|---
        `actorType` | string | `guests` or `users`
        `actorId` | string | Actor id of the reacting participant
        `actorDisplayName` | string | Display name of the reaction author
        `timestamp` | int | Timestamp in seconds and UTC time zone

## Retrieve reactions of a message by type

* Required capability: `reactions`
* Method: `GET`
* Endpoint: `/reaction/{token}/{messageId}`
* Data:

    field | type | Description
    ---|---|---
    `reaction` | string | **Optional:** the reaction emoji

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` In case of no reaction support, message out of reactions context or any other error
        + `404 Not Found` When the conversation or message to react could not be found for the participant

    - Data:
        Array with data of reactions:

        field | type | Description
        ---|---|---
        `actorType` | string | `guests` or `users`
        `actorId` | string | Actor id of the reacting participant
        `actorDisplayName` | string | Display name of the reaction author
        `timestamp` | int | Timestamp in seconds and UTC time zone
