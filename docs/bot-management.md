# Setup and management bots

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`: (requires the `bots-v1` capability - available since Nextcloud 27.1)

## Get list of bots installed on the server

Lists the bots that are installed on the server.

* Required capability: `bots-v1`
* Method: `GET`
* Endpoint: `bot/admin`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not an administrator
    - Data:
      List of bots, each bot has at least:

| field                | type   | Description                                                                                  |
|----------------------|--------|----------------------------------------------------------------------------------------------|
| `id`                 | int    | Unique numeric identifier of the bot on this server                                          |
| `name`               | string | Display name of the bot shown as author when it posts a message or reaction                  |
| `description`        | string | A longer description of the bot helping moderators to decide if they want to enable this bot |
| `url`                | string | URL endpoint that is triggered by this bot                                                   |
| `url_hash`           | string | Hash of the URL prefixed with `bot-` serves as `actor_id`                                    |
| `state`              | int    | One of the [Bot states](constants.md#bot-states)                                             |
| `error_count`        | int    | Number of consecutive errors                                                                 |
| `last_error_date`    | int    | UNIX timestamp of the last error                                                             |
| `last_error_message` | string | The last exception message or error response information when trying to reach the bot.       |

## Get list of bots for a conversation

Lists the bots that are enabled and can be enabled for the conversation

* Required capability: `bots-v1`
* Method: `GET`
* Endpoint: `bot/{token}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
    - Data:
      List of bots, each bot has at least:

| field                 | type   | Description                                                                                  |
|-----------------------|--------|----------------------------------------------------------------------------------------------|
| `id`                  | int    | Unique numeric identifier of the bot on this server                                          |
| `name`                | string | Display name of the bot shown as author when it posts a message or reaction                  |
| `description`         | string | A longer description of the bot helping moderators to decide if they want to enable this bot |
| `state`               | int    | One of the [Bot states](constants.md#bot-states)                                             |

## Enable a bot for a conversation as a moderator

* Required capability: `bots-v1`
* Method: `POST`
* Endpoint: `bot/{token}/{botId}`

* Response:
    - Status code:
        + `200 OK` When the bot is already enabled in the conversation
        + `201 Created` When the bot is now enabled in the conversation
        + `400 Bad Request` When the bot ID is unknown on the server
        + `400 Bad Request` When the bot is disabled or set to "no-setup" on the server
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant

## Disable a bot for a conversation as a moderator

* Required capability: `bots-v1`
* Method: `DELETE`
* Endpoint: `bot/{token}/{botId}`

* Response:
    - Status code:
        + `200 OK` When the bot is already enabled in the conversation
        + `201 Created` When the bot is now enabled in the conversation
        + `400 Bad Request` When the bot ID is unknown on the server
        + `400 Bad Request` When the bot is disabled or set to "no-setup" on the server
        + `403 Forbidden` When the current user is not a moderator/owner
        + `404 Not Found` When the conversation could not be found for the participant
