# Poll API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

All calls to OCS endpoints require the `OCS-APIRequest` header to be set to `true`.

## Create a poll in a conversation

* Method: `POST`
* Endpoint: `/poll/{token}`
* Data:

| field        | type         | Description                                                                                                                                                                                                                    |
|--------------|--------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `question`   | string       | The question of the poll                                                                                                                                                                                                       |
| `options`    | string[]     | Array of strings with the voting options                                                                                                                                                                                       |
| `resultMode` | int          | The result and voting mode of the poll, `0` means participants can immediatelly see the result and who voted for which option. `1` means the result is hidden until the poll is closed and then only the summary is published. |
| `maxVotes`   | int          | Maximum amount of options a participant can vote for                                                                                                                                                                           |

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` When the question or the options were too long
        + `403 Forbidden` When the conversation is read-only
        + `403 Forbidden` When the actor does not have chat permissions
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

        See [Poll data](#poll-data)

## Get state or result of a poll

* Method: `GET`
* Endpoint: `/poll/{token}/{pollId}`

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case of any other error
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the poll id could not be found in the conversation
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

      See [Poll data](#poll-data)

## Vote on a poll

* Method: `POST`
* Endpoint: `/poll/{token}/{pollId}`
* Data:

| field        | type  | Description                                      |
|--------------|-------|--------------------------------------------------|
| `optionIds`  | int[] | The option IDs the participant wants to vote for |


* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` When an option id is invalid
        + `400 Bad Request` When too many options were voted
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the poll id could not be found in the conversation
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

      See [Poll data](#poll-data)

## Close a poll

* Method: `DELETE`
* Endpoint: `/poll/{token}/{pollId}`

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the participant is not the author of the poll or a moderator
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the poll id could not be found in the conversation
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

      See [Poll data](#poll-data)

## Poll data

| field              | type     | Description                                                                                                      |
|--------------------|----------|------------------------------------------------------------------------------------------------------------------|
| `id`               | int      | ID of the poll                                                                                                   |
| `question`         | string   | The question of the poll                                                                                         |
| `options`          | string[] | The options participants can vote for                                                                            |
| `votes`            | int[]    | Map with optionId => number of votes (only available for when the actor voted on public poll or the poll is closed) |
| `actorType`        | string   | Actor type of the poll author (see [Constants - Attendee types](constants.md#attendee-types))                    |
| `actorId`          | string   | Actor ID identifying the poll author                                                                             |
| `actorDisplayName` | string   | Display name of the poll author                                                                                  |
| `status`           | int      | Status of the poll (see [Constants - Poll status](constants.md#poll-status))                                     |
| `resultMode`       | int      | Result mode of the poll (see [Constants - Poll mode](constants.md#poll-mode))                                    |
| `maxVotes`         | int      | Maximum amount of options a user can vote for, `0` means unlimited                                               |
| `votedSelf`        | int[]    | Array of option ids the participant voted for                                                                    |
| `numVoters`        | int      | The number of unique voters that (only available for when the actor voted on public poll or the poll is closed)  |
| `details`          | array[]  | Detailed list who voted for which option (only available for public closed polls), see [Details](#details) below |

### Details

| field            | type   | Description                                                                                                  |
|------------------|--------|--------------------------------------------------------------------------------------------------------------|
| actorType        | string | The actor type of the participant that voted (see [Constants - Attendee types](constants.md#attendee-types)) |
| actorId          | string | The actor id of the participant that voted                                                                   |
| actorDisplayName | string | The display name of the participant that voted                                                               |
| optionId         | int    | The option that was voted for                                                                                |
