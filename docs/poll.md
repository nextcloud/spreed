# Poll API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

With the `talk-polls-live-voters` capability, public polls can opt into showing
voter names before the poll closes. Pass the optional boolean
`showVotersBeforeClosing` when creating a poll or creating/updating a draft.
It defaults to `false` and is ignored for hidden polls (`resultMode = 1`).
Existing polls keep their previous behavior. While an opted-in public poll is
open, voters can see who voted for each option after casting their own
vote, or before voting when `showResultsBeforeVoting` is also enabled.
Withdrawing the vote hides these details again unless result previews are enabled. Once a public poll is
closed, all participants can see the voter details as before. Hidden polls
never expose names.

With `talk-polls-results-before-voting`, the optional boolean
`showResultsBeforeVoting` allows all participants to see counts and percentages
before voting. It defaults to `false` and works independently of `resultMode`
and `showVotersBeforeClosing`, including on hidden polls. It does not grant
access to voter names by itself: those remain hidden until closing unless
`showVotersBeforeClosing` is also enabled on a public poll. With both settings
enabled, names are visible before voting as well. The setting is
preserved in drafts and can be changed when editing a draft.

## Create a poll in a conversation

* Federation capability: `federation-v1`
* Method: `POST`
* Endpoint: `/poll/{token}`
* Data:

| field        | type         | Description                                                                                                                                                                                                                    |
|--------------|--------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `question`   | string       | The question of the poll                                                                                                                                                                                                       |
| `options`    | string[]     | Array of strings with the voting options                                                                                                                                                                                       |
| `resultMode` | int          | `0`: Results are visible after voting; names are shown after closing, or after voting when `showVotersBeforeClosing` is enabled. `1`: Only the summary is published after closing, without names. |
| `showVotersBeforeClosing` | bool | Optional, defaults to `false`. Show names to voters before closing a public poll. Requires `talk-polls-live-voters`. |
| `showResultsBeforeVoting` | bool | Optional, defaults to `false`. Show counts and percentages before voting, including hidden polls. Requires `talk-polls-results-before-voting`. |
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
| `resultMode` | int          | `0`: Results are visible after voting; names are shown after closing, or after voting when `showVotersBeforeClosing` is enabled. `1`: Only the summary is published after closing, without names. |
| `showVotersBeforeClosing` | bool | Optional, defaults to `false`. Show names to voters before closing a public poll. Requires `talk-polls-live-voters`. |
| `showResultsBeforeVoting` | bool | Optional, defaults to `false`. Show counts and percentages before voting, including hidden polls. Requires `talk-polls-results-before-voting`. |
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
| `votes`            | int[]    | Map with `'option-' + optionId` => number of votes (available after voting on public polls, after closing, or when `showResultsBeforeVoting` is enabled) |
| `actorType`        | string   | Actor type of the poll author (see [Constants - Attendee types](constants.md#attendee-types))                                                           |
| `actorId`          | string   | Actor ID identifying the poll author                                                                                                                    |
| `actorDisplayName` | string   | Display name of the poll author                                                                                                                         |
| `status`           | int      | Status of the poll (see [Constants - Poll status](constants.md#poll-status))                                                                            |
| `resultMode`       | int      | Result mode of the poll (see [Constants - Poll mode](constants.md#poll-mode))                                                                           |
| `maxVotes`         | int      | Maximum amount of options a user can vote for, `0` means unlimited                                                                                      |
| `votedSelf`        | int[]    | Array of option ids the participant voted for                                                                                                           |
| `numVoters`        | int      | The number of unique voters (available when results are visible, or to the creator and moderators) |
| `showVotersBeforeClosing` | bool | Whether names are visible to voters before closing a public poll |
| `showResultsBeforeVoting` | bool | Whether counts and percentages are visible before voting |
| `details`          | array[]  | Who voted for each option: available for public closed polls, or with `showVotersBeforeClosing` after voting or when result previews are enabled. See [Details](#details). |

### Details

| field            | type   | Description                                                                                                  |
|------------------|--------|--------------------------------------------------------------------------------------------------------------|
| actorType        | string | The actor type of the participant that voted (see [Constants - Attendee types](constants.md#attendee-types)) |
| actorId          | string | The actor id of the participant that voted                                                                   |
| actorDisplayName | string | The display name of the participant that voted                                                               |
| optionId         | int    | The option that was voted for                                                                                |
