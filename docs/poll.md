# Poll API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Create a poll in a conversation

* Federation capability: `federation-v1`
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
        + `400 Bad Request` When the room is a one-to-one conversation
        + `400 Bad Request` When the question or the options were too long or invalid (not strings)
        + `403 Forbidden` When the conversation is read-only
        + `403 Forbidden` When the actor does not have chat permissions
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

        See [Poll data](#poll-data)

# Edit a draft poll in a conversation

* Required capability: `edit-draft-poll`
* Method: `POST`
* Endpoint: `/poll/{token}/draft/{pollId}`
* Data:

| field        | type         | Description                                                                                                                                                                                                                    |
|--------------|--------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `question`   | string       | The question of the poll                                                                                                                                                                                                       |
| `options`    | string[]     | Array of strings with the voting options                                                                                                                                                                                       |
| `resultMode` | int          | The result and voting mode of the poll, `0` means participants can immediatelly see the result and who voted for which option. `1` means the result is hidden until the poll is closed and then only the summary is published. |
| `maxVotes`   | int          | Maximum amount of options a participant can vote for                                                                                                                                                                           |

* Response:
	- Status code:
		+ `200 OK`
		+ `400 Bad Request` Modifying poll is not possible
		+ `403 Forbidden` No permission to modify this poll
		+ `404 Not Found` When the draft poll could not be found
		
	- Data:

	  See [Poll data](#poll-data)

## Get state or result of a poll

* Federation capability: `federation-v1`
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

* Federation capability: `federation-v1`
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

* Federation capability: `federation-v1`
* Method: `DELETE`
* Endpoint: `/poll/{token}/{pollId}`

* Response:
    - Status code:
        + `200 OK`
        + `400 Bad Request` When the poll is already closed
        + `403 Forbidden` When the participant is not the author of the poll or a moderator
        + `404 Not Found` When the conversation could not be found for the participant
        + `404 Not Found` When the poll id could not be found in the conversation
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:

      See [Poll data](#poll-data)

## Poll data

!!! note

    Due to the structure of the `votes` array the response is not valid in XML.
    It is therefor recommended to use `format=json` or send the `Accept: application/json` header,
    to receive a JSON response.

| field              | type     | Description                                                                                                                                             |
|--------------------|----------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `id`               | int      | ID of the poll                                                                                                                                          |
| `question`         | string   | The question of the poll                                                                                                                                |
| `options`          | string[] | The options participants can vote for                                                                                                                   |
| `votes`            | int[]    | Map with `'option-' + optionId` => number of votes (only available for when the actor voted on public poll or the poll is closed)                       |
| `actorType`        | string   | Actor type of the poll author (see [Constants - Attendee types](constants.md#attendee-types))                                                           |
| `actorId`          | string   | Actor ID identifying the poll author                                                                                                                    |
| `actorDisplayName` | string   | Display name of the poll author                                                                                                                         |
| `status`           | int      | Status of the poll (see [Constants - Poll status](constants.md#poll-status))                                                                            |
| `resultMode`       | int      | Result mode of the poll (see [Constants - Poll mode](constants.md#poll-mode))                                                                           |
| `maxVotes`         | int      | Maximum amount of options a user can vote for, `0` means unlimited                                                                                      |
| `votedSelf`        | int[]    | Array of option ids the participant voted for                                                                                                           |
| `numVoters`        | int      | The number of unique voters that voted (only available when the actor voted on public poll or the poll is closed unless for the creator and moderators) |
| `details`          | array[]  | Detailed list who voted for which option (only available for public closed polls), see [Details](#details) below                                        |

### Details

| field            | type   | Description                                                                                                  |
|------------------|--------|--------------------------------------------------------------------------------------------------------------|
| actorType        | string | The actor type of the participant that voted (see [Constants - Attendee types](constants.md#attendee-types)) |
| actorId          | string | The actor id of the participant that voted                                                                   |
| actorDisplayName | string | The display name of the participant that voted                                                               |
| optionId         | int    | The option that was voted for                                                                                |
