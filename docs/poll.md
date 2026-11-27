# Poll API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

With the `talk-polls-result-visibility` capability, `resultMode` is a bitmask.
Combine the flags with bitwise OR:

| Flag | Meaning |
|------|---------|
| `1` | Anonymous: never expose voter identities, regardless of the other flags |
| `2` | Show voter names before closing, after the participant has voted |
| `4` | Show voter names and results before voting, unless anonymous |
| `8` | Show results before voting, independently of voter-name visibility |

The web editor offers these name-visibility settings:

| Setting | `resultMode` without / with result preview |
|---------|-------------------------------------------|
| Always | `6` / `14` |
| After voting | `2` / `10` |
| After the poll ends | `0` / `8` |
| Never | `1` / `9` |

Names are never returned for drafts. Anonymous polls never expose voter identities
in API responses, exports, or signaling payloads. Flag `8` does not grant access to
names: with mode `10`, results are visible before voting but names appear only after
voting. Withdrawing a vote hides names again in modes `2` and `10`.

### Backward compatibility

The existing values retain their behavior: `0` shows results after voting and
names after closing; `1` hides results until closing and never shows names.
Existing polls require no database migration. The API parameters and response
fields are unchanged; settings are persisted in the existing `resultMode` column
and preserved in drafts, imports, exports, and federated requests. Unknown bits
and negative values are rejected with HTTP 400.

Clients must check `talk-polls-result-visibility` before submitting values other
than `0` and `1`. The web editor falls back to the original checkbox on older
servers and strips unsupported flags from imported drafts. Older clients can
continue creating polls using `0` and `1`; displaying the new modes correctly
requires clients to interpret the bitmask. Server-side visibility checks apply
regardless of the client version.

## Create a poll in a conversation

* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/poll/{token}`
* Data:

| field        | type         | Description                                                                                                                                                                                                                    |
|--------------|--------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `question`   | string       | The question of the poll                                                                                                                                                                                                       |
| `options`    | string[]     | Array of strings with the voting options                                                                                                                                                                                       |
| `resultMode` | int | Result visibility bitmask described above. Values other than `0` and `1` require `talk-polls-result-visibility`. |
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
| `resultMode` | int | Result visibility bitmask described above. Values other than `0` and `1` require `talk-polls-result-visibility`. |
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
| `votes`            | int[]    | Map with `'option-' + optionId` => number of votes (available after voting on public polls, after closing, or when result previews are enabled) |
| `actorType`        | string   | Actor type of the poll author (see [Constants - Attendee types](constants.md#attendee-types))                                                           |
| `actorId`          | string   | Actor ID identifying the poll author                                                                                                                    |
| `actorDisplayName` | string   | Display name of the poll author                                                                                                                         |
| `status`           | int      | Status of the poll (see [Constants - Poll status](constants.md#poll-status))                                                                            |
| `resultMode` | int | Result visibility bitmask described above. Values other than `0` and `1` require `talk-polls-result-visibility`. |
| `maxVotes`         | int      | Maximum amount of options a user can vote for, `0` means unlimited                                                                                      |
| `votedSelf`        | int[]    | Array of option ids the participant voted for                                                                                                           |
| `numVoters`        | int      | The number of unique voters (available when results are visible, or to the creator and moderators) |
| `details` | array[] | Who voted for each option, only when permitted by `resultMode`. Never returned for anonymous polls or drafts. See [Details](#details). |

### Details

| field            | type   | Description                                                                                                  |
|------------------|--------|--------------------------------------------------------------------------------------------------------------|
| actorType        | string | The actor type of the participant that voted (see [Constants - Attendee types](constants.md#attendee-types)) |
| actorId          | string | The actor id of the participant that voted                                                                   |
| actorDisplayName | string | The display name of the participant that voted                                                               |
| optionId         | int    | The option that was voted for                                                                                |
