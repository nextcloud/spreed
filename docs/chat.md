# Chat API

Base endpoint is: `/ocs/v2.php/apps/spreed/api/v1`

## Receive chat messages of a conversation

* Method: `GET`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `lookIntoFuture` | int | `1` Poll and wait for new message or `0` get history of a conversation
    `limit` | int | Number of chat messages to receive (100 by default, 200 at most)
    `lastKnownMessageId` | int | Serves as an offset for the query. The lastKnownMessageId for the next page is available in the `X-Chat-Last-Given` header.
    `lastCommonReadId` | int | Send the last `X-Chat-Last-Common-Read` header you got, if you are interested in updates of the common read value. A 304 response does not allow custom headers and otherwise the server can not know if your value is modified or not.
    `timeout` | int | `$lookIntoFuture = 1` only, Number of seconds to wait for new messages (30 by default, 60 at most)
    `setReadMarker` | int | `1` to automatically set the read timer after fetching the messages, use `0` when your client calls `Mark chat as read` manually. (Default: `1`)
    `includeLastKnown` | int | `1` to include the last known message as well (Default: `0`)

* Response:
    - Status code:
        + `200 OK`
        + `304 Not Modified` When there were no older/newer messages
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Header:

        field | type | Description
        ------|------|------------
        `X-Chat-Last-Given` | int | Offset (lastKnownMessageId) for the next page.
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability and when lastCommonReadId was sent)

    - Data:
        Array of messages, each message has at least:

        field | type | Description
        ------|------|------------
        `id` | int | ID of the comment
        `token` | string | Conversation token
        `actorType` | string | `guests` or `users`
        `actorId` | string | User id of the message author
        `actorDisplayName` | string | Display name of the message author
        `timestamp` | int | Timestamp in seconds and UTC time zone
        `systemMessage` | string | empty for normal chat message or the type of the system message (untranslated)
        `messageType` | string | Currently known types are `comment`, `system` and `command`
        `isReplyable` | bool | True if the user can post a reply to this message (only available with `chat-replies` capability)
        `referenceId` | string | A reference string that was given while posting the message to be able to identify a sent message again (only available with `chat-reference-id` capability)
        `message` | string | Message string with placeholders (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
        `messageParameters` | array | Message parameters for `message` (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
        `parent` | array | **Optional:** See `Parent data` below

#### Parent data

* When deleted:

    field | type | Description
    ------|------|------------
    `id` | int | ID of the parent comment
    `deleted` | bool | `true` when the parent is deleted

* Regular message:

    Full message array as shown above, but `parent` will never be set for a parent message.


## Sending a new chat message

* Method: `POST`
* Endpoint: `/chat/{token}`
* Data:

    field | type | Description
    ------|------|------------
    `message` | string | The message the user wants to say
    `actorDisplayName` | string | Guest display name (ignored for logged in users)
    `replyTo` | int | The message ID this message is a reply to (only allowed for messages from the same conversation and when the message type is not `system` or `command`)
    `referenceId` | string | A reference string to be able to identify the message again in a "get messages" request, should be a random sha256 (only available with `chat-reference-id` capability)

* Response:
    - Status code:
        + `201 Created`
        + `400 Bad Request` In case of any other error
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator
        + `413 Payload Too Large` When the message was longer than the allowed limit of 32000 characters (or 1000 until Nextcloud 16.0.1, check the `spreed => config => chat => max-length` capability for the limit)

    - Header:

        field | type | Description
        ------|------|------------
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)

    - Data:
        The full message array of the new message, as defined in [Receive chat messages of a conversation](#receive-chat-messages-of-a-conversation)

## Mark chat as read

* Method: `POST`
* Endpoint: `/chat/{token}/read`
* Data:

    field | type | Description
    ------|------|------------
    `lastReadMessage` | int | The last read message ID

* Response:
    - Status code:
        + `200 OK`
        + `404 Not Found` When the room could not be found for the participant,
        or the participant is a guest.

    - Header:

        field | type | Description
        ------|------|------------
        `X-Chat-Last-Common-Read` | int | ID of the last message read by every user that has read privacy set to public. When the user themself has it set to private the value the header is not set (only available with `chat-read-status` capability)



## Get mention autocomplete suggestions

* Method: `GET`
* Endpoint: `/chat/{token}/mentions`
* Data:

    field | type | Description
    ------|------|------------
    `search` | string | Search term for name suggestions (should at least be 1 character)
    `limit` | int | Number of suggestions to receive (20 by default)
    `includeStatus` | bool | Whether the user status information also needs to be loaded

* Response:
    - Status code:
        + `200 OK`
        + `403 Forbidden` When the conversation is read-only
        + `404 Not Found` When the conversation could not be found for the participant
        + `412 Precondition Failed` When the lobby is active and the user is not a moderator

    - Data:
        Array of suggestions, each suggestion has at least:

        field | type | Description
        ------|------|------------
        `id` | string | The user id which should be sent as `@<id>` in the message (user ids that contain spaces as well as guest ids need to be wrapped in double-quotes when sending in a message: `@"space user"` and `@"guest/random-string"`)
        `label` | string | The displayname of the user
        `source` | string | The type of the user, currently only `users`, `guests` or `calls` (for mentioning the whole conversation
        `status` | string | Optional: Only available with `includeStatus=true` and for users with a set status
        `statusIcon` | string | Optional: Only available with `includeStatus=true` and for users with a set status
        `statusMessage` | string | Optional: Only available with `includeStatus=true` and for users with a set status

## System messages

* `conversation_created` - {actor} created the conversation
* `conversation_renamed` - {actor} renamed the conversation from "foo" to "bar"
* `call_started` - {actor} started a call
* `call_joined` - {actor} joined the call
* `call_left` - {actor} left the call
* `call_ended` - Call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration 30:23)
* `read_only_off` - {actor} unlocked the conversation
* `read_only` - {actor} locked the conversation
* `listable_none` - {actor} made the conversation accessible to participants
* `listable_users` - {actor} made the conversation accessible to regular users
* `listable_all` - {actor} made the conversation accessible to everone which includes users and guests
* `lobby_timer_reached` - The conversation is now open to everyone
* `lobby_none` - {actor} opened the conversation to everyone
* `lobby_non_moderators` - {actor} restricted the conversation to moderators
* `guests_allowed` - {actor} allowed guests in the conversation
* `guests_disallowed` - {actor} disallowed guests in the conversation
* `password_set` - {actor} set a password for the conversation
* `password_removed` - {actor} removed the password for the conversation
* `user_added` - {actor} added {user} to the conversation
* `user_removed` - {actor} removed {user} from the conversation
* `moderator_promoted` - {actor} promoted {user} to moderator
* `moderator_demoted` - {actor} demoted {user} from moderator
* `guest_moderator_promoted` - {actor} promoted {user} to moderator
* `guest_moderator_demoted` - {actor} demoted {user} from moderator
